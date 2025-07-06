<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Facades\Gate;

/**
 * Action to update (enable/disable) user-level notification preferences.
 *
 * Used by:
 * - Sidebar Livewire component (user toggles for notifications)
 * - UserObserver (to initialize preferences for new users)
 * - Feature tests for notification preference logic
 *
 * Methods:
 * - muteAll: Mute/unmute all notifications for a user (sets muted_notifications on User)
 * - toggleType: Enable/disable a specific notification type for a user
 * - initialize: Create default notification preferences for a new user (called on user creation)
 *
 * Authorization:
 * - Uses policies/gates to ensure only authorized users can update preferences.
 * - Admins can update any user's preferences; users can update their own.
 */
class UpdateNotificationPreferencesAction
{
    /**
     * Mute or unmute all notifications for a user.
     *
     * @param  User  $currentUser  The user performing the action (for authorization)
     * @param  int  $userId  The target user ID
     * @param  bool  $muteAll  Whether to mute (true) or unmute (false) all notifications
     */
    public function muteAll(User $currentUser, int $userId, bool $muteAll): void
    {
        $user = User::findOrFail($userId);
        Gate::forUser($currentUser)->authorize('updateUserPreferences', $user);
        $user->update(['muted_notifications' => $muteAll]);
    }

    /**
     * Enable or disable a specific notification type for a user.
     *
     * @param  User  $currentUser  The user performing the action (for authorization)
     * @param  int  $userId  The target user ID
     * @param  NotificationType  $type  The notification type to toggle
     * @param  bool  $enabled  Whether to enable (true) or disable (false) the notification
     */
    public function toggleType(User $currentUser, int $userId, NotificationType $type, bool $enabled): void
    {
        $user = User::findOrFail($userId);
        Gate::forUser($currentUser)->authorize('updateUserPreferences', $user);
        UserNotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_type' => $type->value,
            ],
            ['enabled' => $enabled]
        );
    }

    /**
     * Initialize default notification preferences for a new user.
     *
     * Called by UserObserver when a user is created.
     *
     * @param  User  $user  The new user
     */
    public function initialize(User $user): void
    {
        foreach (NotificationType::cases() as $type) {
            if (! $type->isAdminOnly() || $user->isAdmin()) {
                UserNotificationPreference::firstOrCreate(
                    [
                        'user_id' => $user->id,
                        'notification_type' => $type->value,
                    ],
                    ['enabled' => $type->defaultEnabled()]
                );
            }
        }
    }
}
