<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\GlobalNotificationPreference;
use App\Models\User;
use App\Models\UserNotificationPreference;

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
     * Toggle global notifications master switch
     */
    public function toggleGlobalNotifications(bool $enabled): void
    {
        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => 'global_master'],
            ['enabled' => $enabled]
        );
    }

    /**
     * Toggle a specific notification type globally
     */
    public function toggleGlobalNotificationType(NotificationType $type, bool $enabled): void
    {
        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => $type->value],
            ['enabled' => $enabled]
        );
    }

    /**
     * Toggle user's mute all notifications setting
     */
    public function toggleUserMuteAll(User $user, bool $muteAll): void
    {
        $user->update(['muted_notifications' => $muteAll]);
    }

    /**
     * Toggle user's individual notification preference
     */
    public function toggleUserNotificationType(User $user, NotificationType $type, bool $enabled): void
    {
        UserNotificationPreference::updateOrCreate(
            [
                'user_id' => $user->id,
                'notification_type' => $type->value,
            ],
            ['enabled' => $enabled]
        );
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
