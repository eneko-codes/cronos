<?php

namespace App\Livewire\Admin;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Livewire\Component;
use Livewire\WithPagination;
use Livewire\Attributes\Title;
use Livewire\Attributes\Computed;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\On;

#[Title('Admin - Projects & Tasks')]
class ProjectsTasksView extends Component
{
  use WithPagination;

  /**
   * Number of projects per page for the first column.
   */
  public int $perPage = 25;

  /**
   * Search term for filtering projects.
   */
  public string $search = '';

  /**
   * ID of the currently selected project.
   */
  public ?string $selectedProofhubProjectId = null;

  /**
   * ID of the currently selected task.
   */
  public ?string $selectedProofhubTaskId = null;

  protected $queryString = [
    'search' => ['except' => ''],
    'page' => ['except' => 1], // Keep pagination in query string for projects
  ];

  /**
   * Reset project pagination when search is updated.
   */
  #[On('updated:search')]
  public function resetPageWhenSearchIsUpdated(): void
  {
    $this->resetPage('projectsPage');
  }

  /**
   * Reset selection and pagination when perPage changes.
   */
  public function updatedPerPage(): void
  {
    $this->resetPage('projectsPage');
    $this->clearSelection();
  }

  /**
   * Handle project selection.
   */
  #[On('project-selected')]
  public function handleProjectSelected(string $proofhubProjectId): void
  {
    // If the same project is clicked again, deselect it
    if ($this->selectedProofhubProjectId === $proofhubProjectId) {
      $this->clearSelection();
    } else {
      $this->selectedProofhubProjectId = $proofhubProjectId;
      $this->selectedProofhubTaskId = null; // Reset task selection when project changes
      // No need to reset project pagination here
    }
  }

  /**
   * Handle task selection.
   */
  #[On('task-selected')]
  public function handleTaskSelected(string $proofhubTaskId): void
  {
    // If the same task is clicked again, deselect it
    if ($this->selectedProofhubTaskId === $proofhubTaskId) {
      $this->selectedProofhubTaskId = null;
    } else {
      $this->selectedProofhubTaskId = $proofhubTaskId;
    }
  }

  /**
   * Clear all selections.
   */
  public function clearSelection(): void
  {
    $this->selectedProofhubProjectId = null;
    $this->selectedProofhubTaskId = null;
  }

  /**
   * Get tasks for the selected project.
   *
   * Uses Livewire's computed property caching.
   */
  #[Computed]
  public function selectedProjectTasks(): ?Collection
  {
    if (!$this->selectedProofhubProjectId) {
      return null;
    }

    return Task::where('proofhub_project_id', $this->selectedProofhubProjectId)
      ->withCount('timeEntries') // Count time entries for display
      ->with('users') // Eager load assigned users
      ->orderBy('name')
      ->get();
  }

  /**
   * Get time entries for the selected task.
   *
   * Uses Livewire's computed property caching.
   */
  #[Computed]
  public function selectedTaskTimeEntries(): ?Collection
  {
    if (!$this->selectedProofhubTaskId) {
      return null;
    }

    // Eager load user for display
    return TimeEntry::where('proofhub_task_id', $this->selectedProofhubTaskId)
      ->with('user')
      ->orderBy('date', 'desc')
      ->orderBy('created_at', 'desc')
      ->get();
  }

  /**
   * Render the component.
   *
   * Fetches paginated projects for the first column.
   *
   * @return \Illuminate\Contracts\View\View
   */
  public function render()
  {
    // Fetch paginated projects for the first column
    $projects = Project::query()
      ->when($this->search, function ($query) {
        // Add search condition
        $query->where('name', 'like', '%' . $this->search . '%');
      })
      ->withCount('tasks') // Count tasks for display
      ->with('users') // Eager load assigned users
      ->orderBy('name')
      ->paginate($this->perPage, ['*'], 'projectsPage');

    return view('livewire.admin.projects-tasks-view', [
      'projects' => $projects,
    ]);
  }
}
