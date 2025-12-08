<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\GetNotificationPreferencesAction;
use App\Actions\UpdateNotificationPreferencesAction;
use App\Enums\NotificationGroup;
use App\Enums\NotificationType;
use App\Models\User;
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
     * Mount the component, optionally for a specific user.
     */
    public function mount(?int $userId, GetNotificationPreferencesAction $getPreferences): void
    {
        $this->targetUserId = $userId;

        $targetUser = $this->targetUser;
        if (! $targetUser) {
            return;
        }

        // Authorize: user can manage own, admins can manage any
        // Uses 'updateUserPreferences' gate defined in AuthServiceProvider
        $this->authorize('updateUserPreferences', $targetUser);

        $this->loadPreferences($getPreferences);
    }

    /**
     * Load the notification preferences for the target user.
     */
    private function loadPreferences(GetNotificationPreferencesAction $getPreferences): void
    {
        $targetUser = $this->targetUser;
        $authUser = Auth::user();

        if (! $targetUser || ! $authUser) {
            return;
        }

        $prefs = $getPreferences->execute($authUser, $targetUser->id);

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

        $grouped = [];

        // Get grouped notification types for this user
        $typesByGroup = NotificationType::groupedForUser($targetUser);

        foreach ($typesByGroup as $groupKey => $types) {
            /** @var string $groupKey */
            $group = NotificationGroup::from($groupKey);
            $groupTypes = [];

            foreach ($types as $type) {
                /** @var NotificationType $type */
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
     * Handle updates to the 'Mute Notifications' toggle.
     */
    public function updatedUserNotificationsMuted(
        bool $value,
        UpdateNotificationPreferencesAction $updatePreferences
    ): void {
        $targetUser = $this->targetUser;
        $authUser = Auth::user();

        if (! $targetUser || ! $authUser) {
            return;
        }

        $updatePreferences->muteAll($authUser, $targetUser->id, $value);
        $this->dispatch('add-toast', message: 'Notification mute setting updated.', variant: 'success');

        // Refresh computed properties
        unset($this->groupedPreferences);
    }

    /**
     * Handle updates to individual notification preferences.
     */
    public function updatedUserNotificationStates(
        bool $value,
        string $key,
        UpdateNotificationPreferencesAction $updatePreferences
    ): void {
        $targetUser = $this->targetUser;
        $authUser = Auth::user();

        if (! $targetUser || ! $authUser) {
            return;
        }

        $type = NotificationType::from($key);
        $updatePreferences->toggleType($authUser, $targetUser->id, $type, $value);

        $action = $value ? 'enabled' : 'disabled';
        $label = $type->label();
        $this->dispatch('add-toast', message: "{$label} {$action}.", variant: 'success');
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.settings.manage-notification-preferences');
    }
}
