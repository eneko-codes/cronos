<?php

declare(strict_types=1);

namespace App\Observers;

use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\AdminDemotionNotification;
use App\Notifications\AdminPromotionNotification;
use App\Notifications\MaintenanceDemotionNotification;
use App\Notifications\MaintenancePromotionNotification;
use App\Notifications\UserArchivedAdminNotification;
use App\Notifications\UserArchivedNotification;
use App\Notifications\UserDemotedFromAdminNotification;
use App\Notifications\UserDemotedFromMaintenanceNotification;
use App\Notifications\UserDoNotTrackAdminNotification;
use App\Notifications\UserDoNotTrackNotification;
use App\Notifications\UserPromotedToAdminNotification;
use App\Notifications\UserPromotedToMaintenanceNotification;
use App\Notifications\UserReactivatedAdminNotification;
use App\Notifications\UserReactivatedNotification;
use App\Notifications\UserTrackingEnabledAdminNotification;
use App\Notifications\UserTrackingEnabledNotification;
use App\Notifications\WelcomeNewUserNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class UserObserver
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Handle the User "created" event.
     */
    public function created(User $user): void
    {
        Log::debug('User created', [
            'id' => $user->id,
            'attributes' => $user->getAttributes(),
        ]);

        // Send welcome email with password setup link for new users without passwords
        if ($user->email && is_null($user->password)) {
            try {
                $user->notify(new WelcomeNewUserNotification);

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
        }
    }

    /**
     * Handle the User "updating" event.
     */
    public function updating(User $user): void
    {
        // Handle do_not_track logic
        if ($user->isDirty('do_not_track') && $user->do_not_track) {
            $this->deleteUserData($user);
        }

        // Handle is_active logic - when user is archived (set to inactive)
        if ($user->isDirty('is_active') && ! $user->is_active) {
            // User is being archived, delete all their data
            $this->deleteUserData($user);

            // If manually archived (not from Odoo sync), set manually_archived_at
            // and delete external identities (but keep password)
            if (is_null($user->manually_archived_at)) {
                $user->manually_archived_at = now();

                // Delete all external identities
                $user->externalIdentities()->delete();
            }
        }
    }

    /**
     * Delete all user data (schedules, leaves, attendances, time entries, etc.).
     * This is used when a user is set to do_not_track or archived (is_active = false).
     */
    private function deleteUserData(User $user): void
    {
        // Delete hasMany relations using relationship query builder
        $user->userSchedules()->delete();
        $user->userLeaves()->delete();
        $user->userAttendances()->delete();
        $user->timeEntries()->delete();
        $user->notificationPreferences()->delete();

        // Detach belongsToMany relations
        $user->projects()->detach();
        $user->categories()->detach();
        $user->tasks()->detach();
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

        // Send verification email if email changed and is not verified
        // This uses Laravel 12 native MustVerifyEmail interface
        // The notification is rate-limited at the queue level to prevent spam (5 minutes per user)
        if ($user->wasChanged('email') && ! $user->hasVerifiedEmail()) {
            try {
                $user->sendEmailVerificationNotification();

                Log::info('Verification email sent due to email change', [
                    'user_id' => $user->id,
                    'old_email' => $user->getOriginal('email'),
                    'new_email' => $user->email,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send verification email after email change', [
                    'user_id' => $user->id,
                    'old_email' => $user->getOriginal('email'),
                    'new_email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Check if the user was just promoted to admin
        if ($user->wasChanged('user_type') && $user->isAdmin()) {
            $performedBy = Auth::user();

            // Notify other admins (exclude the promoted user)
            $adminPromotionNotification = new AdminPromotionNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($adminPromotionNotification, excludeUserId: $user->id);

            // Notify the promoted user (eligibility checked by notification's shouldSend())
            $this->notificationService->notifyUser($user, new UserPromotedToAdminNotification);
        }

        // Check if the user was just promoted to maintenance role
        if ($user->wasChanged('user_type') && $user->isMaintenance()) {
            $performedBy = Auth::user();

            // Notify admins
            $maintenancePromotionNotification = new MaintenancePromotionNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($maintenancePromotionNotification);

            // Notify the promoted user (eligibility checked by notification's shouldSend())
            $this->notificationService->notifyUser($user, new UserPromotedToMaintenanceNotification);
        }

        // Check if the user was just demoted from admin
        if ($user->wasChanged('user_type') && $user->getOriginal('user_type') === RoleType::Admin && ! $user->isAdmin()) {
            $performedBy = Auth::user();

            // Notify admins
            $adminDemotionNotification = new AdminDemotionNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($adminDemotionNotification);

            // Notify the demoted user (eligibility checked by notification's shouldSend())
            $this->notificationService->notifyUser($user, new UserDemotedFromAdminNotification);
        }

        // Check if the user was just demoted from maintenance role
        if ($user->wasChanged('user_type') && $user->getOriginal('user_type') === RoleType::Maintenance && ! $user->isMaintenance()) {
            $performedBy = Auth::user();

            // Notify admins
            $maintenanceDemotionNotification = new MaintenanceDemotionNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($maintenanceDemotionNotification);

            // Notify the demoted user (eligibility checked by notification's shouldSend())
            $this->notificationService->notifyUser($user, new UserDemotedFromMaintenanceNotification);
        }

        // Check if the user was just archived (set to inactive)
        if ($user->wasChanged('is_active') && ! $user->is_active) {
            $performedBy = Auth::user();

            // Notify the archived user via email (always sends)
            try {
                $this->notificationService->notifyUser($user, new UserArchivedNotification);

                Log::info('User archived notification sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send user archived notification', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            // Notify admins (respects notification preferences)
            $archivedAdminNotification = new UserArchivedAdminNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($archivedAdminNotification);
        }

        // Check if the user was just reactivated (set to active)
        if ($user->wasChanged('is_active') && $user->is_active && $user->getOriginal('is_active') === false) {
            $performedBy = Auth::user();

            // Notify the reactivated user via email (auth notification - always sends)
            try {
                $this->notificationService->notifyUser($user, new UserReactivatedNotification);

                Log::info('User reactivated notification sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send user reactivated notification', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            // Notify admins (respects notification preferences)
            $reactivatedAdminNotification = new UserReactivatedAdminNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($reactivatedAdminNotification);
        }

        // Check if the user was just set to do not track
        if ($user->wasChanged('do_not_track') && $user->do_not_track) {
            $performedBy = Auth::user();

            // Notify the affected user via email (auth notification - always sends)
            try {
                $this->notificationService->notifyUser($user, new UserDoNotTrackNotification);

                Log::info('User do not track notification sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send user do not track notification', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            // Notify admins (respects notification preferences)
            $doNotTrackAdminNotification = new UserDoNotTrackAdminNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($doNotTrackAdminNotification);
        }

        // Check if the user was just removed from do not track (tracking enabled)
        if ($user->wasChanged('do_not_track') && ! $user->do_not_track && $user->getOriginal('do_not_track') === true) {
            $performedBy = Auth::user();

            // Notify the affected user via email (auth notification - always sends)
            try {
                $this->notificationService->notifyUser($user, new UserTrackingEnabledNotification);

                Log::info('User tracking enabled notification sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'name' => $user->name,
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send user tracking enabled notification', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }

            // Notify admins (respects notification preferences)
            $trackingEnabledAdminNotification = new UserTrackingEnabledAdminNotification($user, $performedBy);
            $this->notificationService->notifyAdminUsers($trackingEnabledAdminNotification);
        }
    }

    /**
     * Handle the User "deleting" event.
     */
    public function deleting(User $user): void
    {
        // Delete hasMany relations using relationship query builder
        $user->userSchedules()->delete();
        $user->userLeaves()->delete();
        $user->userAttendances()->delete();
        $user->timeEntries()->delete();
        $user->notificationPreferences()->delete();

        // Detach belongsToMany relations
        $user->projects()->detach();
        $user->categories()->detach();
        $user->tasks()->detach();
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
