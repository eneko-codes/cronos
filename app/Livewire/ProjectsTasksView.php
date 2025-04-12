<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Computed;

#[Title('Projects, Tasks & Time-Entries')]
class ProjectsTasksView extends Component
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

  public array $expandedProjects = [];
  public array $expandedTasks = [];

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
    $this->expandedProjects = [];
    $this->expandedTasks = [];
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
   * Toggle project expansion and load tasks if needed.
   */
  public function toggleProject(string $projectId): void
  {
    if (in_array($projectId, $this->expandedProjects)) {
      $this->expandedProjects = array_diff($this->expandedProjects, [
        $projectId,
      ]);
    } else {
      $this->expandedProjects[] = $projectId;
    }
  }

  /**
   * Toggle task expansion and load time entries if needed.
   */
  public function toggleTask(string $taskId): void
  {
    if (in_array($taskId, $this->expandedTasks)) {
      $this->expandedTasks = array_diff($this->expandedTasks, [$taskId]);
    } else {
      $this->expandedTasks[] = $taskId;
    }
  }

  /**
   * Computed property to get tasks for expanded projects.
   * Groups tasks by project ID.
   */
  #[Computed]
  public function tasks(): Collection
  {
    if (empty($this->expandedProjects)) {
      return collect(); // Return empty collection if no projects are expanded
    }

    return Task::whereIn('proofhub_project_id', $this->expandedProjects)
      ->withCount('timeEntries')
      ->with('users')
      ->orderBy('name')
      ->get()
      ->groupBy('proofhub_project_id'); // Group by project ID
  }

  /**
   * Computed property to get project-level time entries for expanded projects.
   * Groups entries by project ID.
   */
  #[Computed]
  public function projectTimeEntries(): Collection
  {
    if (empty($this->expandedProjects)) {
      return collect();
    }

    return TimeEntry::whereIn('proofhub_project_id', $this->expandedProjects)
      ->whereNull('proofhub_task_id')
      ->with('user')
      ->orderBy('date', 'desc')
      ->orderBy('created_at', 'desc')
      ->get()
      ->groupBy('proofhub_project_id'); // Group by project ID
  }

  /**
   * Computed property to get time entries for expanded tasks.
   * Groups entries by task ID.
   */
  #[Computed]
  public function taskTimeEntries(): Collection
  {
    if (empty($this->expandedTasks)) {
      return collect();
    }

    return TimeEntry::whereIn('proofhub_task_id', $this->expandedTasks)
      ->with('user')
      ->orderBy('date', 'desc')
      ->orderBy('created_at', 'desc')
      ->get()
      ->groupBy('proofhub_task_id'); // Group by task ID
  }

  /**
   * Render the component.
   */
  public function render()
  {
    $projects = Project::query()
      ->when($this->search, function ($query) {
        $query->where('name', 'like', '%' . $this->search . '%');
      })
      // Apply Filters
      ->when($this->filters['has_tasks'], function ($query) {
        $query->has('tasks');
      })
      ->when($this->filters['has_time_entries'], function ($query) {
        $query->has('timeEntries'); // Checks if project has any time entries (direct or via tasks)
      })
      ->when($this->filters['has_no_tasks'], function ($query) {
        $query->doesntHave('tasks');
      })
      ->when($this->filters['has_no_time_entries'], function ($query) {
        $query->doesntHave('timeEntries');
      })
      ->when($this->filters['has_direct_time_entries'], function ($query) {
        $query->whereHas('timeEntries', function ($subQuery) {
          $subQuery->whereNull('proofhub_task_id');
        });
      })
      ->withCount('tasks')
      ->withCount([
        'timeEntries as project_time_entries_count' => function ($query) {
          $query->whereNull('proofhub_task_id');
        },
      ])
      ->with('users')
      // Apply Sorting
      ->when($this->sortBy, function (Builder $query) {
        match ($this->sortBy) {
          'name_asc' => $query->orderBy('name', 'asc'),
          'name_desc' => $query->orderBy('name', 'desc'),
          'created_at_desc' => $query->orderBy('created_at', 'desc'),
          'created_at_asc' => $query->orderBy('created_at', 'asc'),
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
      ->paginate($this->perPage); // Use $perPage property

    return view('livewire.projects-tasks-view', [
      'projects' => $projects,
    ]);
  }
}
