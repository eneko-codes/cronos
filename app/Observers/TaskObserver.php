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
        // Detach each user individually to emit model events
        foreach ($task->users as $user) {
            $task->users()->detach($user->id);
        }

        // Delete associated time entries to emit model events
        foreach ($task->timeEntries as $timeEntry) {
            $timeEntry->delete();
        }
    }

    public function created($task)
    {
        Log::info('Task created', [
            'proofhub_task_id' => $task->proofhub_task_id,
            'attributes' => $task->getAttributes(),
        ]);
    }

    public function updated($task)
    {
        $changes = $task->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $task->getOriginal($field);
            }
            Log::info('Task updated', [
                'proofhub_task_id' => $task->proofhub_task_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted($task)
    {
        Log::info('Task deleted', [
            'proofhub_task_id' => $task->proofhub_task_id,
            'attributes' => $task->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
