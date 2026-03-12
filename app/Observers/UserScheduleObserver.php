<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserSchedule;
use App\Notifications\ScheduleChangeNotification;
use App\Notifications\ScheduleStartingNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class UserScheduleObserver
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Handle the UserSchedule "created" event.
     *
     * Notifies the user when a new schedule assignment starts.
     */
    public function created(UserSchedule $userSchedule): void
    {
        Log::debug('UserSchedule created', [
            'id' => $userSchedule->id,
            'attributes' => $userSchedule->getAttributes(),
        ]);

        // Check if this is a new schedule assignment starting (effective_until is null)
        if (is_null($userSchedule->effective_until)) {
            // Eager load relationships if not already loaded
            $userSchedule->loadMissing(['user', 'schedule']);

            $user = $userSchedule->user;
            $newSchedule = $userSchedule->getRelation('schedule');

            if ($user && $newSchedule) {
                // Find the previous schedule if any
                $previousSchedule = $user->userSchedules()
                    ->where('id', '!=', $userSchedule->id)
                    ->whereNotNull('effective_until')
                    ->orderBy('effective_until', 'desc')
                    ->first();

                $oldSchedule = null;
                if ($previousSchedule) {
                    $previousSchedule->loadMissing('schedule');
                    $oldSchedule = $previousSchedule->getRelation('schedule');
                }

                // Create and send notification (eligibility checked by notification's shouldSend())
                $notification = new ScheduleStartingNotification($user, $newSchedule, $oldSchedule);
                $this->notificationService->notifyUser($user, $notification);
            }
        }
    }

    /**
     * Handle the UserSchedule "updated" event.
     *
     * Notifies the user when their schedule assignment ends (effective_until is set).
     */
    public function updated(UserSchedule $userSchedule): void
    {
        $changes = $userSchedule->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $userSchedule->getOriginal($field);
            }
            Log::debug('UserSchedule updated', [
                'id' => $userSchedule->id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }

        // Check if the schedule assignment just ended (effective_until was changed FROM null TO a date)
        if ($userSchedule->wasChanged('effective_until') && ! is_null($userSchedule->effective_until)) {
            // Eager load relationships if not already loaded
            $userSchedule->loadMissing(['user', 'schedule']);

            $user = $userSchedule->user;
            $endedSchedule = $userSchedule->schedule; // The schedule that just ended

            // Create and send notification (eligibility checked by notification's shouldSend())
            $notification = new ScheduleChangeNotification($user, $endedSchedule, null);
            $this->notificationService->notifyUser($user, $notification);
        }
    }

    /**
     * Handle the UserSchedule "deleted" event.
     */
    public function deleted(UserSchedule $userSchedule): void
    {
        Log::debug('UserSchedule deleted', [
            'id' => $userSchedule->id,
            'attributes' => $userSchedule->getOriginal(),
        ]);
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
