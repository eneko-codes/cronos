<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class NotificationPreferencePolicy
{
    /**
     * Determine whether the user can view global notification settings.
     */
    public function viewGlobalSettings(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can manage global notification settings.
     */
    public function manageGlobalSettings(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can view their own notification preferences.
     */
    public function viewOwnPreferences(User $user): bool
    {
        return true; // All authenticated users can view their own preferences
    }

    /**
     * Determine whether the user can view another user's notification preferences.
     */
    public function viewUserPreferences(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can modify their own notification preferences.
     */
    public function updateOwnPreferences(User $user): bool
    {
        return true; // All authenticated users can modify their own preferences
    }

    /**
     * Determine whether the user can modify another user's notification preferences.
     */
    public function updateUserPreferences(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can mute/unmute another user's notifications.
     */
    public function muteUserNotifications(User $user, User $targetUser): bool
    {
        return $user->isAdmin() && $user->id !== $targetUser->id;
    }

    /**
     * Determine whether the user can check notification eligibility for another user.
     */
    public function checkEligibility(User $user, ?User $targetUser = null): bool
    {
        // System can always check eligibility (for jobs/observers)
        if ($targetUser === null) {
            return true;
        }

        // Users can check their own eligibility, admins can check anyone's
        return $user->isAdmin() || $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can access notification settings page.
     */
    public function accessSettingsPage(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can access notification preferences sidebar.
     */
    public function accessPreferencesSidebar(User $user): bool
    {
        return true; // All authenticated users can access their preferences
    }
}
