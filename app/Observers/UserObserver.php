<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\CheckNotificationEligibilityAction;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\AdminPromotionEmail;
use App\Notifications\UserPromotedToAdminNotification;
use App\Notifications\WelcomeEmail;
use App\Services\NotificationPreferenceService;

class UserObserver
{
    private CheckNotificationEligibilityAction $eligibilityAction;

    private NotificationPreferenceService $notificationPreferenceService;

    public function __construct(
        CheckNotificationEligibilityAction $eligibilityAction,
        NotificationPreferenceService $notificationPreferenceService
    ) {
        $this->eligibilityAction = $eligibilityAction;
        $this->notificationPreferenceService = $notificationPreferenceService;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        // Initialize default notification preferences for the new user
        $this->notificationPreferenceService->initializeUserPreferences($user);

        $welcomeNotification = new WelcomeEmail;

        if ($user->email && $this->eligibilityAction->execute($welcomeNotification->type(), $user)) {
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
                if ($project->proofhub_project_id) {
                    $user->projects()->detach($project->proofhub_project_id);
                }
            }

            foreach ($user->categories as $category) {
                if ($category->odoo_category_id) {
                    $user->categories()->detach($category->odoo_category_id);
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
        if ($user->wasChanged('user_type') && $user->isAdmin()) {

            // --- Notify other admins ---
            $adminPromotionEmail = new AdminPromotionEmail($user);

            $adminUsers = User::where('user_type', RoleType::Admin)
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($adminUsers as $admin) {
                if ($this->eligibilityAction->execute($adminPromotionEmail->type(), $admin)) {
                    $admin->notify($adminPromotionEmail);
                }
            }

            // --- Notify the promoted user ---
            $userPromotionNotification = new UserPromotedToAdminNotification;
            if ($this->eligibilityAction->execute($userPromotionNotification->type(), $user)) {
                $user->notify($userPromotionNotification);
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

        // Delete the related notification preferences records
        $user->notificationPreferences()->delete();

        // Detach belongsToMany relations individually to emit model events
        foreach ($user->projects as $project) {
            if ($project->proofhub_project_id) {
                $user->projects()->detach($project->proofhub_project_id);
            }
        }

        foreach ($user->categories as $category) {
            if ($category->odoo_category_id) {
                $user->categories()->detach($category->odoo_category_id);
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
