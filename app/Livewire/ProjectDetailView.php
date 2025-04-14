<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Livewire\Component;
use Livewire\Attributes\Title;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log; // Set appropriate title

#[Title('Project Details')]
class ProjectDetailView extends Component
{
  public Project $project;

  // Properties to hold loaded data
  public Collection $tasks;
  public Collection $projectTimeEntries;
  public Collection $taskTimeEntries;

  public array $expandedTasks = []; // For toggling task time entries

  /**
   * Mount the component, accepting the Project model via route model binding.
   * Load initial data.
   */
  public function mount(Project $project)
  {
    $this->project = $project->load('users:id,name'); // Eager load project users

    $this->loadTasks();
    $this->loadProjectTimeEntries();
    // Initialize with an empty Eloquent Collection to match the type hint
    $this->taskTimeEntries = new \Illuminate\Database\Eloquent\Collection();
  }

  /**
   * Load tasks associated with the current project.
   */
  protected function loadTasks(): void
  {
    $this->tasks = $this->project
      ->tasks()
      ->with('users:id,name') // Eager load task users
      ->withCount('timeEntries')
      ->orderBy('name')
      ->get();
  }

  /**
   * Load time entries directly associated with the project (not linked to a task).
   */
  protected function loadProjectTimeEntries(): void
  {
    $this->projectTimeEntries = $this->project
      ->timeEntries()
      ->whereNull('proofhub_task_id') // Only project-level entries
      ->with('user:id,name') // Eager load user
      ->orderBy('date', 'desc')
      ->orderBy('created_at', 'desc')
      ->get();
  }

  /**
   * Load time entries for a specific set of tasks.
   */
  protected function loadTaskTimeEntries(array $taskIds): void
  {
    if (empty($taskIds)) {
      return;
    }

    $newEntries = TimeEntry::whereIn('proofhub_task_id', $taskIds)
      ->with('user:id,name') // Eager load user
      ->orderBy('date', 'desc')
      ->orderBy('created_at', 'desc')
      ->get()
      ->groupBy('proofhub_task_id'); // Group by task ID for easy access in the view

    // Merge new entries with existing ones (if any)
    $this->taskTimeEntries = $this->taskTimeEntries->merge($newEntries);
  }

  /**
   * Toggle task expansion and load time entries if needed.
   */
  public function toggleTask(string $taskId): void
  {
    if (in_array($taskId, $this->expandedTasks)) {
      // Collapse: Remove from expanded list
      $this->expandedTasks = array_diff($this->expandedTasks, [$taskId]);
      // Optionally, remove the loaded entries for this task to save memory,
      // but usually not necessary unless dealing with huge amounts of data.
      // unset($this->taskTimeEntries[$taskId]);
    } else {
      // Expand: Add to expanded list and load entries if not already loaded
      $this->expandedTasks[] = $taskId;
      if (!isset($this->taskTimeEntries[$taskId])) {
        $this->loadTaskTimeEntries([$taskId]);
      }
    }
  }

  /**
   * Render the component.
   */
  public function render()
  {
    return view('livewire.project-detail-view');
  }
}
