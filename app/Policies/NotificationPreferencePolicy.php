<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

/**
 * Policy for notification preference authorization.
 *
 * Methods in this policy are registered as gates in AuthServiceProvider.
 * Only add methods here that are registered as gates.
 */
class NotificationPreferencePolicy
{
    /**
     * Determine whether the user can view global notification settings.
     *
     * Both administrators and maintenance users can view global settings.
     */
    public function viewGlobalSettings(User $user): bool
    {
        return $user->isAdmin() || $user->isMaintenance();
    }

    /**
     * Determine whether the user can manage global notification settings.
     *
     * Both administrators and maintenance users can manage global settings.
     */
    public function manageGlobalSettings(User $user): bool
    {
        return $user->isAdmin() || $user->isMaintenance();
    }

    /**
     * Determine whether the user can view another user's notification preferences.
     *
     * Administrators, maintenance users, and the user themselves can view preferences.
     */
    public function viewUserPreferences(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->isMaintenance() || $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can modify another user's notification preferences.
     *
     * Administrators, maintenance users, and the user themselves can update preferences.
     */
    public function updateUserPreferences(User $user, User $targetUser): bool
    {
        return $user->isAdmin() || $user->isMaintenance() || $user->id === $targetUser->id;
    }

    /**
     * Determine whether the user can access notification settings page.
     *
     * Both administrators and maintenance users can access the settings page.
     */
    public function accessSettingsPage(User $user): bool
    {
        return $user->isAdmin() || $user->isMaintenance();
    }
}
