<?php

namespace App\Observers;

use App\Models\LeaveType;

class LeaveTypeObserver
{
    /**
     * Handle the LeaveType "deleting" event.
     *
     * @param  \App\Models\LeaveType  $leaveType
     * @return void
     */
    public function deleting(LeaveType $leaveType): void
    {
        // Delete related UserLeave records to emit model events
        foreach ($leaveType->leaves as $leave) {
            $leave->delete();
        }
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
} 