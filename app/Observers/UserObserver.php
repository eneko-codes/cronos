<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\Notification\ShouldDeliverNotificationToUserAction;
use App\Models\User;
use App\Notifications\AdminPromotionEmail;
use App\Notifications\UserPromotedToAdminNotification;
use App\Notifications\WelcomeEmail;
use App\Services\ApplicationSettingsService;

class UserObserver
{
    private ApplicationSettingsService $settingsService;

    public function __construct(ApplicationSettingsService $settingsService)
    {
        $this->settingsService = $settingsService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Create default notification preferences for the new user
        $user->notificationPreferences()->create();

        // Create the notification instance (no argument)
        $welcomeNotification = new WelcomeEmail;

        // Use the injected service and the created notification instance
        $action = new ShouldDeliverNotificationToUserAction;
        if ($this->settingsService->getWelcomeEmailEnabled() && $user->email && $action->handle($user, $welcomeNotification)) {
            $user->notify($welcomeNotification);
        }
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // Handle do_not_track logic
        if (! $user->isDirty('do_not_track')) {
            return;
        }

        if ($user->do_not_track) {
            // Delete hasMany relations individually to emit model events
            foreach ($user->userSchedules as $schedule) {
                $schedule->delete();
            }

            foreach ($user->userLeaves as $leave) {
                $leave->delete();
            }

            foreach ($user->userAttendances as $attendance) {
                $attendance->delete();
            }

            foreach ($user->timeEntries as $timeEntry) {
                $timeEntry->delete();
            }

            // Detach belongsToMany relations individually to emit model events
            foreach ($user->projects as $project) {
                if ($project && $project->id) {
                    $user->projects()->detach($project->id);
                }
            }

            foreach ($user->categories as $category) {
                if ($category && $category->id) {
                    $user->categories()->detach($category->id);
                }
            }

            foreach ($user->tasks as $task) {
                // Detach this specific user from this specific task
                $task->users()->detach($user->id);
            }
        }
    }

    /**
     * Handle the User "updated" event.
     */
    public function updated(User $user): void
    {
        // Check if the user was just promoted to admin
        if ($user->wasChanged('is_admin') && $user->is_admin) {

            // --- Notify other admins ---
            $adminNotifyAction = new ShouldDeliverNotificationToUserAction;
            $adminNotification = new AdminPromotionEmail($user);

            // Check global setting for notifying other admins
            if ($this->settingsService->getAdminPromotionEmailEnabled()) {
                $adminUsers = User::where('is_admin', true)
                    ->where('id', '!=', $user->id)
                    ->get();

                foreach ($adminUsers as $admin) {
                    // Check if this specific admin should receive the notification
                    if ($adminNotifyAction->handle($admin, $adminNotification)) {
                        $admin->notify($adminNotification);
                    }
                }
            }

            // --- Notify the promoted user ---
            // Check global setting for notifying the promoted user
            if ($this->settingsService->getUserPromotionNotificationEnabled()) {
                // User's own preference doesn't apply here, only global setting matters
                $user->notify(new UserPromotedToAdminNotification($user));
            }
        }
    }

    /**
     * Handle the User "deleting" event.
     */
    public function deleting(User $user): void
    {
        // Delete hasMany relations individually to emit model events
        foreach ($user->loginTokens as $loginToken) {
            $loginToken->delete();
        }

        foreach ($user->userSchedules as $schedule) {
            $schedule->delete();
        }

        foreach ($user->userLeaves as $leave) {
            $leave->delete();
        }

        foreach ($user->userAttendances as $attendance) {
            $attendance->delete();
        }

        foreach ($user->timeEntries as $timeEntry) {
            $timeEntry->delete();
        }

        // Delete the related notification preferences record
        $user->notificationPreferences()->delete();

        // Detach belongsToMany relations individually to emit model events
        foreach ($user->projects as $project) {
            if ($project && $project->id) {
                $user->projects()->detach($project->id);
            }
        }

        foreach ($user->categories as $category) {
            if ($category && $category->id) {
                $user->categories()->detach($category->id);
            }
        }

        foreach ($user->tasks as $task) {
            // Detach this specific user from this specific task
            $task->users()->detach($user->id);
        }
    }

    /**
     * Handle the User "deleted" event.
     */
    public function deleted(User $user): void
    {
        //
    }

    /**
     * Handle the User "restored" event.
     */
    public function restored(User $user): void
    {
        //
    }

    /**
     * Handle the User "force deleted" event.
     */
    public function forceDeleted(User $user): void
    {
        //
    }
}
