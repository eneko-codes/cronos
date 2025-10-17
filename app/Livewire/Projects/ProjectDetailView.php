<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Project Details')]
#[Lazy]
class ProjectDetailView extends Component
{
    public Project $project;

    // Properties to hold loaded data
    /** @var EloquentCollection<int, \App\Models\Task> */
    public EloquentCollection $tasks;

    /** @var EloquentCollection<int, \App\Models\TimeEntry> */
    public EloquentCollection $projectTimeEntries;

    /** @var \Illuminate\Support\Collection<string, \Illuminate\Support\Collection<\App\Models\TimeEntry>> */
    public \Illuminate\Support\Collection $taskTimeEntries;

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
            'users:id,name,user_type',
            'tasks' => function ($query): void {
                $query->with('users:id,name,user_type') // Load task users
                    ->withCount('timeEntries')       // Count task time entries
                    ->orderBy('title');               // Order tasks
            },
            'timeEntries' => function ($query): void {
                $query->whereNull('proofhub_task_id') // Only project-level entries
                    ->with('user:id,name,user_type') // Load entry user
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc');
            },
        ]);

        $this->project = $project;
        $this->tasks = $project->tasks;
        $this->projectTimeEntries = $project->timeEntries;

        // Initialize with an empty Support Collection to match the type hint
        $this->taskTimeEntries = new \Illuminate\Support\Collection;
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
            ->with('user:id,name,user_type')
            ->orderBy('date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->groupBy('proofhub_task_id'); // Group by task ID for easy access in the view

        // Merge new entries with existing ones (if any)
        foreach ($newEntries as $taskId => $entriesForTask) {
            $this->taskTimeEntries->put($taskId, $entriesForTask);
        }
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
        return view('livewire.projects.project-detail-view');
    }

    /**
     * Define the title for the page.
     */
    #[Title('Project: ')]
    public function title(): string
    {
        return 'Project: '.$this->project->title;
    }

    /**
     * Render a skeleton placeholder while the project detail view is loading.
     * This provides a visual indication that the project detail data is being fetched.
     *
     * @return \Illuminate\View\View
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.projects.project-detail-view-skeleton', $params);
    }
}
