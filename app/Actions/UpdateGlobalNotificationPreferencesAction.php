<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\GlobalNotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Action to update global (system-wide) notification preferences.
 *
 * Used by:
 * - Settings Livewire component (admin toggles for global notification settings)
 * - Feature tests for global notification logic
 *
 * Methods:
 * - toggleMaster: Enable/disable the global master switch for all notifications
 * - toggleType: Enable/disable a specific notification type globally
 *
 * Authorization:
 * - Only admins (via policy/gate) can update global notification settings.
 */
class UpdateGlobalNotificationPreferencesAction
{
    /**
     * Enable or disable the global master switch for all notifications.
     *
     * @param  User  $user  The admin performing the action (for authorization)
     * @param  bool  $enabled  Whether to enable (true) or disable (false) all notifications globally
     */
    public function toggleMaster(User $user, bool $enabled): void
    {
        Gate::forUser($user)->authorize('manageGlobalSettings');
        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => 'global_master'],
            ['enabled' => $enabled]
        );
    }

    /**
     * Enable or disable a specific notification type globally.
     *
     * @param  User  $user  The admin performing the action (for authorization)
     * @param  NotificationType  $type  The notification type to toggle
     * @param  bool  $enabled  Whether to enable (true) or disable (false) the notification globally
     */
    public function toggleType(User $user, NotificationType $type, bool $enabled): void
    {
        Gate::forUser($user)->authorize('manageGlobalSettings');
        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => $type->value],
            ['enabled' => $enabled]
        );
    }
}
