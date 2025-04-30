<?php

namespace App\Livewire;

use App\Models\Schedule;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Schedule Details')]
class ScheduleDetailView extends Component
{
    public Schedule $schedule;

    // State for section visibility
    public bool $showScheduleDetails = true;

    public bool $showAssignedUsers = true;

    // Property to hold unique user schedules for display
    public Collection $uniqueUserSchedules;

    /**
     * Mount the component and load the schedule.
     *
     * Uses route model binding to automatically fetch the Schedule model.
     */
    public function mount(Schedule $schedule)
    {
        // Eager load necessary relationships for the detail view
        $this->schedule = $schedule->load(['scheduleDetails', 'userSchedules.user']);

        // Calculate unique user schedules here and store as public property
        $this->uniqueUserSchedules = $this->schedule->userSchedules->unique('user_id');
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
     * Toggle visibility of the Assigned Users section.
     */
    public function toggleAssignedUsers(): void
    {
        $this->showAssignedUsers = ! $this->showAssignedUsers;
    }

    /**
     * Render the component view.
     */
    public function render()
    {
        // Group user schedules by user ID for cleaner display - REMOVED calculation from here
        // $uniqueUserSchedules = $this->schedule->userSchedules->unique('user_id');

        // Now just return the view, accessing $uniqueUserSchedules via public property
        return view('livewire.schedule-detail-view');
        // REMOVED passing data explicitly:
        // [
        //     'uniqueUserSchedules' => $uniqueUserSchedules,
        // ]);
    }
}
