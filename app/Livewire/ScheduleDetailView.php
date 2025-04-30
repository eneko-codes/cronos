<?php

namespace App\Livewire;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Title;
use Livewire\Component; // Import base collection

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

    // Property to hold schedule details grouped and sorted by weekday (Mon-Sun)
    public SupportCollection $groupedScheduleDetails;

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
            return ! is_null($us->effective_until) && $us->effective_until < $now;
        });

        // Get unique users currently assigned
        $this->currentUserSchedules = $currentAssignments->unique('user_id');

        // Get unique users who were previously assigned BUT are NOT currently assigned
        $currentlyAssignedUserIds = $this->currentUserSchedules->pluck('user_id');
        $this->pastUserSchedules = $pastAssignments->whereNotIn('user_id', $currentlyAssignedUserIds)->unique('user_id');

        // Group and sort schedule details for the view
        $grouped = $this->schedule->scheduleDetails
            ->sortBy('start') // Sort by start time within each day
            ->groupBy('weekday');

        // --- Detect and mark duplicates within each day/period group ---
        foreach ($grouped as $weekday => $weekdayDetails) {
            $periodGroups = $weekdayDetails->groupBy('day_period');
            foreach ($periodGroups as $period => $periodDetails) {
                if ($periodDetails->count() > 1) {
                    // Mark all details in this specific group as having duplicates
                    foreach ($periodDetails as $detail) {
                        $detail->has_duplicates = true; // Add temporary property to the model instance
                    }
                }
            }
        }
        // --- End duplicate detection ---

        // Custom sort keys: Mon (1) to Sun (0)
        $this->groupedScheduleDetails = $grouped->sortBy(function ($group, $weekday) {
            // Map weekday to sort order: 1 (Mon) -> 1, ..., 6 (Sat) -> 6, 0 (Sun) -> 7
            return ($weekday == 0) ? 7 : $weekday;
        })
        // Convert inner Eloquent Collections to base SupportCollections of stdClass objects
            ->map(function ($details) {
                // Convert each detail model, explicitly adding has_duplicates if it was set
                return collect($details->map(function ($model) {
                    $data = $model->toArray(); // Convert base attributes
                    // Check the original model instance for the flag and add it to the array
                    $data['has_duplicates'] = (isset($model->has_duplicates) && $model->has_duplicates);

                    return (object) $data; // Return as stdClass object
                })->all());
            });
    }

    /**
     * Define the title for the page.
     *
     * Computed property ensures the title updates if the schedule description changes.
     */
    #[Title('Schedule: ')]
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
        return view('livewire.schedule-detail-view');

    }
}
