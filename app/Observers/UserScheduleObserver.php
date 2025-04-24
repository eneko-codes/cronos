<?php

namespace App\Observers;

use App\Models\User;
use App\Models\UserSchedule;
use App\Notifications\ScheduleChangeNotification;
use Illuminate\Support\Facades\Log;

class UserScheduleObserver
{
    /**
     * Handle the UserSchedule "created" event.
     *
     * Notifies the user when a new schedule assignment is created.
     */
    public function created(UserSchedule $userSchedule): void
    {
        // Eager load relationships to avoid extra queries
        $userSchedule->load(['user', 'schedule']);

        $user = $userSchedule->user;
        $newSchedule = $userSchedule->schedule;

        // Ensure user and schedule exist before proceeding
        if (! $user || ! $newSchedule) {
            Log::warning(
                'UserScheduleObserver: User or Schedule relationship missing for created UserSchedule',
                [
                    'user_schedule_id' => $userSchedule->id,
                    'user_exists' => ! is_null($user),
                    'schedule_exists' => ! is_null($newSchedule),
                ]
            );

            return;
        }

        // Pass null for old schedule, and the new schedule model
        $notification = new ScheduleChangeNotification(
            $user,
            null,
            $newSchedule
        );

        if ($user->canReceiveNotification($notification)) {
            // Queue the notification as it's not as critical
            $user->notify($notification);
        }
    }

    /**
     * Handle the UserSchedule "updated" event.
     *
     * Notifies the user when their schedule assignment ends (effective_until is set).
     */
    public function updated(UserSchedule $userSchedule): void
    {
        // Check if the schedule assignment just ended
        if (
            $userSchedule->wasChanged('effective_until') &&
            ! is_null($userSchedule->effective_until)
        ) {
            // Eager load relationships
            $userSchedule->load(['user', 'schedule']);

            $user = $userSchedule->user;
            $oldSchedule = $userSchedule->schedule;

            // Ensure user and schedule exist
            if (! $user || ! $oldSchedule) {
                Log::warning(
                    'UserScheduleObserver: User or Schedule relationship missing for updated UserSchedule',
                    [
                        'user_schedule_id' => $userSchedule->id,
                        'user_exists' => ! is_null($user),
                        'schedule_exists' => ! is_null($oldSchedule),
                    ]
                );

                return;
            }

            // Pass the ended schedule as old, and null for new
            $notification = new ScheduleChangeNotification(
                $user,
                $oldSchedule,
                null
            );

            if ($user->canReceiveNotification($notification)) {
                $user->notify($notification);
            }
        }
    }

    /**
     * Handle the UserSchedule "deleted" event.
     */
    public function deleted(UserSchedule $userSchedule): void
    {
        // Potential logic: Notify if a schedule assignment is deleted unexpectedly?
    }

    /**
     * Handle the UserSchedule "restored" event.
     */
    public function restored(UserSchedule $userSchedule): void
    {
        //
    }

    /**
     * Handle the UserSchedule "force deleted" event.
     */
    public function forceDeleted(UserSchedule $userSchedule): void
    {
        //
    }
}
