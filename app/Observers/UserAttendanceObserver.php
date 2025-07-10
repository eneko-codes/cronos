<?php

namespace App\Observers;

use App\Models\UserAttendance;
use Illuminate\Support\Facades\Log;

class UserAttendanceObserver
{
    public function created(UserAttendance $attendance)
    {
        Log::info('UserAttendance created', [
            'id' => $attendance->id,
            'attributes' => $attendance->getAttributes(),
        ]);
    }

    public function updated(UserAttendance $attendance)
    {
        $changes = $attendance->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $attendance->getOriginal($field);
            }
            Log::info('UserAttendance updated', [
                'id' => $attendance->id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(UserAttendance $attendance)
    {
        Log::info('UserAttendance deleted', [
            'id' => $attendance->id,
            'attributes' => $attendance->getOriginal(),
        ]);
    }
}
