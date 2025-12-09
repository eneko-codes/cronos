<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\GlobalNotificationPreference;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Service for retrieving notification preferences and eligibility.
 *
 * Provides reusable domain logic for checking notification preferences and eligibility
 * across the application. Used by Livewire components, Observers, Actions, and Jobs.
 *
 * Used by:
 * - Sidebar Livewire component (user notification settings)
 * - Settings Livewire component (global notification settings)
 * - Observers (UserObserver, UserScheduleObserver) to check eligibility before sending notifications
 * - API health check and notification logic
 *
 * Authorization:
 * - Uses policies/gates to ensure only authorized users can view preferences.
 * - Admins can view any user's preferences; users can view their own.
 */
final class NotificationPreferenceService
{
    /**
     * Returns all notification preferences and eligibility for a user (or global if null).
     *
     * @param  User|null  $currentUser  The user making the request (for authorization)
     * @param  int|null  $userId  The target user (for user-specific preferences)
     * @return array{
     *   global_enabled: bool,
     *   global_types: array<string, bool>,
     *   user_mute_all: bool,
     *   user_individual: array<string, bool>,
     *   available_types: array,
     *   eligibility: array<string, bool>
     * }
     *
     * Returns a structured array with global and user-specific notification states, available types, and eligibility.
     */
    public function getPreferences(?User $currentUser = null, ?int $userId = null): array
    {
        $user = $userId ? User::findOrFail($userId) : $currentUser;
        if ($user && $currentUser) {
            Gate::forUser($currentUser)->authorize('viewUserPreferences', $user);
        }
        $globalEnabled = $this->areGlobalNotificationsEnabled();
        $globalTypes = $this->getGlobalNotificationTypeStates();
        $userMuteAll = $user ? (bool) $user->muted_notifications : false;
        $userIndividualPrefs = $user ? $this->getUserIndividualPreferences($user) : [];
        $availableTypes = $this->getAvailableNotificationTypes($user);
        $eligibility = [];
        foreach (NotificationType::cases() as $type) {
            $eligibility[$type->value] = $this->isEligible($type, $user);
        }

        return [
            'global_enabled' => $globalEnabled,
            'global_types' => $globalTypes,
            'user_mute_all' => $userMuteAll,
            'user_individual' => $userIndividualPrefs,
            'available_types' => $availableTypes,
            'eligibility' => $eligibility,
        ];
    }

    /**
     * Determine if a user is eligible to receive a given notification type.
     *
     * @param  NotificationType  $type  The notification type.
     * @param  User|null  $user  The user (or null for global).
     * @return bool True if eligible, false otherwise.
     */
    public function isEligible(NotificationType $type, ?User $user = null): bool
    {
        if (! $this->areGlobalNotificationsEnabled()) {
            return false;
        }
        if (! $this->isNotificationTypeGloballyEnabled($type)) {
            return false;
        }
        if (! $user) {
            return $type->defaultEnabled();
        }
        if ($user->muted_notifications) {
            return false;
        }
        if ($type->isAdminOnly() && ! $user->isAdmin()) {
            return false;
        }
        if ($type->isMaintenanceOnly() && ! $user->isMaintenance()) {
            return false;
        }
        $userPreference = $this->getUserPreferenceForType($user, $type);
        if ($userPreference !== null) {
            return $userPreference;
        }

        return $type->defaultEnabled();
    }

    /**
     * Check if global notifications are enabled (master switch).
     *
     * @return bool True if global notifications are enabled, false otherwise.
     */
    private function areGlobalNotificationsEnabled(): bool
    {
        $globalMaster = GlobalNotificationPreference::where('notification_type', 'global_master')->first();

        return $globalMaster ? (bool) $globalMaster->enabled : true;
    }

    /**
     * Get the enabled/disabled state for each notification type globally.
     *
     * @return array<string, bool> Array of notification type => enabled state.
     */
    private function getGlobalNotificationTypeStates(): array
    {
        $states = [];
        $globalPrefs = GlobalNotificationPreference::whereIn(
            'notification_type',
            collect(NotificationType::cases())->map(fn ($type) => $type->value)->toArray()
        )->pluck('enabled', 'notification_type');
        foreach (NotificationType::cases() as $type) {
            // Some notification types cannot be disabled globally
            if (! $type->canBeDisabledGlobally()) {
                $states[$type->value] = true;
            } else {
                $states[$type->value] = (bool) $globalPrefs->get($type->value, $type->defaultEnabled());
            }
        }

        return $states;
    }

    /**
     * Get the enabled/disabled state for each notification type for a specific user.
     *
     * @param  User  $user  The user whose preferences to retrieve.
     * @return array<string, bool> Array of notification type => enabled state.
     */
    private function getUserIndividualPreferences(User $user): array
    {
        $preferences = [];
        $userPrefs = $user->notificationPreferences()->pluck('enabled', 'notification_type');
        foreach (NotificationType::cases() as $type) {
            if ($type->isAdminOnly() && ! $user->isAdmin()) {
                continue;
            }
            if ($type->isMaintenanceOnly() && ! $user->isMaintenance()) {
                continue;
            }
            $preferences[$type->value] = $userPrefs->get($type->value, $type->defaultEnabled());
        }

        return $preferences;
    }

    /**
     * Get the list of notification types available to a user.
     *
     * @param  User|null  $user  The user (or null for global).
     * @return array Array of available notification types.
     */
    private function getAvailableNotificationTypes(?User $user = null): array
    {
        return NotificationType::availableForUser($user);
    }

    /**
     * Check if a notification type is globally enabled.
     *
     * @param  NotificationType  $type  The notification type.
     * @return bool True if enabled globally, false otherwise.
     */
    private function isNotificationTypeGloballyEnabled(NotificationType $type): bool
    {
        // Some notification types cannot be disabled globally (e.g., WelcomeEmail for password setup)
        if (! $type->canBeDisabledGlobally()) {
            return true;
        }

        $globalPref = GlobalNotificationPreference::where('notification_type', $type->value)->first();

        return $globalPref ? (bool) $globalPref->enabled : $type->defaultEnabled();
    }

    /**
     * Get the user-specific preference for a notification type, or null if not set.
     *
     * @param  User  $user  The user.
     * @param  NotificationType  $type  The notification type.
     * @return bool|null True/false if set, null if not set.
     */
    private function getUserPreferenceForType(User $user, NotificationType $type): ?bool
    {
        $preference = $user->notificationPreferences()->where('notification_type', $type->value)->first();

        return $preference ? $preference->enabled : null;
    }
}
