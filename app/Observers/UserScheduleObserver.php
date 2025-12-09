<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\User;
use App\Models\UserSchedule;
use App\Notifications\ScheduleChangeNotification;
use App\Services\NotificationPreferenceService;
use Illuminate\Support\Facades\Log;

class UserScheduleObserver
{
    private NotificationPreferenceService $preferenceService;

    public function __construct(NotificationPreferenceService $preferenceService)
    {
        $this->preferenceService = $preferenceService;
    }

    /**
     * Handle the UserSchedule "created" event.
     */
    public function created(UserSchedule $userSchedule): void
    {
        Log::debug('UserSchedule created', [
            'id' => $userSchedule->id,
            'attributes' => $userSchedule->getAttributes(),
        ]);
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

            // Create the notification: Pass the ended schedule as old, and null as new
            // Note: We might need more details than just the Schedule model depending on the Notification's needs.
            // Assuming the notification can handle the Schedule model object or relevant details from it.
            $notification = new ScheduleChangeNotification($user, $endedSchedule, null);

            // Check permission using the notification service
            if ($this->preferenceService->getPreferences($user)['eligibility'][$notification->type()->value] ?? false) {
                $user->notify($notification);
            }
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
