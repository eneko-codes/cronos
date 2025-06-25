<?php

declare(strict_types=1);

namespace App\Livewire;

use App\DataTransferObjects\TodaysAttendanceData;
use App\DataTransferObjects\TodaysScheduleData;
use App\Models\User;
use App\Models\UserLeave;
use App\Queries\GetTodaysAttendanceQuery as QueryGetTodaysAttendanceQuery;
use App\Queries\GetTodaysLoggedTimeQuery as QueryGetTodaysLoggedTimeQuery;
use App\Queries\GetTodaysScheduleQuery as QueryGetTodaysScheduleQuery;
use App\Queries\GetUpcomingLeaveQuery as QueryGetUpcomingLeaveQuery;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class UserDashboardWidgets extends Component
{
    public int $userId;

    public ?TodaysScheduleData $todaysSchedule = null;

    public ?TodaysAttendanceData $todaysAttendance = null;

    public string $todaysLoggedTime = '0h 0m'; // Default value

    public ?int $upcomingLeaveId = null;

    public function mount(
        User $user,
        QueryGetTodaysScheduleQuery $getTodaysScheduleQuery,
        QueryGetTodaysAttendanceQuery $getTodaysAttendanceQuery,
        QueryGetTodaysLoggedTimeQuery $getTodaysLoggedTimeQuery,
        QueryGetUpcomingLeaveQuery $getUpcomingLeaveQuery
    ): void {
        $this->userId = $user->id;

        if (! $user->do_not_track) {
            $this->todaysSchedule = $getTodaysScheduleQuery->execute($user);
            $this->todaysAttendance = $getTodaysAttendanceQuery->execute($user);
            $this->todaysLoggedTime = $getTodaysLoggedTimeQuery->execute($user);
            $upcomingLeave = $getUpcomingLeaveQuery->execute($user);
            $this->upcomingLeaveId = $upcomingLeave?->id;
        } else {
            // Set defaults for do_not_track users, TodaysAttendanceAction already returns a default DTO
            $this->todaysSchedule = null; // Or a default DTO if preferred
            $this->todaysAttendance = $getTodaysAttendanceQuery->execute($user); // Will return 'Not Tracked' DTO
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
