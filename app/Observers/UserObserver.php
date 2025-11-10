<?php

declare(strict_types=1);

namespace App\Observers;

use App\Actions\GetNotificationPreferencesAction;
use App\Actions\UpdateNotificationPreferencesAction;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\AdminPromotionEmail;
use App\Notifications\UserPromotedToAdminNotification;
use App\Notifications\WelcomeEmail;
use App\Notifications\WelcomeNewUserEmail;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    private UpdateNotificationPreferencesAction $updatePreferences;

    private GetNotificationPreferencesAction $getPreferences;

    public function __construct(
        UpdateNotificationPreferencesAction $updatePreferences,
        GetNotificationPreferencesAction $getPreferences
    ) {
        $this->updatePreferences = $updatePreferences;
        $this->getPreferences = $getPreferences;
    }

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        Log::debug('User created', [
            'id' => $user->id,
            'attributes' => $user->getAttributes(),
        ]);

        // Initialize default notification preferences for the new user
        $this->updatePreferences->initialize($user);

        // Send welcome email with password setup link for new users without passwords
        if ($user->email && is_null($user->password)) {
            try {
                $user->notify(new WelcomeNewUserEmail);

                Log::info('Welcome email sent to new user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send welcome email to new user', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            // For users with passwords, use the existing welcome notification
            $welcomeNotification = new WelcomeEmail;
            if ($user->email && ($this->getPreferences->execute($user)['eligibility'][$welcomeNotification->type()->value] ?? false)) {
                $user->notify($welcomeNotification);
            }
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
        $changes = $user->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $user->getOriginal($field);
            }
            Log::debug('User updated', [
                'id' => $user->id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }

        // Check if the user was just promoted to admin
        if ($user->wasChanged('user_type') && $user->isAdmin()) {

            // --- Notify other admins ---
            $adminPromotionEmail = new AdminPromotionEmail($user);

            $adminUsers = User::where('user_type', RoleType::Admin)
                ->where('id', '!=', $user->id)
                ->get();

            foreach ($adminUsers as $admin) {
                if ($this->getPreferences->execute($admin)['eligibility'][$adminPromotionEmail->type()->value] ?? false) {
                    $admin->notify($adminPromotionEmail);
                }
            }

            // --- Notify the promoted user ---
            $userPromotionNotification = new UserPromotedToAdminNotification;
            if ($this->getPreferences->execute($user)['eligibility'][$userPromotionNotification->type()->value] ?? false) {
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
        Log::debug('User deleted', [
            'id' => $user->id,
            'attributes' => $user->getOriginal(),
        ]);
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
