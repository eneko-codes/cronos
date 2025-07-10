<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Project;
use Illuminate\Support\Facades\Log;

class ProjectObserver
{
    /**
     * Handle the Project "deleting" event.
     */
    public function deleting(Project $project): void
    {
        // Detach each user individually to emit model events
        foreach ($project->users as $user) {
            $project->users()->detach($user->id);
        }

        // Delete associated tasks and their time entries to emit model events
        foreach ($project->tasks as $task) {
            foreach ($task->timeEntries as $timeEntry) {
                $timeEntry->delete();
            }
            $task->delete();
        }

        // Delete associated time entries not linked to tasks to emit model events
        foreach ($project->timeEntries as $timeEntry) {
            $timeEntry->delete();
        }
    }

    public function created($project)
    {
        Log::info('Project created', [
            'proofhub_project_id' => $project->proofhub_project_id,
            'attributes' => $project->getAttributes(),
        ]);
    }

    public function updated($project)
    {
        $changes = $project->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $project->getOriginal($field);
            }
            Log::info('Project updated', [
                'proofhub_project_id' => $project->proofhub_project_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted($project)
    {
        Log::info('Project deleted', [
            'proofhub_project_id' => $project->proofhub_project_id,
            'attributes' => $project->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
