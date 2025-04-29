<?php

namespace App\Livewire;

use App\Models\Schedule;
use Livewire\Attributes\Title;
use Livewire\Component;

class ScheduleDetailView extends Component
{
    public Schedule $schedule;

    /**
     * Mount the component and load the schedule.
     *
     * Uses route model binding to automatically fetch the Schedule model.
     */
    public function mount(Schedule $schedule)
    {
        // Eager load necessary relationships for the detail view
        $this->schedule = $schedule->load(['scheduleDetails', 'userSchedules.user']);
    }

    /**
     * Define the title for the page.
     *
     * Computed property ensures the title updates if the schedule description changes.
     */
    #[Title('Schedule: ')] // Placeholder, will be completed by computed property
    public function title(): string
    {
        return 'Schedule: ' . ($this->schedule->description ?? $this->schedule->odoo_schedule_id);
    }

    /**
     * Render the component view.
     */
    public function render()
    {
        // Group user schedules by user ID for cleaner display
        $uniqueUserSchedules = $this->schedule->userSchedules->unique('user_id');

        return view('livewire.schedule-detail-view', [
            'uniqueUserSchedules' => $uniqueUserSchedules,
        ]);
    }
}
