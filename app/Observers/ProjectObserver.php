<?php

namespace App\Observers;

use App\Models\Project;

class ProjectObserver
{
    /**
     * Handle the Project "deleting" event.
     *
     * @param  \App\Models\Project  $project
     * @return void
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

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
} 