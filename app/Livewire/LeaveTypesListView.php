<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\LeaveType;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Leave Types')]
class LeaveTypesListView extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 25;

    // Sorting
    public string $sortBy = 'name_asc'; // Default sort: name asc

    // Filtering
    public array $filters = [
        'active' => null, // null = show all, true = active, false = inactive
        'is_unpaid' => null, // null = show all, true = unpaid, false = paid
        'requires_allocation' => null, // null = show all, true = requires, false = doesn't require
        'has_user_leaves' => false,
        'has_no_user_leaves' => false,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
        'sortBy' => ['except' => 'name_asc'],
        'perPage' => ['except' => 25],
        'filters.active' => ['as' => 'f_act', 'except' => null],
        'filters.is_unpaid' => ['as' => 'f_unp', 'except' => null],
        'filters.requires_allocation' => ['as' => 'f_req', 'except' => null],
        'filters.has_user_leaves' => ['as' => 'f_hul', 'except' => false],
        'filters.has_no_user_leaves' => ['as' => 'f_hnul', 'except' => false],
    ];

    /**
     * Reset page when search query changes.
     */
    public function updatedSearch(): void
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
     * Reset page when filters change.
     */
    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    /**
     * Render the component.
     */
    public function render()
    {
        $leaveTypes = LeaveType::query()
            ->when($this->search, function ($query) {
                $query->whereRaw('LOWER(name) LIKE ?', [
                    '%'.strtolower($this->search).'%',
                ]);
            })
          // Apply Filters
            ->when(! is_null($this->filters['active']), function ($query) {
                $query->where('active', $this->filters['active']);
            })
            ->when(! is_null($this->filters['is_unpaid']), function ($query) {
                $query->where('is_unpaid', $this->filters['is_unpaid']);
            })
            ->when(! is_null($this->filters['requires_allocation']), function ($query) {
                $query->where('requires_allocation', $this->filters['requires_allocation']);
            })
            ->when($this->filters['has_user_leaves'], function ($query) {
                $query->has('leaves');
            })
            ->when($this->filters['has_no_user_leaves'], function ($query) {
                $query->doesntHave('leaves');
            })
            ->withCount('leaves') // Add count of associated user leaves
          // Apply Sorting
            ->when($this->sortBy, function ($query) {
                match ($this->sortBy) {
                    'name_asc' => $query->orderBy('name', 'asc'),
                    'name_desc' => $query->orderBy('name', 'desc'),
                    'created_at_desc' => $query->orderBy('created_at', 'desc'),
                    'created_at_asc' => $query->orderBy('created_at', 'asc'),
                    'updated_at_desc' => $query->orderBy('updated_at', 'desc'),
                    'updated_at_asc' => $query->orderBy('updated_at', 'asc'),
                    'leaves_count_desc' => $query->orderBy('leaves_count', 'desc'),
                    'leaves_count_asc' => $query->orderBy('leaves_count', 'asc'),
                    default => $query->orderBy('name', 'asc'), // Fallback default
                };
            })
            ->paginate($this->perPage);

        return view('livewire.leave-types-list-view', [
            'leaveTypes' => $leaveTypes,
        ]);
    }
}
