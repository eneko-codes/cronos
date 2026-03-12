<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\NotificationType;
use App\Models\GlobalNotificationPreference;
use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

/**
 * Service for notification dispatching and preference management.
 *
 * This service is the single source of truth for all notification-related operations:
 *
 * ## Dispatching
 * - notifyUser() - Send to a single user
 * - notifyAdminUsers() - Send to all admin users
 * - notifyMaintenanceUsers() - Send to all maintenance users
 *
 * ## Eligibility
 * - isEligible() - Check if user can receive a notification type
 *
 * ## Preferences (Read)
 * - getPreferences() - Get all preferences for UI display
 *
 * ## Preferences (Write - Global)
 * - toggleGlobalMaster() - Enable/disable all notifications globally
 * - toggleGlobalType() - Enable/disable a specific notification type globally
 * - updateChannel() - Change the notification delivery channel
 *
 * ## Preferences (Write - User)
 * - muteUserNotifications() - Mute/unmute all notifications for a user
 * - toggleUserType() - Enable/disable a specific notification type for a user
 *
 * @see \App\Traits\HandlesNotificationDelivery For notification delivery trait
 */
final class NotificationService
{
    /**
     * Send a notification to all maintenance users.
     *
     * Maintenance users are responsible for monitoring system health and data quality.
     * Eligibility is checked by the notification's shouldSend() method.
     *
     * @param  Notification  $notification  The notification to send
     */
    public function notifyMaintenanceUsers(Notification $notification): void
    {
        $maintenanceUsers = User::query()
            ->active()
            ->maintenance()
            ->with('notificationPreferences') // Eager load to prevent N+1 queries
            ->get();

        foreach ($maintenanceUsers as $user) {
            $user->notify($notification);
        }
    }

    /**
     * Send a notification to all admin users.
     *
     * Admin users are responsible for managing the application and user roles.
     * Eligibility is checked by the notification's shouldSend() method.
     *
     * @param  Notification  $notification  The notification to send
     * @param  int|null  $excludeUserId  Optional user ID to exclude from recipients
     */
    public function notifyAdminUsers(Notification $notification, ?int $excludeUserId = null): void
    {
        $adminUsers = User::query()
            ->active()
            ->admin()
            ->with('notificationPreferences') // Eager load to prevent N+1 queries
            ->get();

        foreach ($adminUsers as $user) {
            if ($excludeUserId !== null && $user->id === $excludeUserId) {
                continue;
            }

            $user->notify($notification);
        }
    }

    /**
     * Send a notification to a single user.
     *
     * Eligibility is checked by the notification's shouldSend() method.
     * This provides a consistent entry point for all notification dispatching.
     *
     * @param  User  $user  The user to notify
     * @param  Notification  $notification  The notification to send
     */
    public function notifyUser(User $user, Notification $notification): void
    {
        $user->notify($notification);
    }

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

        // Authorize user preferences access if viewing another user
        if ($user && $currentUser) {
            Gate::forUser($currentUser)->authorize('viewUserPreferences', $user);
        }

        // Authorize global settings access - only admins/maintenance can view global settings
        // This check is only needed when accessing global settings (no userId specified)
        // Regular users can see their own preferences (which include global state for display)
        if ($currentUser && ! $userId) {
            Gate::forUser($currentUser)->authorize('viewGlobalSettings');
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

    // =========================================================================
    // PREFERENCES (WRITE - GLOBAL)
    // =========================================================================

    /**
     * Enable or disable the global master switch for all notifications.
     *
     * @param  User  $user  The admin/maintenance user performing the action (for authorization)
     * @param  bool  $enabled  Whether to enable (true) or disable (false) all notifications globally
     */
    public function toggleGlobalMaster(User $user, bool $enabled): void
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
     * @param  User  $user  The admin/maintenance user performing the action (for authorization)
     * @param  NotificationType  $type  The notification type to toggle
     * @param  bool  $enabled  Whether to enable (true) or disable (false) the notification globally
     *
     * @throws \InvalidArgumentException If notification type is mandatory
     */
    public function toggleGlobalType(User $user, NotificationType $type, bool $enabled): void
    {
        if ($type->isMandatory()) {
            throw new \InvalidArgumentException(
                "Notification type '{$type->label()}' cannot be disabled globally as it is required for security and legal compliance."
            );
        }

        Gate::forUser($user)->authorize('manageGlobalSettings');
        GlobalNotificationPreference::updateOrCreate(
            ['notification_type' => $type->value],
            ['enabled' => $enabled]
        );
    }

    /**
     * Update the global notification channel (mail, slack, or database).
     *
     * @param  User  $user  The admin/maintenance user performing the action (for authorization)
     * @param  string  $channel  The channel to use ('mail', 'slack', or 'database')
     *
     * @throws \InvalidArgumentException If channel is invalid
     */
    public function updateChannel(User $user, string $channel): void
    {
        Gate::forUser($user)->authorize('manageGlobalSettings');
        if (! in_array($channel, ['mail', 'slack', 'database'], true)) {
            throw new \InvalidArgumentException("Invalid notification channel: {$channel}. Must be 'mail', 'slack', or 'database'.");
        }
        Setting::setValue('notification_channel', $channel);
    }

    // =========================================================================
    // PREFERENCES (WRITE - USER)
    // =========================================================================

    /**
     * Mute or unmute all notifications for a user.
     *
     * @param  User  $currentUser  The user performing the action (for authorization)
     * @param  int  $userId  The target user ID
     * @param  bool  $muted  Whether to mute (true) or unmute (false) all notifications
     */
    public function muteUserNotifications(User $currentUser, int $userId, bool $muted): void
    {
        $user = User::findOrFail($userId);
        Gate::forUser($currentUser)->authorize('updateUserPreferences', $user);
        $user->update(['muted_notifications' => $muted]);
    }

    /**
     * Enable or disable a specific notification type for a user.
     *
     * @param  User  $currentUser  The user performing the action (for authorization)
     * @param  int  $userId  The target user ID
     * @param  NotificationType  $type  The notification type to toggle
     * @param  bool  $enabled  Whether to enable (true) or disable (false) the notification
     *
     * @throws AuthorizationException If notification is mandatory
     */
    public function toggleUserType(User $currentUser, int $userId, NotificationType $type, bool $enabled): void
    {
        if ($type->isMandatory()) {
            throw new AuthorizationException(
                "Notification type '{$type->label()}' cannot be disabled as it is required for security and legal compliance."
            );
        }

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

    // =========================================================================
    // ELIGIBILITY
    // =========================================================================

    /**
     * Determine if a user is eligible to receive a given notification type.
     *
     * Note: Mandatory notifications (isMandatory() = true) bypass this check
     * entirely - they are handled in HandlesNotificationDelivery::shouldSend().
     * This method is only called for configurable notifications.
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
        // Archived users cannot receive notifications
        if (! $user->is_active) {
            return false;
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
        // For non-mandatory notifications, check user preferences
        if (! $type->isMandatory()) {
            $userPreference = $this->getUserPreferenceForType($user, $type);
            if ($userPreference !== null) {
                return $userPreference;
            }
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
            // Mandatory notification types cannot be disabled globally
            if ($type->isMandatory()) {
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
            // Skip mandatory notifications - they don't have user preferences
            if ($type->isMandatory()) {
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
        // Mandatory notification types cannot be disabled globally
        if ($type->isMandatory()) {
            return true;
        }

        $globalPref = GlobalNotificationPreference::where('notification_type', $type->value)->first();

        return $globalPref ? (bool) $globalPref->enabled : $type->defaultEnabled();
    }

    /**
     * Get the user-specific preference for a notification type, or null if not set.
     *
     * Uses the already-loaded relationship collection to avoid N+1 queries
     * when checking multiple notification types for the same user.
     *
     * @param  User  $user  The user.
     * @param  NotificationType  $type  The notification type.
     * @return bool|null True/false if set, null if not set.
     */
    private function getUserPreferenceForType(User $user, NotificationType $type): ?bool
    {
        // Use the collection instead of querying to avoid N+1 when preferences are eager-loaded
        $preference = $user->notificationPreferences->firstWhere('notification_type', $type->value);

        return $preference?->enabled;
    }
}
