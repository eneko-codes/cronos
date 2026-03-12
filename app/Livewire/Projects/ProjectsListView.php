<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\TimeEntry;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Projects')]
#[Lazy]
class ProjectsListView extends Component
{
    use WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 25)]
    public int $perPage = 25; // Added for configurable pagination

    // Sorting
    #[Url(except: 'title_asc')]
    public string $sortBy = 'title_asc'; // Default sort: title asc

    // Filtering
    public array $filters = [
        'has_tasks' => false,
        'has_time_entries' => false,
        'has_no_tasks' => false,
        'has_no_time_entries' => false,
        'has_direct_time_entries' => false,
    ];

    /**
     * Query string configuration for nested filters with aliases.
     */
    protected $queryString = [
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
        $applyFiltersAndSorting = function ($query): void {
            // Apply Filters
            $query->when($this->filters['has_tasks'], function ($q): void {
                $q->has('tasks');
            })
                ->when($this->filters['has_time_entries'], function ($q): void {
                    $q->has('timeEntries'); // Checks if project has any time entries (direct or via tasks)
                })
                ->when($this->filters['has_no_tasks'], function ($q): void {
                    $q->doesntHave('tasks');
                })
                ->when($this->filters['has_no_time_entries'], function ($q): void {
                    $q->doesntHave('timeEntries');
                })
                ->when($this->filters['has_direct_time_entries'], function ($q): void {
                    $q->whereHas('timeEntries', function (\Illuminate\Database\Eloquent\Builder $subQuery): void {
                        /** @var \Illuminate\Database\Eloquent\Builder<TimeEntry> $subQuery */
                        $subQuery->projectLevel();
                    });
                })
                ->withCount('tasks')
                ->withCount([
                    'timeEntries as project_time_entries_count' => function (\Illuminate\Database\Eloquent\Builder $subQuery): void {
                        /** @var \Illuminate\Database\Eloquent\Builder<TimeEntry> $subQuery */
                        $subQuery->projectLevel();
                    },
                ])
                ->withCount('users') // Add count of users
                // Apply Sorting
                ->when($this->sortBy, function ($q): void {
                    match ($this->sortBy) {
                        'title_asc' => $q->orderBy('title', 'asc'),
                        'title_desc' => $q->orderBy('title', 'desc'),
                        'created_at_desc' => $q->orderBy('created_at', 'desc'),
                        'created_at_asc' => $q->orderBy('created_at', 'asc'),
                        'updated_at_desc' => $q->orderBy('updated_at', 'desc'),
                        'updated_at_asc' => $q->orderBy('updated_at', 'asc'),
                        'tasks_count_desc' => $q->orderBy('tasks_count', 'desc'),
                        'tasks_count_asc' => $q->orderBy('tasks_count', 'asc'),
                        'project_time_entries_count_desc' => $q->orderBy(
                            'project_time_entries_count',
                            'desc'
                        ),
                        'project_time_entries_count_asc' => $q->orderBy(
                            'project_time_entries_count',
                            'asc'
                        ),
                        default => $q->orderBy('title', 'asc'), // Fallback default
                    };
                });
        };

        // Apply search using Scout
        if ($this->search) {
            $projects = Project::search($this->search)
                ->query($applyFiltersAndSorting)
                ->paginate($this->perPage);
        } else {
            $projects = Project::query()
                ->tap($applyFiltersAndSorting)
                ->paginate($this->perPage);
        }

        return view('livewire.projects.projects-list-view', [
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
        return view('livewire.projects.projects-list-view-skeleton', $params);
    }
}
