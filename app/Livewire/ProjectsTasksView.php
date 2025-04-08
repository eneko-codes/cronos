<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Livewire\Component;
use Livewire\Attributes\Title;
use Livewire\WithPagination;
use Illuminate\Database\Eloquent\Collection;

#[Title('Projects, Tasks & Time-Entries')]
class ProjectsTasksView extends Component
{
  use WithPagination;

  public int $perPage = 15;
  public string $search = '';

  public array $expandedProjects = [];
  public array $expandedTasks = [];

  public array $loadedTasks = [];
  public array $loadedTimeEntries = [];

  protected $queryString = [
    'search' => ['except' => ''],
    'page' => ['except' => 1],
  ];

  /**
   * Reset page when search query changes.
   */
  public function updatedSearch(): void
  {
    $this->resetPage();
    $this->expandedProjects = [];
    $this->expandedTasks = [];
    $this->loadedTasks = [];
    $this->loadedTimeEntries = [];
  }

  /**
   * Reset page when items per page changes.
   */
  public function updatedPerPage(): void
  {
    $this->resetPage();
  }

  /**
   * Toggle project expansion and load tasks if needed.
   */
  public function toggleProject($projectId): void
  {
    if (in_array($projectId, $this->expandedProjects)) {
      $this->expandedProjects = array_diff($this->expandedProjects, [
        $projectId,
      ]);
    } else {
      $this->expandedProjects[] = $projectId;
      if (!isset($this->loadedTasks[$projectId])) {
        $this->loadedTasks[$projectId] = Task::where(
          'proofhub_project_id',
          $projectId
        )
          ->withCount('timeEntries')
          ->with('users')
          ->orderBy('name')
          ->get();
      }
    }
  }

  /**
   * Toggle task expansion and load time entries if needed.
   */
  public function toggleTask($taskId): void
  {
    if (in_array($taskId, $this->expandedTasks)) {
      $this->expandedTasks = array_diff($this->expandedTasks, [$taskId]);
    } else {
      $this->expandedTasks[] = $taskId;
      if (!isset($this->loadedTimeEntries[$taskId])) {
        $this->loadedTimeEntries[$taskId] = TimeEntry::where(
          'proofhub_task_id',
          $taskId
        )
          ->with('user')
          ->orderBy('date', 'desc')
          ->orderBy('created_at', 'desc')
          ->get();
      }
    }
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
      ->withCount('tasks')
      ->with('users')
      ->orderBy('name')
      ->paginate($this->perPage);

    return view('livewire.projects-tasks-view', [
      'projects' => $projects,
    ]);
  }
}
