<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Project;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Projects')]
#[Lazy]
class ProjectsListView extends Component
{
    use WithPagination;

    public string $search = '';

    public int $perPage = 25; // Added for configurable pagination

    // Sorting
    public string $sortBy = 'name_asc'; // Default sort: name asc

    // Filtering
    public array $filters = [
        'has_tasks' => false,
        'has_time_entries' => false,
        'has_no_tasks' => false,
        'has_no_time_entries' => false,
        'has_direct_time_entries' => false,
    ];

    protected $queryString = [
        'search' => ['except' => ''],
        'page' => ['except' => 1],
        'sortBy' => ['except' => 'name_asc'], // Updated default
        'perPage' => ['except' => 25], // Added perPage to query string
        'filters.has_tasks' => ['as' => 'f_ht', 'except' => false],
        'filters.has_time_entries' => ['as' => 'f_hte', 'except' => false],
        'filters.has_no_tasks' => ['as' => 'f_hnt', 'except' => false],
        'filters.has_no_time_entries' => ['as' => 'f_hnte', 'except' => false],
        'filters.has_direct_time_entries' => ['as' => 'f_hdte', 'except' => false],
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
        $projects = Project::query()
            ->when($this->search, function ($query): void {
                $query->whereRaw('LOWER(name) LIKE ?', [
                    '%'.strtolower($this->search).'%',
                ]);
            })
          // Apply Filters
            ->when($this->filters['has_tasks'], function ($query): void {
                $query->has('tasks');
            })
            ->when($this->filters['has_time_entries'], function ($query): void {
                $query->has('timeEntries'); // Checks if project has any time entries (direct or via tasks)
            })
            ->when($this->filters['has_no_tasks'], function ($query): void {
                $query->doesntHave('tasks');
            })
            ->when($this->filters['has_no_time_entries'], function ($query): void {
                $query->doesntHave('timeEntries');
            })
            ->when($this->filters['has_direct_time_entries'], function ($query): void {
                $query->whereHas('timeEntries', function ($subQuery): void {
                    $subQuery->whereNull('proofhub_task_id');
                });
            })
            ->withCount('tasks')
            ->withCount([
                'timeEntries as project_time_entries_count' => function ($query): void {
                    $query->whereNull('proofhub_task_id');
                },
            ])
            ->withCount('users') // Add count of users
          // Eager load users for the list view badges if needed (REMOVED, using withCount now)
          // ->with('users:id,name') // Only load necessary columns
          // Apply Sorting
            ->when($this->sortBy, function ($query): void {
                // Changed Builder import requirement
                match ($this->sortBy) {
                    'name_asc' => $query->orderBy('name', 'asc'),
                    'name_desc' => $query->orderBy('name', 'desc'),
                    'created_at_desc' => $query->orderBy('created_at', 'desc'),
                    'created_at_asc' => $query->orderBy('created_at', 'asc'),
                    'updated_at_desc' => $query->orderBy('updated_at', 'desc'),
                    'updated_at_asc' => $query->orderBy('updated_at', 'asc'),
                    'tasks_count_desc' => $query->orderBy('tasks_count', 'desc'),
                    'tasks_count_asc' => $query->orderBy('tasks_count', 'asc'),
                    'project_time_entries_count_desc' => $query->orderBy(
                        'project_time_entries_count',
                        'desc'
                    ),
                    'project_time_entries_count_asc' => $query->orderBy(
                        'project_time_entries_count',
                        'asc'
                    ),
                    default => $query->orderBy('name', 'asc'), // Fallback default
                };
            })
            ->paginate($this->perPage);

        return view('livewire.projects-list-view', [
            'projects' => $projects,
        ]);
    }

    /**
     * Render a skeleton placeholder while the projects list is loading.
     * This provides a visual indication that the projects data is being fetched.
     *
     * @return \Illuminate\View\View
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.placeholders.projects-list-view-skeleton', $params);
    }
}
