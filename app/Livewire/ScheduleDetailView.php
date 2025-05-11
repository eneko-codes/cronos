<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Schedule;
use App\Models\UserSchedule;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Lazy]
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

    /**
     * Mount the component and load the schedule with relationships.
     *
     * Uses route model binding to automatically fetch the Schedule model.
     */
    public function mount(Schedule $schedule): void
    {
        $this->schedule = $schedule->loadMissing(['scheduleDetails', 'userSchedules.user']);
    }

    /**
     * Get the currently assigned user schedules (unique users).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, UserSchedule>
     */
    #[Computed]
    public function currentUserSchedules(): Collection
    {
        $now = Carbon::now();
        $allUserSchedules = $this->schedule->userSchedules; // Already loaded in mount

        // Filter for assignments active now or in the future
        $currentAssignments = $allUserSchedules->filter(function ($us) use ($now) {
            return is_null($us->effective_until) || $us->effective_until >= $now;
        });

        // Get unique users currently assigned
        return $currentAssignments->unique('user_id');
    }

    /**
     * Get the previously assigned user schedules (unique users, not currently assigned).
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, UserSchedule>
     */
    #[Computed]
    public function pastUserSchedules(): Collection
    {
        $now = Carbon::now();
        $allUserSchedules = $this->schedule->userSchedules; // Already loaded in mount

        // Filter for assignments that ended in the past
        $pastAssignments = $allUserSchedules->filter(function ($us) use ($now) {
            return ! is_null($us->effective_until) && $us->effective_until < $now;
        });

        // Get unique users who were previously assigned BUT are NOT currently assigned
        $currentlyAssignedUserIds = $this->currentUserSchedules()->pluck('user_id'); // Use the computed property

        return $pastAssignments->whereNotIn('user_id', $currentlyAssignedUserIds)->unique('user_id');
    }

    /**
     * Get schedule details grouped by weekday, sorted, with duplicates marked,
     * and converted to stdClass objects including a duration string.
     */
    #[Computed]
    public function groupedScheduleDetails(): SupportCollection
    {
        // Create a mutable copy for marking duplicates without affecting original models
        $detailsCopy = $this->schedule->scheduleDetails->map(function ($detail) {
            $clone = $detail->replicate(); // Use replicate or newFromBuilder if needed
            $clone->id = $detail->id; // Ensure ID is copied if replicate doesn't handle it well
            $clone->has_duplicates = false; // Initialize flag

            return $clone;
        });

        $grouped = $detailsCopy
            ->sortBy('start') // Sort by start time within each day
            ->groupBy('weekday');

        // Detect and mark duplicates within each day/period group
        foreach ($grouped as $weekday => $weekdayDetails) {
            $periodGroups = $weekdayDetails->groupBy('day_period');
            foreach ($periodGroups as $periodDetails) {
                if ($periodDetails->count() > 1) {
                    // Mark all details in this specific group as having duplicates
                    foreach ($periodDetails as $detail) {
                        $detail->has_duplicates = true; // Mark the copied instance
                    }
                }
            }
        }

        // Custom sort keys: Mon (1) to Sun (0)
        return $grouped->sortBy(function ($group, $weekday) {
            // Map weekday to sort order: 1 (Mon) -> 1, ..., 6 (Sat) -> 6, 0 (Sun) -> 7
            return ($weekday == 0) ? 7 : $weekday;
        })
        // Convert inner Eloquent Collections to base SupportCollections of stdClass objects
            ->map(function ($details) {
                // Convert each detail model, explicitly adding has_duplicates and duration_string
                return collect($details->map(function ($model) {
                    $data = $model->toArray(); // Convert base attributes
                    $data['has_duplicates'] = $model->has_duplicates; // Use the flag from the copied instance

                    // Calculate duration string
                    try {
                        $startCarbon = Carbon::parse($model->start);
                        $endCarbon = Carbon::parse($model->end);
                        $interval = $startCarbon->diff($endCarbon);
                        $durationParts = [];
                        if ($interval->h > 0) {
                            $durationParts[] = $interval->h.' hour'.($interval->h > 1 ? 's' : '');
                        }
                        if ($interval->i > 0) {
                            $durationParts[] = $interval->i.' minute'.($interval->i > 1 ? 's' : '');
                        }
                        $durationString = implode(' ', $durationParts);
                        if (empty($durationString)) {
                            $durationString = '0 minutes';
                        }
                        $data['duration_string'] = $durationString;
                    } catch (\Exception $e) {
                        // Handle potential parsing errors if start/end are not valid time strings
                        $data['duration_string'] = 'Error calculating';
                        // Log::error("Error parsing schedule detail time: " . $e->getMessage(), ['detail_id' => $model->id]);
                    }

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
     * Render a skeleton placeholder while the schedule details are loading.
     * This provides a visual indication that the schedule data is being fetched.
     *
     * @return \Illuminate\View\View
     */
    /*
    public function placeholder()
    {
        return view('livewire.placeholders.schedule-detail-view');
    }*/

    /**
     * Render the component view.
     */
    public function render()
    {
        return view('livewire.schedule-detail-view');
    }
}
