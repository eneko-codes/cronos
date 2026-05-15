<?php

declare(strict_types=1);

namespace App\Livewire\Projects;

use App\Models\Project;
use App\Models\Task;
use App\Models\TimeEntry;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Project Details')]
#[Lazy]
class ProjectDetailView extends Component
{
    public Project $project;

    // Properties to hold loaded data
    /** @var EloquentCollection<int, Task> */
    public EloquentCollection $tasks;

    /** @var EloquentCollection<int, TimeEntry> */
    public EloquentCollection $projectTimeEntries;

    /** @var Collection<string, Collection<TimeEntry>> */
    public Collection $taskTimeEntries;

    /** @var array<int, int> Task ID => time-entry count. Cached separately because withCount() results don't survive Livewire hydration. */
    public array $taskTimeEntryCounts = [];

    public array $expandedTasks = []; // For toggling task time entries

    // State for main section visibility
    public bool $showProjectTimeEntries = true;

    public bool $showTasks = true;

    /**
     * Mount the component, accepting the Project model via route model binding.
     */
    public function mount(Project $project)
    {
        $this->project = $project;
        $this->refreshProjectData();

        // Initialize with an empty Support Collection to match the type hint
        $this->taskTimeEntries = new Collection;
    }

    /**
     * Runs before hydrate on every request. Ensures the typed Support Collection is
     * initialized even if Livewire's deserializer leaves it unset (it can't round-trip
     * a Support Collection containing Eloquent models).
     */
    public function boot(): void
    {
        if (! isset($this->taskTimeEntries)) {
            $this->taskTimeEntries = new Collection;
        }
    }

    /**
     * Re-eager-load relations and counts every Livewire request — model strict mode is on,
     * and Livewire's default hydration re-fetches Eloquent models without their preloaded
     * relations/withCount, which would otherwise throw LazyLoadingViolation / MissingAttribute.
     */
    public function hydrate(): void
    {
        $this->refreshProjectData();

        // Already-expanded tasks had their time entries (and the user relation on each)
        // wiped by the same hydration process — re-load them.
        $this->taskTimeEntries = new Collection;
        if (! empty($this->expandedTasks)) {
            $this->loadTaskTimeEntries($this->expandedTasks);
        }
    }

    protected function refreshProjectData(): void
    {
        $this->project->load([
            'users:id,name,user_type',
            'tasks' => function ($query): void {
                $query->with('users:id,name,user_type')
                    ->withCount('timeEntries')
                    ->orderBy('title');
            },
            'timeEntries' => function ($query): void {
                $query->projectLevel()
                    ->with('user:id,name,user_type')
                    ->orderBy('date', 'desc')
                    ->orderBy('created_at', 'desc');
            },
        ]);

        $this->tasks = $this->project->tasks;
        $this->projectTimeEntries = $this->project->timeEntries;

        $this->taskTimeEntryCounts = $this->tasks
            ->pluck('time_entries_count', 'proofhub_task_id')
            ->map(fn ($count) => (int) $count)
            ->all();
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
     * @return View
     */
    public function placeholder(array $params = [])
    {
        return view('livewire.projects.project-detail-view-skeleton', $params);
    }
}
