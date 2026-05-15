<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Department;
use Illuminate\Support\Facades\Log;

class DepartmentObserver
{
    /**
     * Handle the Department "deleting" event.
     */
    public function deleting(Department $department): void
    {
        // Set department_id to null for all associated users
        $department->users()->update(['department_id' => null]);
    }

    public function created(Department $department): void
    {
        Log::debug('Department created', [
            'odoo_department_id' => $department->odoo_department_id,
            'attributes' => $department->getAttributes(),
        ]);
    }

    public function updated(Department $department): void
    {
        $changes = $department->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $department->getOriginal($field);
            }
            Log::debug('Department updated', [
                'odoo_department_id' => $department->odoo_department_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(Department $department): void
    {
        Log::debug('Department deleted', [
            'odoo_department_id' => $department->odoo_department_id,
            'attributes' => $department->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
