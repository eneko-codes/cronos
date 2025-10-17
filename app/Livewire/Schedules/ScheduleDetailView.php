<?php

declare(strict_types=1);

namespace App\Livewire\Schedules;

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

        // Ensure user relationship is loaded to prevent lazy loading violations
        /** @var \Illuminate\Database\Eloquent\Collection<int, UserSchedule> $allUserSchedules */
        $allUserSchedules = UserSchedule::with('user')
            ->where('odoo_schedule_id', $this->schedule->odoo_schedule_id)
            ->get();

        // Filter for assignments active now or in the future
        $currentAssignments = $allUserSchedules->filter(function (UserSchedule $us) use ($now) {
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

        // Ensure user relationship is loaded to prevent lazy loading violations
        /** @var \Illuminate\Database\Eloquent\Collection<int, UserSchedule> $allUserSchedules */
        $allUserSchedules = UserSchedule::with('user')
            ->where('odoo_schedule_id', $this->schedule->odoo_schedule_id)
            ->get();

        // Filter for assignments that ended in the past
        $pastAssignments = $allUserSchedules->filter(function (UserSchedule $us) use ($now) {
            return ! is_null($us->effective_until) && $us->effective_until < $now;
        });

        // Get unique users who were previously assigned BUT are NOT currently assigned
        $currentlyAssignedUserIds = $this->currentUserSchedules()->pluck('user_id'); // Use the computed property

        return $pastAssignments->whereNotIn('user_id', $currentlyAssignedUserIds)->unique('user_id');
    }

    /**
     * Get schedule details grouped by weekday, sorted, and converted to stdClass objects including a duration string.
     */
    #[Computed]
    public function groupedScheduleDetails(): SupportCollection
    {
        $today = now()->toDateString();

        $grouped = $this->schedule->scheduleDetails
            ->sortBy('start') // Sort by start time within each day
            ->groupBy('weekday');

        // Custom sort keys: Mon (1) to Sun (0)
        return $grouped->sortBy(function ($group, $weekday) {
            // Map weekday to sort order: 1 (Mon) -> 1, ..., 6 (Sat) -> 6, 0 (Sun) -> 7
            return ($weekday == 0) ? 7 : $weekday;
        })
        // Convert inner Eloquent Collections to base SupportCollections of stdClass objects
            ->map(function ($details) use ($today) {
                // Convert each detail model with duration string and status
                return collect($details->map(function ($model) use ($today) {
                    $data = $model->toArray(); // Convert base attributes

                    // Determine status based on active flag and date range
                    $data['status'] = $this->determineScheduleDetailStatus($model, $today);
                    $data['status_label'] = $this->getStatusLabel($data['status']);
                    $data['status_classes'] = $this->getStatusClasses($data['status']);

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
     * Determine the status of a schedule detail based on active flag and date range.
     */
    private function determineScheduleDetailStatus($detail, string $today): string
    {
        // First check if it's active
        if ($detail->active === false) {
            return 'inactive';
        }

        // Get date range
        $dateFrom = $detail->date_from ? $detail->date_from->toDateString() : null;
        $dateTo = $detail->date_to ? $detail->date_to->toDateString() : null;

        // If no date range specified, it's always active (if active flag is true)
        if (! $dateFrom && ! $dateTo) {
            return $detail->active === true ? 'active' : 'inactive';
        }

        // Check date range
        $afterStart = ! $dateFrom || $today >= $dateFrom;
        $beforeEnd = ! $dateTo || $today <= $dateTo;

        if ($afterStart && $beforeEnd) {
            return $detail->active === true ? 'active' : 'inactive';
        } elseif (! $afterStart) {
            return 'future'; // Not yet started
        } else {
            return 'historical'; // Has ended
        }
    }

    /**
     * Get human-readable status label.
     */
    private function getStatusLabel(string $status): string
    {
        return match ($status) {
            'active' => 'Currently Active',
            'future' => 'Future Schedule',
            'historical' => 'Historical/Expired',
            'inactive' => 'Inactive',
            default => 'Unknown',
        };
    }

    /**
     * Get CSS classes for status styling.
     */
    private function getStatusClasses(string $status): string
    {
        return match ($status) {
            'active' => 'border-green-500 bg-green-50 dark:bg-gray-700 dark:border-green-500 dark:border-l-4 dark:border-l-green-500',
            'future' => 'border-yellow-500 bg-yellow-50 dark:bg-gray-700 dark:border-yellow-500 dark:border-l-4 dark:border-l-yellow-500',
            'historical' => 'border-orange-400 bg-orange-50 dark:bg-gray-700 dark:border-orange-500 dark:border-l-4 dark:border-l-orange-500',
            'inactive' => 'border-gray-400 bg-gray-100 dark:bg-gray-800 dark:border-gray-500 opacity-75',
            default => 'border-gray-300 bg-gray-50 dark:bg-gray-700 dark:border-gray-500',
        };
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
        return view('livewire.schedules.schedule-detail-view');
    }
}
