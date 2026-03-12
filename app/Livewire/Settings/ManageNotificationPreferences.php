<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\NotificationGroup;
use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for managing user notification preferences.
 *
 * Allows users to:
 * - Mute/unmute all personal notifications
 * - Toggle individual notification types
 *
 * Can be used for the current user (sidebar) or by admins
 * to manage another user's preferences (user details modal).
 */
class ManageNotificationPreferences extends Component
{
    /**
     * The target user ID. If null, manages the current user's preferences.
     */
    #[Locked]
    public ?int $targetUserId = null;

    /**
     * Whether notifications are muted for the user.
     */
    public bool $userNotificationsMuted = false;

    /**
     * Individual notification type states.
     *
     * @var array<string, bool>
     */
    public array $userNotificationStates = [];

    /**
     * Whether notifications are globally enabled (system-wide).
     */
    public bool $isGloballyEnabled = true;

    /**
     * Global preference states for each notification type.
     *
     * @var array<string, bool>
     */
    public array $globalPreferenceStates = [];

    /**
     * The currently active notification group tab.
     */
    public string $activeTab = 'personal';

    /**
     * Mount the component, optionally for a specific user.
     */
    public function mount(?int $userId, NotificationService $notificationService): void
    {
        $this->targetUserId = $userId;

        $targetUser = $this->targetUser;
        if (! $targetUser) {
            return;
        }

        // Authorize: user can manage own, admins can manage any
        // Uses 'updateUserPreferences' gate defined in AuthServiceProvider
        $this->authorize('updateUserPreferences', $targetUser);

        $this->loadPreferences($notificationService);
    }

    /**
     * Load the notification preferences for the target user.
     */
    private function loadPreferences(NotificationService $notificationService): void
    {
        $targetUser = $this->targetUser;
        $authUser = Auth::user();

        if (! $targetUser || ! $authUser) {
            return;
        }

        $prefs = $notificationService->getPreferences($authUser, $targetUser->id);

        $this->userNotificationsMuted = $prefs['user_mute_all'];
        $this->userNotificationStates = $prefs['user_individual'];
        $this->isGloballyEnabled = $prefs['global_enabled'];
        $this->globalPreferenceStates = $prefs['global_types'];
    }

    /**
     * Get the target user.
     */
    #[Computed]
    public function targetUser(): ?User
    {
        if ($this->targetUserId) {
            return User::find($this->targetUserId);
        }

        return Auth::user();
    }

    /**
     * Get notification preferences organized by group.
     *
     * Groups notifications into Personal, Maintenance, and Admin categories
     * for improved UI organization and user experience.
     *
     * @return array<string, array{
     *   group: NotificationGroup,
     *   label: string,
     *   description: string,
     *   types: array<string, array{
     *     label: string,
     *     description: string,
     *     isAdminOnly: bool,
     *     isMaintenanceOnly: bool,
     *     isDisabled: bool,
     *     isSpecificTypeGloballyOff: bool
     *   }>
     * }>
     */
    #[Computed]
    public function groupedPreferences(): array
    {
        $targetUser = $this->targetUser;
        if (! $targetUser) {
            return [];
        }

        // Authorize: user can view own, admins can view any
        $authUser = Auth::user();
        if ($authUser) {
            $this->authorize('viewUserPreferences', $targetUser);
        }

        $grouped = [];

        // Get grouped notification types for this user
        $typesByGroup = NotificationType::groupedForUser($targetUser);

        foreach ($typesByGroup as $groupKey => $types) {
            /** @var string $groupKey */
            $group = NotificationGroup::from($groupKey);
            $groupTypes = [];

            foreach ($types as $type) {
                /** @var NotificationType $type */
                // Skip mandatory notifications - they always send and cannot be disabled
                // Showing them would confuse users since they can't control them individually
                if ($type->isMandatory()) {
                    continue;
                }

                $isSpecificTypeGloballyOff = isset($this->globalPreferenceStates[$type->value])
                    && ! $this->globalPreferenceStates[$type->value];

                $isDisabled = ! $this->isGloballyEnabled
                    || $isSpecificTypeGloballyOff
                    || $this->userNotificationsMuted;

                $groupTypes[$type->value] = [
                    'label' => $type->label(),
                    'description' => $type->description(),
                    'isAdminOnly' => $type->isAdminOnly(),
                    'isMaintenanceOnly' => $type->isMaintenanceOnly(),
                    'isDisabled' => $isDisabled,
                    'isSpecificTypeGloballyOff' => $isSpecificTypeGloballyOff,
                ];
            }

            // Only add group if it has types
            if (count($groupTypes) > 0) {
                $grouped[$groupKey] = [
                    'group' => $group,
                    'label' => $group->label(),
                    'description' => $group->description(),
                    'types' => $groupTypes,
                ];
            }
        }

        return $grouped;
    }

    /**
     * Switch to a different notification group tab.
     */
    public function switchTab(string $tab): void
    {
        if (in_array($tab, ['personal', 'maintenance', 'admin'], true)) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Handle updates to the 'Mute Notifications' toggle.
     */
    public function updatedUserNotificationsMuted(
        bool $value,
        NotificationService $notificationService
    ): void {
        $targetUser = $this->targetUser;
        $authUser = Auth::user();

        if (! $targetUser || ! $authUser) {
            return;
        }

        // Authorize: user can manage own, admins can manage any
        $this->authorize('updateUserPreferences', $targetUser);

        $notificationService->muteUserNotifications($authUser, $targetUser->id, $value);

        $message = $value ? 'Notifications muted.' : 'Notifications unmuted.';
        $variant = $value ? 'info' : 'success';

        $this->dispatch('add-toast', message: $message, variant: $variant);

        // Refresh computed properties
        unset($this->groupedPreferences);
    }

    /**
     * Handle updates to individual notification preferences.
     */
    public function updatedUserNotificationStates(
        bool $value,
        string $key,
        NotificationService $notificationService
    ): void {
        $targetUser = $this->targetUser;
        $authUser = Auth::user();

        if (! $targetUser || ! $authUser) {
            return;
        }

        // Authorize: user can manage own, admins can manage any
        $this->authorize('updateUserPreferences', $targetUser);

        $type = NotificationType::from($key);
        $notificationService->toggleUserType($authUser, $targetUser->id, $type, $value);

        $action = $value ? 'enabled' : 'disabled';
        $label = $type->label();
        $this->dispatch('add-toast', message: "{$label} {$action}.", variant: 'success');
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.settings.manage-notification-preferences');
    }
}
