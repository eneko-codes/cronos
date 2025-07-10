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

    public string $todaysLoggedTime = '0h 0m'; // Default value

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
                $details = $schedule->scheduleDetails()->where('weekday', $todayWeekday)->get();
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

            // Today's Attendance (simplified logic)
            $attendance = UserAttendance::where('user_id', $user->id)
                ->whereDate('date', now()->toDateString())
                ->first();
            if (! $attendance) {
                $this->todaysAttendance = [
                    'status' => 'Not Clocked In',
                    'duration' => '0h 0m',
                    'is_remote' => false,
                    'clockedIn' => false,
                    'start' => null,
                    'end' => null,
                ];
            } elseif ($attendance->is_remote) {
                $durationMinutes = (int) (($attendance->presence_seconds ?? 0) / 60);
                $this->todaysAttendance = [
                    'status' => 'Remote',
                    'duration' => \Carbon\CarbonInterval::minutes($durationMinutes)->cascade()->format('%hh %dm'),
                    'is_remote' => true,
                    'clockedIn' => false,
                    'start' => null,
                    'end' => null,
                ];
            } else {
                $durationMinutes = 0;
                $clockedIn = false;
                $start = $attendance->start ? $attendance->start->toDateTimeString() : null;
                $end = $attendance->end ? $attendance->end->toDateTimeString() : null;
                if ($attendance->start && $attendance->end) {
                    $durationMinutes = $attendance->start->diffInMinutes($attendance->end);
                } elseif ($attendance->start) {
                    $durationMinutes = (int) (($attendance->presence_seconds ?? 0) / 60);
                    $clockedIn = true;
                }
                $this->todaysAttendance = [
                    'status' => 'In Office',
                    'duration' => \Carbon\CarbonInterval::minutes($durationMinutes)->cascade()->format('%hh %dm'),
                    'is_remote' => false,
                    'clockedIn' => $clockedIn,
                    'start' => $start,
                    'end' => $end,
                ];
            }

            // Today's Logged Time
            $seconds = TimeEntry::where('user_id', $user->id)
                ->whereDate('date', now()->toDateString())
                ->sum('duration_seconds');
            $this->todaysLoggedTime = $seconds > 0
                ? \Carbon\CarbonInterval::seconds($seconds)->cascade()->format('%hh %mm')
                : '0h 0m';

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
            $this->todaysLoggedTime = '0h 0m';
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
