<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\GlobalNotificationPreference;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Facades\Gate;

class NotificationPreferenceService
{
    /**
     * Check if a user is eligible to receive a specific notification type
     */
    public function isEligibleForNotification(NotificationType $type, ?User $user = null): bool
    {
        // 1. Check if notifications are globally enabled
        if (! $this->areGlobalNotificationsEnabled()) {
            return false;
        }

        // 2. Check if this specific notification type is globally enabled
        if (! $this->isNotificationTypeGloballyEnabled($type)) {
            return false;
        }

        // 3. If no user provided, return based on global settings only
        if (! $user) {
            return $type->defaultEnabled();
        }

        // 4. Check if user has muted all notifications
        if ($user->muted_notifications) {
            return false;
        }

        // 5. Check admin-only restrictions
        if ($type->isAdminOnly() && ! $user->isAdmin()) {
            return false;
        }

        // 6. Check user's individual preference for this notification type
        $userPreference = $this->getUserPreferenceForType($user, $type);
        if ($userPreference !== null) {
            return $userPreference;
        }

        // 7. Fall back to default enabled state
        return $type->defaultEnabled();
    }

    /**
     * Get all notification preferences for a user
     */
    public function getUserNotificationPreferences(?User $user = null): array
    {
        $globalEnabled = $this->areGlobalNotificationsEnabled();
        $globalTypes = $this->getGlobalNotificationTypeStates();

        $userMuteAll = false;
        $userIndividualPrefs = [];

        if ($user) {
            $userMuteAll = (bool) $user->muted_notifications;
            $userIndividualPrefs = $this->getUserIndividualPreferences($user);
        }

        return [
            'global_enabled' => $globalEnabled,
            'global_types' => $globalTypes,
            'user_mute_all' => $userMuteAll,
            'user_individual' => $userIndividualPrefs,
            'available_types' => $this->getAvailableNotificationTypes($user),
        ];
    }

    /**
     * Toggle global notifications master switch (admin only)
     */
    public function toggleGlobalNotifications(User $user, bool $enabled): void
    {
        Gate::forUser($user)->authorize('manageGlobalSettings');

        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => 'global_master'],
            ['enabled' => $enabled]
        );
    }

    /**
     * Toggle a specific notification type globally (admin only)
     */
    public function toggleGlobalNotificationType(User $user, NotificationType $type, bool $enabled): void
    {
        Gate::forUser($user)->authorize('manageGlobalSettings');

        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => $type->value],
            ['enabled' => $enabled]
        );
    }

    /**
     * Toggle user's mute all notifications setting
     */
    public function toggleUserMuteAll(User $currentUser, int $userId, bool $muteAll): void
    {
        $user = User::findOrFail($userId);
        Gate::forUser($currentUser)->authorize('updateUserPreferences', $user);

        $user->update(['muted_notifications' => $muteAll]);
    }

    /**
     * Toggle user's individual notification preference
     */
    public function toggleUserNotificationType(User $currentUser, int $userId, NotificationType $type, bool $enabled): void
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
     * Get formatted notification preferences for a user
     */
    public function getFormattedUserPreferences(User $currentUser, int $userId): array
    {
        $user = User::findOrFail($userId);
        Gate::forUser($currentUser)->authorize('viewUserPreferences', $user);

        $preferences = $this->getUserNotificationPreferences($user);

        return [
            'user_notifications_muted' => $preferences['user_mute_all'],
            'user_notification_states' => $preferences['user_individual'],
            'global_notifications_enabled' => $preferences['global_enabled'],
            'global_notification_type_states' => $preferences['global_types'],
            'available_notification_types' => $preferences['available_types'],
        ];
    }

    /**
     * Get global notification settings (admin only)
     */
    public function getGlobalSettings(User $user): array
    {
        Gate::forUser($user)->authorize('viewGlobalSettings');

        $preferences = $this->getUserNotificationPreferences();

        return [
            'global_enabled' => $preferences['global_enabled'],
            'global_types' => $preferences['global_types'],
        ];
    }

    /**
     * Check if global notifications are enabled
     */
    public function areGlobalNotificationsEnabled(): bool
    {
        $globalMaster = GlobalNotificationPreference::where('notification_type', 'global_master')->first();

        return $globalMaster ? (bool) $globalMaster->enabled : true; // Default to enabled
    }

    /**
     * Check if a specific notification type is globally enabled
     */
    public function isNotificationTypeGloballyEnabled(NotificationType $type): bool
    {
        $globalPref = GlobalNotificationPreference::where('notification_type', $type->value)->first();

        return $globalPref ? (bool) $globalPref->enabled : $type->defaultEnabled();
    }

    /**
     * Get global notification type states
     */
    public function getGlobalNotificationTypeStates(): array
    {
        $states = [];
        $globalPrefs = GlobalNotificationPreference::whereIn(
            'notification_type',
            collect(NotificationType::cases())->map(fn ($type) => $type->value)->toArray()
        )->pluck('enabled', 'notification_type');

        foreach (NotificationType::cases() as $type) {
            $states[$type->value] = $globalPrefs->get($type->value, $type->defaultEnabled());
        }

        return $states;
    }

    /**
     * Get user's individual notification preferences
     */
    public function getUserIndividualPreferences(User $user): array
    {
        $preferences = [];
        $userPrefs = $user->notificationPreferences()
            ->pluck('enabled', 'notification_type');

        foreach (NotificationType::cases() as $type) {
            $preferences[$type->value] = $userPrefs->get($type->value, $type->defaultEnabled());
        }

        return $preferences;
    }

    /**
     * Get user's preference for a specific notification type
     */
    public function getUserPreferenceForType(User $user, NotificationType $type): ?bool
    {
        $preference = $user->notificationPreferences()
            ->where('notification_type', $type->value)
            ->first();

        return $preference ? $preference->enabled : null;
    }

    /**
     * Get notification types available to a user
     */
    public function getAvailableNotificationTypes(?User $user = null): array
    {
        return NotificationType::availableForUser($user);
    }

    /**
     * Initialize default preferences for a new user
     */
    public function initializeUserPreferences(User $user): void
    {
        foreach (NotificationType::cases() as $type) {
            // Only create preferences for types the user can access
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
