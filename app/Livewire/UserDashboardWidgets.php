<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\User\GetTodaysAttendanceAction;
use App\Actions\User\GetTodaysLoggedTimeAction;
use App\Actions\User\GetTodaysScheduleAction;
use App\Actions\User\GetUpcomingLeaveAction;
use App\DataTransferObjects\TodaysAttendanceData;
use App\DataTransferObjects\TodaysScheduleData;
use App\Models\User;
use App\Models\UserLeave;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class UserDashboardWidgets extends Component
{
    public User $user;

    public ?TodaysScheduleData $todaysSchedule = null;

    public ?TodaysAttendanceData $todaysAttendance = null;

    public string $todaysLoggedTime = '0h 0m'; // Default value

    public ?UserLeave $upcomingLeave = null;

    public function mount(
        User $user,
        GetTodaysScheduleAction $getTodaysScheduleAction,
        GetTodaysAttendanceAction $getTodaysAttendanceAction,
        GetTodaysLoggedTimeAction $getTodaysLoggedTimeAction,
        GetUpcomingLeaveAction $getUpcomingLeaveAction
    ): void {
        $this->user = $user;

        if (! $this->user->do_not_track) {
            $this->todaysSchedule = $getTodaysScheduleAction->execute($this->user);
            $this->todaysAttendance = $getTodaysAttendanceAction->execute($this->user);
            $this->todaysLoggedTime = $getTodaysLoggedTimeAction->execute($this->user);
            $this->upcomingLeave = $getUpcomingLeaveAction->execute($this->user);
        } else {
            // Set defaults for do_not_track users, TodaysAttendanceAction already returns a default DTO
            $this->todaysSchedule = null; // Or a default DTO if preferred
            $this->todaysAttendance = $getTodaysAttendanceAction->execute($this->user); // Will return 'Not Tracked' DTO
            $this->todaysLoggedTime = '0h 0m';
            $this->upcomingLeave = null;
        }
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
