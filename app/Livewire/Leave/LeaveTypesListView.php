<?php

declare(strict_types=1);

namespace App\Livewire\Leave;

use App\Models\LeaveType;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
#[Title('Leave Types')]
class LeaveTypesListView extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 25)]
    public int $perPage = 25;

    // Sorting
    #[Url(except: 'name_asc')]
    public string $sortBy = 'name_asc'; // Default sort: name asc

    // Filtering
    public array $filters = [
        'active' => null, // null = show all, true = active, false = inactive
        'is_unpaid' => null, // null = show all, true = unpaid, false = paid
        'requires_allocation' => null, // null = show all, true = requires, false = doesn't require
        'has_user_leaves' => false,
        'has_no_user_leaves' => false,
    ];

    /**
     * Query string configuration for nested filters with aliases.
     */
    protected $queryString = [
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
        $applyFiltersAndSorting = function ($query): void {
            // Apply Filters
            $query->when(! is_null($this->filters['active']), function ($q): void {
                $q->where('active', $this->filters['active']);
            })
                ->when(! is_null($this->filters['is_unpaid']), function ($q): void {
                    $q->where('is_unpaid', $this->filters['is_unpaid']);
                })
                ->when(! is_null($this->filters['requires_allocation']), function ($q): void {
                    $q->where('requires_allocation', $this->filters['requires_allocation']);
                })
                ->when($this->filters['has_user_leaves'], function ($q): void {
                    $q->has('leaves');
                })
                ->when($this->filters['has_no_user_leaves'], function ($q): void {
                    $q->doesntHave('leaves');
                })
                ->withCount('leaves') // Add count of associated user leaves
                // Apply Sorting
                ->when($this->sortBy, function ($q): void {
                    match ($this->sortBy) {
                        'name_asc' => $q->orderBy('name', 'asc'),
                        'name_desc' => $q->orderBy('name', 'desc'),
                        'created_at_desc' => $q->orderBy('created_at', 'desc'),
                        'created_at_asc' => $q->orderBy('created_at', 'asc'),
                        'updated_at_desc' => $q->orderBy('updated_at', 'desc'),
                        'updated_at_asc' => $q->orderBy('updated_at', 'asc'),
                        'leaves_count_desc' => $q->orderBy('leaves_count', 'desc'),
                        'leaves_count_asc' => $q->orderBy('leaves_count', 'asc'),
                        default => $q->orderBy('name', 'asc'), // Fallback default
                    };
                });
        };

        // Apply search using Scout
        if ($this->search) {
            $leaveTypes = LeaveType::search($this->search)
                ->query($applyFiltersAndSorting)
                ->paginate($this->perPage);
        } else {
            $leaveTypes = LeaveType::query()
                ->tap($applyFiltersAndSorting)
                ->paginate($this->perPage);
        }

        return view('livewire.leave.leave-types-list-view', [
            'leaveTypes' => $leaveTypes,
        ]);
    }

    /**
     * Render a skeleton placeholder while the leave types list is loading.
     * This provides a visual indication that the leave types data is being fetched.
     *
     * @return \Illuminate\View\View
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.leave.leave-types-list-view-skeleton', $params);
    }
}
