<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Models\User;
use App\Models\UserAttendance;
use App\Models\UserLeave;
use App\Models\UserSchedule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class UserDashboardWidgets extends Component
{
    public int $userId;

    public ?array $todaysSchedule = null;

    public ?array $todaysAttendance = null;

    public array $todaysTimeEntries = []; // Array of today's time entries

    public ?int $upcomingLeaveId = null;

    public function mount(User $user): void
    {
        $this->userId = $user->id;

        if (! $user->do_not_track) {
            // Today's Schedule
            $userSchedule = UserSchedule::where('user_id', $user->id)
                ->where('effective_from', '<=', now()->toDateString())
                ->where(function ($q): void {
                    $q->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', now()->toDateString());
                })
                ->latest('effective_from')
                ->first();
            $schedule = $userSchedule?->schedule;
            if ($schedule) {
                $todayWeekday = now()->dayOfWeekIso; // 1 (Mon) - 7 (Sun)
                $today = now()->toDateString();

                // Filter schedule details to only include explicitly active ones for today
                $details = $schedule->scheduleDetails()
                    ->where('weekday', $todayWeekday)
                    ->where(function ($query) use ($today): void {
                        $query->where('active', true)
                            ->where(function ($q) use ($today): void {
                                $q->where(function ($subQ): void {
                                    // Either no date range specified (applies to all dates)
                                    $subQ->whereNull('date_from')
                                        ->whereNull('date_to');
                                })
                                    ->orWhere(function ($subQ) use ($today): void {
                                        // Or specified date is within the range
                                        $subQ->where(function ($dateFromQ) use ($today): void {
                                            $dateFromQ->whereNull('date_from')
                                                ->orWhere('date_from', '<=', $today);
                                        })
                                            ->where(function ($dateToQ) use ($today): void {
                                                $dateToQ->whereNull('date_to')
                                                    ->orWhere('date_to', '>=', $today);
                                            });
                                    });
                            });
                    })
                    ->get();

                $totalMinutes = 0;
                $slots = [];
                foreach ($details as $detail) {
                    if ($detail->start && $detail->end) {
                        $totalMinutes += $detail->start->diffInMinutes($detail->end);
                        $slots[] = [
                            'start' => $detail->start->format('H:i'),
                            'end' => $detail->end->format('H:i'),
                        ];
                    }
                }
                $duration = $totalMinutes > 0
                    ? \Carbon\CarbonInterval::minutes($totalMinutes)->cascade()->format('%hh %mm')
                    : '0h 0m';
                $this->todaysSchedule = [
                    'name' => $schedule->description,
                    'duration' => $duration,
                    'slots' => $slots,
                ];
            } else {
                $this->todaysSchedule = null;
            }

            // Today's Attendance (handles multiple segments)
            $attendances = UserAttendance::where('user_id', $user->id)
                ->whereDate('date', now()->toDateString())
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
                $totalSeconds = $attendances->sum('duration_seconds');
                $durationMinutes = (int) ($totalSeconds / 60);

                // Get first clock-in and last clock-out
                $firstClockIn = $attendances->whereNotNull('clock_in')->min('clock_in');
                $lastClockOut = $attendances->whereNotNull('clock_out')->max('clock_out');

                // Check if currently clocked in (has clock_in but no clock_out in latest segment)
                $latestSegment = $attendances->sortByDesc('clock_in')->first();
                $clockedIn = $latestSegment && $latestSegment->clock_in && ! $latestSegment->clock_out;

                // Build segments array
                $segments = $attendances->map(function ($attendance) {
                    return [
                        'clock_in' => $attendance->clock_in ? $attendance->clock_in->format('H:i') : null,
                        'clock_out' => $attendance->clock_out ? $attendance->clock_out->format('H:i') : null,
                        'duration' => \Carbon\CarbonInterval::seconds((int) $attendance->duration_seconds)
                            ->cascade()
                            ->format('%hh %Im'),
                    ];
                })->toArray();

                $this->todaysAttendance = [
                    'status' => $isRemote ? 'Remote' : 'In Office',
                    'duration' => \Carbon\CarbonInterval::minutes($durationMinutes)->cascade()->format('%hh %Im'),
                    'is_remote' => $isRemote,
                    'clockedIn' => $clockedIn,
                    'start' => $firstClockIn ? $firstClockIn->toDateTimeString() : null,
                    'end' => $lastClockOut ? $lastClockOut->toDateTimeString() : null,
                    'segments' => $segments,
                ];
            }

            // Today's Time Entries
            $timeEntries = TimeEntry::where('user_id', $user->id)
                ->whereDate('date', now()->toDateString())
                ->with(['project', 'task'])
                ->orderBy('proofhub_created_at')
                ->get();

            $this->todaysTimeEntries = $timeEntries->map(function ($entry) {
                $hours = floor($entry->duration_seconds / 3600);
                $minutes = floor(($entry->duration_seconds % 3600) / 60);
                $duration = $hours.'h '.$minutes.'m';

                return [
                    'duration' => $duration,
                    'duration_seconds' => $entry->duration_seconds,
                    'description' => $entry->description,
                    'project_name' => $entry->project->title ?? 'Unknown Project',
                    'task_name' => $entry->task->name ?? null,
                    'status' => $entry->status,
                ];
            })->toArray();

            // Upcoming Leave
            $upcomingLeave = UserLeave::where('user_id', $user->id)
                ->where('status', 'validate')
                ->where('start_date', '>=', now()->toDateString())
                ->orderBy('start_date')
                ->first();
            $this->upcomingLeaveId = $upcomingLeave?->id;
        } else {
            $this->todaysSchedule = null;
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
        return User::findOrFail($this->userId);
    }

    #[Computed]
    public function upcomingLeave(): ?UserLeave
    {
        return $this->upcomingLeaveId ? UserLeave::find($this->upcomingLeaveId) : null;
    }

    public function placeholder(array $params = []): \Illuminate\Contracts\View\View
    {
        return view('livewire.placeholders.user-dashboard-widgets-skeleton');
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('livewire.user-dashboard-widgets');
    }
}
