<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Task;

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

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
