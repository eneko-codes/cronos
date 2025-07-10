<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\LeaveType;
use Illuminate\Support\Facades\Log;

class LeaveTypeObserver
{
    /**
     * Handle the LeaveType "deleting" event.
     */
    public function deleting(LeaveType $leaveType): void
    {
        // Delete related UserLeave records to emit model events
        foreach ($leaveType->leaves as $leave) {
            $leave->delete();
        }
    }

    public function created($leaveType)
    {
        Log::info('LeaveType created', [
            'odoo_leave_type_id' => $leaveType->odoo_leave_type_id,
            'attributes' => $leaveType->getAttributes(),
        ]);
    }

    public function updated($leaveType)
    {
        $changes = $leaveType->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $leaveType->getOriginal($field);
            }
            Log::info('LeaveType updated', [
                'odoo_leave_type_id' => $leaveType->odoo_leave_type_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted($leaveType)
    {
        Log::info('LeaveType deleted', [
            'odoo_leave_type_id' => $leaveType->odoo_leave_type_id,
            'attributes' => $leaveType->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
