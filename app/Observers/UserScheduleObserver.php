<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserSchedule;
use App\Notifications\ScheduleChangeNotification;
use App\Services\NotificationPermissionService;
use Illuminate\Support\Facades\Log;

class UserScheduleObserver
{
    public function __construct(
        protected NotificationPermissionService $notificationPermissionService
    ) {}

    /**
     * Handle the UserSchedule "created" event.
     */
    public function created(UserSchedule $userSchedule): void
    {
        //
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

            if ($this->notificationPermissionService->canUserReceiveNotification($user, $notification)) {
                $user->notify($notification);
            }
        }
    }

    /**
     * Handle the UserSchedule "deleted" event.
     */
    public function deleted(UserSchedule $userSchedule): void
    {
        //
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
