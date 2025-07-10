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
        // Delete associated schedule details to emit model events
        foreach ($schedule->scheduleDetails as $detail) {
            $detail->delete();
        }

        // Delete associated user schedule assignments to emit model events
        foreach ($schedule->userSchedules as $userSchedule) {
            $userSchedule->delete();
        }
    }

    public function created($schedule)
    {
        Log::info('Schedule created', [
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'attributes' => $schedule->getAttributes(),
        ]);
    }

    public function updated($schedule)
    {
        $changes = $schedule->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $schedule->getOriginal($field);
            }
            Log::info('Schedule updated', [
                'odoo_schedule_id' => $schedule->odoo_schedule_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted($schedule)
    {
        Log::info('Schedule deleted', [
            'odoo_schedule_id' => $schedule->odoo_schedule_id,
            'attributes' => $schedule->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
