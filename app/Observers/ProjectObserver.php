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
        // Detach all users
        $project->users()->detach();

        // Delete associated time entries (both from tasks and direct)
        $project->timeEntries()->delete();

        // Delete associated tasks
        $project->tasks()->delete();
    }

    public function created(Project $project): void
    {
        Log::debug('Project created', [
            'proofhub_project_id' => $project->proofhub_project_id,
            'attributes' => $project->getAttributes(),
        ]);
    }

    public function updated(Project $project): void
    {
        $changes = $project->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $project->getOriginal($field);
            }
            Log::debug('Project updated', [
                'proofhub_project_id' => $project->proofhub_project_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(Project $project): void
    {
        Log::debug('Project deleted', [
            'proofhub_project_id' => $project->proofhub_project_id,
            'attributes' => $project->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
