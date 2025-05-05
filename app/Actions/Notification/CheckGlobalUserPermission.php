<?php

declare(strict_types=1);

namespace App\Actions\Notification;

use App\Models\Setting;
use App\Models\User;

class CheckGlobalUserPermission
{
    /**
     * Check if notifications are generally enabled for the user.
     *
     * Considers global settings and the user's master mute preference.
     *
     * @return bool Returns false if notifications are globally disabled or user muted all, true otherwise.
     */
    public function handle(User $user): bool
    {
        // First, check if notifications are globally disabled by an administrator.
        $isGloballyEnabled = (bool) Setting::getValue(
            'notifications.global_enabled',
            true
        ); // Default to true if setting doesn't exist
        if (! $isGloballyEnabled) {
            return false; // Globally disabled.
        }

        // Next, check the user's specific preference to mute all their notifications.
        $preferences = $user->notificationPreferences; // Relies on withDefault()

        // If preferences record exists and mute_all is true, user has opted out.
        if ($preferences && $preferences->mute_all) {
            return false;
        }

        // If globally enabled and user hasn't muted all, they might receive notifications.
        return true;
    }
}
