<?php

namespace App\Livewire;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Project Details')]
class ProjectDetailView extends Component
{
    public Project $project;

    // Properties to hold loaded data
    public Collection $tasks;

    public Collection $projectTimeEntries;

    public Collection $taskTimeEntries;

    public array $expandedTasks = []; // For toggling task time entries

    // State for main section visibility
    public bool $showProjectTimeEntries = true;

    public bool $showTasks = true;

    /**
     * Mount the component, accepting the Project model via route model binding.
     * Load initial data using eager loading.
     */
    public function mount(Project $project)
    {
        // Eager load project with users, tasks (incl. relations/counts), and project-level time entries
        $project->load([
            'users:id,name,is_admin',
            'tasks' => function ($query) {
                $query->with('users:id,name,is_admin') // Load task users
                    ->withCount('timeEntries')       // Count task time entries
                    ->orderBy('name');               // Order tasks
            },
            'timeEntries' => function ($query) {
                $query->whereNull('proofhub_task_id') // Only project-level entries
                    ->with('user:id,name,is_admin') // Load entry user
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc');
            },
        ]);

        $this->project = $project;
        $this->tasks = $project->tasks;
        $this->projectTimeEntries = $project->timeEntries;

        // Initialize with an empty Eloquent Collection to match the type hint
        $this->taskTimeEntries = new \Illuminate\Database\Eloquent\Collection;
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
            ->with('user:id,name,is_admin')
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
            if (! isset($this->taskTimeEntries[$taskId])) {
                $this->loadTaskTimeEntries([$taskId]);
            }
        }
    }

    /**
     * Toggle visibility of the Project Time Entries section.
     */
    public function toggleProjectTimeEntries(): void
    {
        $this->showProjectTimeEntries = ! $this->showProjectTimeEntries;
    }

    /**
     * Toggle visibility of the Tasks section.
     */
    public function toggleTasks(): void
    {
        $this->showTasks = ! $this->showTasks;
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.project-detail-view');
    }

    /**
     * Define the title for the page.
     */
    #[Title('Project: ')]
    public function title(): string
    {
        return 'Project: ' . $this->project->name;
    }
}
