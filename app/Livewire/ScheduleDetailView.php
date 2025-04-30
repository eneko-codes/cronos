<?php

namespace App\Livewire;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Schedule Details')]
class ScheduleDetailView extends Component
{
    public Schedule $schedule;

    // State for section visibility
    public bool $showScheduleDetails = true;
    // State for currently assigned users
    public bool $showCurrentlyAssigned = true; 
    // State for previously assigned users
    public bool $showPreviouslyAssigned = true;

    // Properties to hold current and past user schedules
    public Collection $currentUserSchedules;
    public Collection $pastUserSchedules;

    /**
     * Mount the component and load the schedule.
     *
     * Uses route model binding to automatically fetch the Schedule model.
     */
    public function mount(Schedule $schedule)
    {
        // Eager load necessary relationships for the detail view
        $this->schedule = $schedule->load(['scheduleDetails', 'userSchedules.user']);

        // Calculate current and past user schedules
        $now = Carbon::now();
        $allUserSchedules = $this->schedule->userSchedules; // Already loaded with user

        // Filter for assignments active now or in the future
        $currentAssignments = $allUserSchedules->filter(function ($us) use ($now) {
            return is_null($us->effective_until) || $us->effective_until >= $now;
        });

        // Filter for assignments that ended in the past
        $pastAssignments = $allUserSchedules->filter(function ($us) use ($now) {
            return !is_null($us->effective_until) && $us->effective_until < $now;
        });

        // Get unique users currently assigned
        $this->currentUserSchedules = $currentAssignments->unique('user_id');

        // Get unique users who were previously assigned BUT are NOT currently assigned
        $currentlyAssignedUserIds = $this->currentUserSchedules->pluck('user_id');
        $this->pastUserSchedules = $pastAssignments->whereNotIn('user_id', $currentlyAssignedUserIds)->unique('user_id');
    }

    /**
     * Define the title for the page.
     *
     * Computed property ensures the title updates if the schedule description changes.
     */
    #[Title('Schedule: ')] // Placeholder, will be completed by computed property
    public function title(): string
    {
        return 'Schedule: '.($this->schedule->description ?? $this->schedule->odoo_schedule_id);
    }

    /**
     * Toggle visibility of the Schedule Details section.
     */
    public function toggleScheduleDetails(): void
    {
        $this->showScheduleDetails = ! $this->showScheduleDetails;
    }

    /**
     * Toggle visibility of the Currently Assigned Users section.
     */
    public function toggleCurrentlyAssigned(): void
    {
        $this->showCurrentlyAssigned = ! $this->showCurrentlyAssigned;
    }

    /**
     * Toggle visibility of the Previously Assigned Users section.
     */
    public function togglePreviouslyAssigned(): void
    {
        $this->showPreviouslyAssigned = ! $this->showPreviouslyAssigned;
    }

    /**
     * Render the component view.
     */
    public function render()
    {
        // Group user schedules by user ID for cleaner display - REMOVED calculation from here
        // $uniqueUserSchedules = $this->schedule->userSchedules->unique('user_id');

        // Now just return the view, accessing computed properties directly
        return view('livewire.schedule-detail-view');
        // REMOVED passing data explicitly:
        // [
        //     'uniqueUserSchedules' => $uniqueUserSchedules,
        // ]);
    }
}
