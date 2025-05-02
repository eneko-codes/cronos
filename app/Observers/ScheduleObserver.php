<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Schedule;

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

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
