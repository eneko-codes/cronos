<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Department;

class DepartmentObserver
{
    /**
     * Handle the Department "deleting" event.
     */
    public function deleting(Department $department): void
    {
        // Set 'department_id' to null for each associated user to emit model events
        foreach ($department->users as $user) {
            $user->department_id = null;
            $user->save();
        }
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
