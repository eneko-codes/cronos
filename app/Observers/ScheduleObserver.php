<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Schedule;
use Illuminate\Support\Facades\Log;

class ScheduleObserver
{
    /**
     * Handle the Schedule "deleting" event.
     */
    public function deleting(Schedule $schedule): void
    {
        // Delete associated schedule details and user schedule assignments
        $schedule->scheduleDetails()->delete();
        $schedule->userSchedules()->delete();
    }

    public function created(Schedule $schedule): void
    {
        Log::debug('Schedule created', [
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'attributes' => $schedule->getAttributes(),
        ]);
    }

    public function updated(Schedule $schedule): void
    {
        $changes = $schedule->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $schedule->getOriginal($field);
            }
            Log::debug('Schedule updated', [
                'odoo_schedule_id' => $schedule->odoo_schedule_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(Schedule $schedule): void
    {
        Log::debug('Schedule deleted', [
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'attributes' => $schedule->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
