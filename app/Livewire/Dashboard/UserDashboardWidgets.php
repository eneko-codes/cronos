<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserLeave;
use App\Models\UserSchedule;
use App\Services\DurationFormatterService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Lazy]
class UserDashboardWidgets extends Component
{
    #[Locked]
    public int $userId;

    public ?array $todaysSchedule = null;

    public ?array $todaysAttendance = null;

    public array $todaysTimeEntries = []; // Array of today's time entries

    public ?array $todaysLeave = null;

    public ?int $upcomingLeaveId = null;

    public function mount(int $userId): void
    {
        $this->userId = $userId;

        $user = $this->user;

        if (! $user->do_not_track) {
            // Today's Schedule
            $userSchedule = UserSchedule::where('user_id', $this->userId)
                ->where('effective_from', '<=', now()->toDateString())
                ->effectiveAt(now()->toDateString())
                ->latest('effective_from')
                ->first();
            $schedule = $userSchedule?->schedule;
            if ($schedule) {
                $todayWeekday = (now()->dayOfWeek + 6) % 7; // 0 (Mon) - 6 (Sun) - matches Odoo format
                $today = now()->toDateString();

                // Filter schedule details to only include explicitly active ones for today
                $details = $schedule->scheduleDetails()
                    ->forWeekday($todayWeekday)
                    ->activeForDate($today)
                    ->get();

                $totalMinutes = 0;
                $slots = [];
                $detailedSlots = [];
                /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\ScheduleDetail> $details */
                foreach ($details as $detail) {
                    /** @var \App\Models\ScheduleDetail $detail */
                    $start = $detail->start;
                    $end = $detail->end;
                    $minutesForSlot = $start->diffInMinutes($end);
                    $totalMinutes += $minutesForSlot;

                    // Simple slots for backward compatibility
                    $slots[] = [
                        'start' => $start->format('H:i'),
                        'end' => $end->format('H:i'),
                    ];

                    // Detailed slots without period labels
                    $detailedSlots[] = [
                        'start' => $start->format('H:i'),
                        'end' => $end->format('H:i'),
                        'period' => $detail->day_period,
                        'name' => $detail->name,
                        'formatted' => "{$start->format('H:i')} - {$end->format('H:i')}",
                        'duration' => DurationFormatterService::fromMinutes($minutesForSlot),
                    ];
                }
                $duration = DurationFormatterService::fromMinutes($totalMinutes);
                $this->todaysSchedule = [
                    'name' => $schedule->description,
                    'duration' => $duration,
                    'slots' => $slots,
                    'detailedSlots' => $detailedSlots,
                    'scheduleId' => $schedule->odoo_schedule_id,
                    'averageHoursDay' => $schedule->average_hours_day,
                    'flexibleHours' => $schedule->flexible_hours,
                    'twoWeeksCalendar' => $schedule->two_weeks_calendar,
                ];
            } else {
                $this->todaysSchedule = null;
            }

            // Today's Leave
            $todaysLeave = UserLeave::forUser($this->userId)
                ->activeOn(now()->toDateString())
                ->whereIn('status', ['validate', 'confirm'])
                ->with(['leaveType'])
                ->first();

            if ($todaysLeave) {
                // Trust Odoo's duration_days calculation - it already accounts for working calendars
                // Convert to hours for display (Odoo typically uses 8h as standard work day)
                $standardWorkDayHours = 8;
                $leaveDurationMinutes = (int) round($todaysLeave->duration_days * $standardWorkDayHours * 60);

                // Show duration for partial days, hide for full days in dashboard context
                $showDuration = $todaysLeave->duration_days < 1;

                // Calculate actual time range from start_date/end_date (same logic as timesheet)
                $startTime = $todaysLeave->start_date->format('H:i');
                $endTime = $todaysLeave->end_date->format('H:i');
                $actualTimeRange = "{$startTime} - {$endTime}";

                // Only show time range for partial-day leaves, and avoid meaningless ranges
                $shouldShowTimeRange = $todaysLeave->duration_days < 1 &&
                                     $actualTimeRange !== '00:00 - 00:00' &&
                                     $startTime !== $endTime;

                $this->todaysLeave = [
                    'type' => $todaysLeave->leaveType ? $todaysLeave->leaveType->name : 'Leave',
                    'duration' => $showDuration && $leaveDurationMinutes > 0
                        ? DurationFormatterService::fromMinutes($leaveDurationMinutes)
                        : '',
                    'status' => $todaysLeave->status,
                    'isHalfDay' => $todaysLeave->isHalfDay(),
                    'timePeriod' => $todaysLeave->isMorningLeave() ? 'Morning' : ($todaysLeave->isAfternoonLeave() ? 'Afternoon' : 'Full day'),
                    'timeRange' => $shouldShowTimeRange ? $actualTimeRange : null,
                    'durationDays' => $todaysLeave->duration_days,
                ];
            } else {
                $this->todaysLeave = null;
            }

            // Today's Attendance (handles multiple segments)
            $attendances = UserAttendance::forUser($this->userId)
                ->forDate(now()->toDateString())
                ->orderBy('clock_in')
                ->get();

            if ($attendances->isEmpty()) {
                $this->todaysAttendance = [
                    'status' => 'Not Clocked In',
                    'duration' => '0h 0m',
                    'is_remote' => false,
                    'clockedIn' => false,
                    'start' => null,
                    'end' => null,
                    'segments' => [],
                ];
            } else {
                $isRemote = $attendances->first()->is_remote;
                $totalSeconds = 0;

                // Get first clock-in and last clock-out
                $firstClockIn = $attendances->whereNotNull('clock_in')->min('clock_in');
                $lastClockOut = $attendances->whereNotNull('clock_out')->max('clock_out');

                // Check if currently clocked in (has clock_in but no clock_out in latest segment)
                $latestSegment = $attendances->sortByDesc('clock_in')->first();
                $clockedIn = $latestSegment && $latestSegment->clock_in && ! $latestSegment->clock_out;

                // Build segments array and calculate total duration
                $segments = $attendances->map(function ($attendance) use (&$totalSeconds) {
                    $segmentSeconds = 0;

                    if ($attendance->clock_out) {
                        // Completed segment: use stored duration
                        $segmentSeconds = (int) $attendance->duration_seconds;
                    } elseif ($attendance->clock_in) {
                        // Active segment: calculate from clock_in until now
                        $segmentSeconds = $attendance->clock_in->diffInSeconds(now());
                    }

                    $totalSeconds += $segmentSeconds;

                    return [
                        'clock_in' => $attendance->clock_in ? $attendance->clock_in->setTimezone(config('app.timezone'))->format('H:i') : null,
                        'clock_out' => $attendance->clock_out ? $attendance->clock_out->setTimezone(config('app.timezone'))->format('H:i') : null,
                        'duration' => DurationFormatterService::fromSeconds($segmentSeconds),
                    ];
                })->toArray();

                $durationMinutes = (int) ($totalSeconds / 60);

                $this->todaysAttendance = [
                    'status' => $isRemote ? 'Remote' : 'In Office',
                    'duration' => DurationFormatterService::fromMinutes($durationMinutes),
                    'is_remote' => $isRemote,
                    'clockedIn' => $clockedIn,
                    'start' => $firstClockIn ? $firstClockIn->setTimezone(config('app.timezone'))->format('H:i') : null,
                    'end' => $lastClockOut ? $lastClockOut->setTimezone(config('app.timezone'))->format('H:i') : null,
                    'segments' => $segments,
                ];
            }

            // Today's Time Entries
            $timeEntries = TimeEntry::forUser($this->userId)
                ->forDate(now()->toDateString())
                ->with(['project', 'task'])
                ->orderBy('proofhub_created_at')
                ->get();

            $this->todaysTimeEntries = $timeEntries->map(function ($entry) {
                return [
                    'duration' => $entry->formatted_duration,
                    'duration_seconds' => $entry->duration_seconds,
                    'description' => $entry->description,
                    'project_name' => $entry->project->title ?? 'Unknown Project',
                    'task_name' => $entry->task->name ?? null,
                    'status' => $entry->status,
                ];
            })->toArray();

            // Upcoming Leave
            $upcomingLeave = UserLeave::forUser($this->userId)
                ->approved()
                ->upcoming()
                ->orderBy('start_date')
                ->first();
            $this->upcomingLeaveId = $upcomingLeave?->id;
        } else {
            $this->todaysSchedule = null;
            $this->todaysLeave = null;
            $this->todaysAttendance = [
                'status' => 'Not Tracked',
                'duration' => '0h 0m',
                'is_remote' => false,
                'clockedIn' => false,
                'start' => null,
                'end' => null,
            ];
            $this->todaysTimeEntries = [];
            $this->upcomingLeaveId = null;
        }
    }

    #[Computed]
    public function user(): User
    {
        // Only accesses do_not_track attribute, no relationships needed
        return User::findOrFail($this->userId);
    }

    #[Computed]
    public function upcomingLeave(): ?UserLeave
    {
        return $this->upcomingLeaveId ? UserLeave::find($this->upcomingLeaveId) : null;
    }

    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.dashboard.user-dashboard-widgets-skeleton');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.dashboard.user-dashboard-widgets');
    }
}
