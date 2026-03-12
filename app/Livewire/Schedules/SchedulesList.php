<?php

declare(strict_types=1);

namespace App\Livewire\Schedules;

use App\Models\Schedule;
use App\Models\UserSchedule;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Schedules')]
#[Lazy]
class SchedulesList extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 25)]
    public int $itemsPerPage = 25;

    #[Url(except: 'description_asc')]
    public string $sortBy = 'description_asc'; // Default sort

    // Filters array
    public array $filters = [
        'has_details' => false,
        'has_no_details' => false,
        'has_assigned_users' => false,
        'has_no_assigned_users' => false,
    ];

    /**
     * Query string configuration for nested filters with aliases.
     */
    protected $queryString = [
        'filters.has_details' => ['as' => 'f_hd', 'except' => false],
        'filters.has_no_details' => ['as' => 'f_hnd', 'except' => false],
        'filters.has_assigned_users' => ['as' => 'f_hau', 'except' => false],
        'filters.has_no_assigned_users' => ['as' => 'f_hnau', 'except' => false],
    ];

    /**
     * Reset page when search query changes.
     */
    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    /**
     * Reset page when filters change.
     */
    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    /**
     * Reset page when sort order changes.
     */
    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    /**
     * Render the component.
     */
    public function render()
    {
        $now = now(); // Get current time once

        $applyFiltersAndSorting = function ($query) use ($now): void {
            // Apply Filters
            $query->when($this->filters['has_details'], function ($q): void {
                $q->has('scheduleDetails');
            })
                ->when($this->filters['has_no_details'], function ($q): void {
                    $q->doesntHave('scheduleDetails');
                })
                ->when($this->filters['has_assigned_users'], function ($q) use ($now): void {
                    $q->whereHas('userSchedules', function (\Illuminate\Database\Eloquent\Builder $subQuery) use ($now): void {
                        /** @var \Illuminate\Database\Eloquent\Builder<UserSchedule> $subQuery */
                        $subQuery->effectiveAt($now);
                    });
                })
                ->when($this->filters['has_no_assigned_users'], function ($q) use ($now): void {
                    $q->whereDoesntHave('userSchedules', function (\Illuminate\Database\Eloquent\Builder $subQuery) use ($now): void {
                        /** @var \Illuminate\Database\Eloquent\Builder<UserSchedule> $subQuery */
                        $subQuery->effectiveAt($now);
                    });
                })
                // Apply Sorting
                ->when($this->sortBy, function ($q): void {
                    match ($this->sortBy) {
                        'description_asc' => $q->orderBy('description', 'asc'),
                        'description_desc' => $q->orderBy('description', 'desc'),
                        'users_count_desc' => $q->orderBy('current_users_count', 'desc'),
                        'users_count_asc' => $q->orderBy('current_users_count', 'asc'),
                        'created_at_desc' => $q->orderBy('created_at', 'desc'),
                        'created_at_asc' => $q->orderBy('created_at', 'asc'),
                        'updated_at_desc' => $q->orderBy('updated_at', 'desc'),
                        'updated_at_asc' => $q->orderBy('updated_at', 'asc'),
                        default => $q->orderBy('description', 'asc'), // Fallback default
                    };
                });
        };

        $withCountCallback = function ($query) use ($now): void {
            $query->withCount([
                'userSchedules as current_users_count' => function (\Illuminate\Database\Eloquent\Builder $subQuery) use ($now): void {
                    /** @var \Illuminate\Database\Eloquent\Builder<UserSchedule> $subQuery */
                    $subQuery->effectiveAt($now);
                },
            ]);
        };

        // Apply search using Scout
        if ($this->search) {
            $schedules = Schedule::search($this->search)
                ->query(function ($q) use ($withCountCallback, $applyFiltersAndSorting): void {
                    $withCountCallback($q);
                    $applyFiltersAndSorting($q);
                })
                ->paginate($this->itemsPerPage);
        } else {
            $schedules = Schedule::query()
                ->tap($withCountCallback)
                ->tap($applyFiltersAndSorting)
                ->paginate($this->itemsPerPage);
        }

        return view('livewire.schedules.schedules-list', [
            'schedules' => $schedules,
        ]);
    }

    /**
     * Render a skeleton placeholder while the schedules list is loading.
     * This provides a visual indication that the schedules data is being fetched.
     *
     * @return \Illuminate\View\View
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.schedules.schedules-list-skeleton', $params);
    }
}
