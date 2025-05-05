<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\TimeEntry;
use App\Models\UserAttendance;
use App\Models\UserLeave;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class UserDashboardWidgets extends Component
{
    public ?array $todaysSchedule = null;

    public ?array $todaysAttendance = null;

    public ?string $todaysLoggedTime = '0h 0m';

    public ?UserLeave $upcomingLeave = null;

    // Reusable helper from UserDashboard, consider refactoring to a Trait or Service if used more widely
    protected function formatMinutesToHoursMinutes(float $minutes): string
    {
        if ($minutes <= 0) { // Handle zero or negative explicitly
            return '0h 0m';
        }
        $totalHours = floor($minutes / 60);
        $remainingMinutes = $minutes % 60;

        return sprintf('%dh %dm', $totalHours, $remainingMinutes);
    }

    protected function durationToMinutes(string $duration): int
    {
        $hours = 0;
        $minutes = 0;
        sscanf($duration, '%dh %dm', $hours, $minutes);
        $hours = (int) $hours;
        $minutes = (int) $minutes;

        return $hours * 60 + $minutes;
    }

    public function mount()
    {
        $user = Auth::user();
        $today = Carbon::today();
        $weekday = ($today->dayOfWeek + 6) % 7; // 0=Monday, ..., 6=Sunday

        // 1. Today's Schedule - Simplified Eager Loading
        $activeSchedule = UserSchedule::where('user_id', $user->id)
            ->where('effective_from', '<=', $today)
            ->where(function ($query) use ($today): void {
                $query->whereNull('effective_until')
                    ->orWhere('effective_until', '>=', $today);
            })
            ->with('schedule:odoo_schedule_id,description') // Select only existing columns, ensuring the owner key 'odoo_schedule_id' is present
            ->first();

        // Check if schedule loaded (Linter fix: simplified condition)
        if ($activeSchedule) {
            // Since we eager-loaded schedule, we can assume it exists if $activeSchedule exists
            $schedule = $activeSchedule->schedule; // Get the loaded schedule

            // Now load the details for this specific schedule and weekday
            $details = $schedule->scheduleDetails()->where('weekday', $weekday)->get();

            $totalMinutes = 0;
            if ($details->isNotEmpty()) {
                /** @var \App\Models\ScheduleDetail $detail */
                foreach ($details->sortBy('start') as $detail) {
                    $start = Carbon::parse($detail->start)->setTimezone('UTC');
                    $end = Carbon::parse($detail->end)->setTimezone('UTC');
                    $minutesForSlot = $start->diffInMinutes($end);
                    $totalMinutes += $minutesForSlot;
                }
            }

            // Set schedule data
            $this->todaysSchedule = [
                'duration' => $this->formatMinutesToHoursMinutes($totalMinutes),
                'name' => $schedule->description ?? 'Default Schedule',
            ];
        }
        // If no active schedule or schedule relationship found, $this->todaysSchedule remains null

        // 2. Today's Attendance
        $attendance = UserAttendance::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->first();

        if ($attendance) {
            $status = 'Unknown';
            $timeInfo = null;
            $durationMinutes = 0;

            if ($attendance->is_remote) {
                $status = 'Remote';
                $durationMinutes = (int) (($attendance->presence_seconds ?? 0) / 60);
            } elseif ($attendance->start && $attendance->end) {
                $status = 'In Office - Clocked Out';
                $start = Carbon::parse($attendance->start);
                $end = Carbon::parse($attendance->end);
                $timeInfo = $start->format('H:i').' - '.$end->format('H:i');
                $durationMinutes = $start->diffInMinutes($end);
            } elseif ($attendance->start && ! $attendance->end) {
                $status = 'In Office - Clocked In';
                $start = Carbon::parse($attendance->start);
                $timeInfo = 'Since '.$start->format('H:i');
                $durationMinutes = (int) (($attendance->presence_seconds ?? 0) / 60);
            } elseif (! $attendance->start && ! $attendance->end && isset($attendance->presence_seconds) && $attendance->presence_seconds > 0) {
                // Fallback if only presence_seconds is available (maybe from DeskTime sync without clock-in/out?)
                $status = 'Present (System)';
                $durationMinutes = (int) ($attendance->presence_seconds / 60);
            } else {
                $status = 'No Activity Recorded';
            }

            $this->todaysAttendance = [
                'status' => $status,
                'time_info' => $timeInfo,
                'duration' => $this->formatMinutesToHoursMinutes($durationMinutes),
                'is_remote' => $attendance->is_remote,
                'clocked_in' => $attendance->start && ! $attendance->end, // Flag if currently clocked in
            ];
        } else {
            $this->todaysAttendance = ['status' => 'Not Clocked In', 'time_info' => null, 'duration' => '0h 0m', 'is_remote' => false, 'clocked_in' => false];
        }

        // 3. Today's Logged Time
        $totalSecondsToday = TimeEntry::where('user_id', $user->id)
            ->whereDate('date', $today)
            ->sum('duration_seconds');
        $loggedMinutes = $totalSecondsToday / 60;
        $this->todaysLoggedTime = $this->formatMinutesToHoursMinutes($loggedMinutes);

        // 4. Upcoming Leave (Next 30 days)
        $this->upcomingLeave = UserLeave::where('user_id', $user->id)
            ->where('status', 'validate') // Only approved leave
            ->where('end_date', '>=', $today) // End date is today or later
            ->where('start_date', '<=', $today->copy()->addDays(30)) // Start date within the next 30 days
            ->orderBy('start_date', 'asc')
            ->with('leaveType')
            ->first();
    }

    public function render()
    {
        return view('livewire.user-dashboard-widgets');
    }
}
