<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Task;
use Illuminate\Support\Facades\Log;

class TaskObserver
{
    /**
     * Handle the Task "deleting" event.
     */
    public function deleting(Task $task): void
    {
        // Detach all users
        $task->users()->detach();

        // Delete associated time entries
        $task->timeEntries()->delete();
    }

    public function created(Task $task): void
    {
        Log::debug('Task created', [
            'proofhub_task_id' => $task->proofhub_task_id,
            'attributes' => $task->getAttributes(),
        ]);
    }

    public function updated(Task $task): void
    {
        $changes = $task->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $task->getOriginal($field);
            }
            Log::debug('Task updated', [
                'proofhub_task_id' => $task->proofhub_task_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(Task $task): void
    {
        Log::debug('Task deleted', [
            'proofhub_task_id' => $task->proofhub_task_id,
            'attributes' => $task->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
