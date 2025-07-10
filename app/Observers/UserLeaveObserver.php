<?php

namespace App\Observers;

use App\Models\UserLeave;
use Illuminate\Support\Facades\Log;

class UserLeaveObserver
{
    public function created(UserLeave $leave)
    {
        Log::info('UserLeave created', [
            'id' => $leave->id,
            'attributes' => $leave->getAttributes(),
        ]);
    }

    public function updated(UserLeave $leave)
    {
        $changes = $leave->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $leave->getOriginal($field);
            }
            Log::info('UserLeave updated', [
                'id' => $leave->id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(UserLeave $leave)
    {
        Log::info('UserLeave deleted', [
            'id' => $leave->id,
            'attributes' => $leave->getOriginal(),
        ]);
    }
}
