<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Schedule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Schedules')]
class SchedulesList extends Component
{
    use WithPagination;

    public int $itemsPerPage = 25;

    #[Url(except: '')]
    public string $search = '';

    // Filters array
    public array $filters = [
        'has_details' => false,
        'has_no_details' => false,
        'has_assigned_users' => false,
        'has_no_assigned_users' => false,
    ];

    // Sorting property
    public string $sortBy = 'description_asc'; // Default sort

    // Updated query string setup
    protected function queryString()
    {
        return [
            'search' => ['except' => ''],
            'page' => ['except' => 1],
            'sortBy' => ['except' => 'description_asc'], // Add sortBy
            'itemsPerPage' => ['except' => 25],
            // Add filters to query string with aliases
            'filters.has_details' => ['as' => 'f_hd', 'except' => false],
            'filters.has_no_details' => ['as' => 'f_hnd', 'except' => false],
            'filters.has_assigned_users' => ['as' => 'f_hau', 'except' => false],
            'filters.has_no_assigned_users' => ['as' => 'f_hnau', 'except' => false],
        ];
    }

    /**
     * Reset page when search query changes.
     */
    #[Url] // Keep this attribute for search updates
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

        $schedules = Schedule::query()
            ->withCount([
                'userSchedules as current_users_count' => function ($query) use ($now) {
                    $query->where(function ($subQuery) use ($now) {
                        $subQuery->whereNull('effective_until')
                            ->orWhere('effective_until', '>=', $now);
                    });
                },
            ])
            ->when($this->search, function ($query) {
                // Search only by schedule description
                $query->whereRaw('LOWER(description) LIKE ?', [
                    '%'.strtolower($this->search).'%',
                ]);
            })
            // Apply Filters
            ->when($this->filters['has_details'], function ($query) {
                $query->has('scheduleDetails');
            })
            ->when($this->filters['has_no_details'], function ($query) {
                $query->doesntHave('scheduleDetails');
            })
            ->when($this->filters['has_assigned_users'], function ($query) use ($now) {
                $query->whereHas('userSchedules', function ($subQuery) use ($now) {
                    $subQuery->where(function ($q) use ($now) {
                        $q->whereNull('effective_until')
                            ->orWhere('effective_until', '>=', $now);
                    });
                });
            })
            ->when($this->filters['has_no_assigned_users'], function ($query) use ($now) {
                $query->whereDoesntHave('userSchedules', function ($subQuery) use ($now) {
                    $subQuery->where(function ($q) use ($now) {
                        $q->whereNull('effective_until')
                            ->orWhere('effective_until', '>=', $now);
                    });
                });
            })
            // Apply Sorting
            ->when($this->sortBy, function ($query) {
                match ($this->sortBy) {
                    'description_asc' => $query->orderBy('description', 'asc'),
                    'description_desc' => $query->orderBy('description', 'desc'),
                    'users_count_desc' => $query->orderBy('current_users_count', 'desc'),
                    'users_count_asc' => $query->orderBy('current_users_count', 'asc'),
                    'created_at_desc' => $query->orderBy('created_at', 'desc'),
                    'created_at_asc' => $query->orderBy('created_at', 'asc'),
                    'updated_at_desc' => $query->orderBy('updated_at', 'desc'),
                    'updated_at_asc' => $query->orderBy('updated_at', 'asc'),
                    default => $query->orderBy('description', 'asc'), // Fallback default
                };
            })
            ->paginate($this->itemsPerPage);

        return view('livewire.schedules-list', [
            'schedules' => $schedules,
        ]);
    }
}
