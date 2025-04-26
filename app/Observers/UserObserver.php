<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\AdminPromotionEmail;
use App\Notifications\WelcomeEmail;
use App\Services\NotificationPermissionService;
use Illuminate\Support\Facades\Notification;

class UserObserver
{
    public function __construct(
        protected NotificationPermissionService $notificationPermissionService
    ) {}

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Create default notification preferences for the new user
        $user->notificationPreferences()->create();

        // Create the notification instance
        $welcomeNotification = new WelcomeEmail($user);

        // Use the injected service
        if ($user->email && $this->notificationPermissionService->canUserReceiveNotification($user, $welcomeNotification)) {
            $user->notify(new WelcomeEmail($user));
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
                $user->projects()->detach($project->id);
            }

            foreach ($user->categories as $category) {
                $user->categories()->detach($category->id);
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
            // Fetch all other admin users (excluding the promoted user)
            $adminUsers = User::where('is_admin', true)
                ->where('id', '!=', $user->id)
                ->get();

            // Create the notification instance
            $notification = new AdminPromotionEmail($user);

            // Send notification to all other admins who can receive it
            foreach ($adminUsers as $admin) {
                // Use the injected service
                if ($this->notificationPermissionService->canUserReceiveNotification($admin, $notification)) {
                    // Use notify to respect the queue
                    $admin->notify($notification);
                }
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
            $user->projects()->detach($project->id);
        }

        foreach ($user->categories as $category) {
            $user->categories()->detach($category->id);
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
