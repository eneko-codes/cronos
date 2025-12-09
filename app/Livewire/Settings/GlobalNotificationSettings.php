<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Actions\UpdateGlobalNotificationPreferencesAction;
use App\Actions\UpdateNotificationRetentionPeriodAction;
use App\Enums\NotificationGroup;
use App\Enums\NotificationRetentionPeriod;
use App\Enums\NotificationType;
use App\Models\Setting;
use App\Services\NotificationPreferenceService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Livewire component for managing global notification settings.
 *
 * Allows admins to configure:
 * - Global notifications master switch
 * - Notification channel (Email/Slack/In App Only)
 * - Notification retention period
 * - Per-type notification toggles
 */
class GlobalNotificationSettings extends Component
{
    /**
     * Whether all notifications are globally enabled.
     */
    public bool $globalNotificationsEnabled = true;

    /**
     * The global notification channel ('mail', 'slack', or 'database').
     */
    public string $notificationChannel = 'mail';

    /**
     * The notification retention period in days.
     */
    public int $notificationRetentionPeriod = 30;

    /**
     * Stores the state of global notification type toggles.
     *
     * @var array<string, bool>
     */
    public array $notificationStates = [];

    public function mount(NotificationPreferenceService $preferenceService): void
    {
        $user = Auth::user();
        if ($user) {
            $settings = $preferenceService->getPreferences($user);
            $this->globalNotificationsEnabled = $settings['global_enabled'];
            $this->notificationStates = $settings['global_types'];
            $this->notificationChannel = Setting::getValue('notification_channel', 'mail');
            $this->notificationRetentionPeriod = (int) Setting::getValue('notifications.retention_period', 30);
        }
    }

    /**
     * Get notification types that can be toggled globally, organized by group.
     *
     * Groups notifications into Personal, Maintenance, and Admin categories
     * for improved UI organization on the settings page.
     *
     * @return array<string, array{
     *   group: NotificationGroup,
     *   label: string,
     *   description: string,
     *   types: array<int, NotificationType>
     * }>
     */
    #[Computed]
    public function groupedNotificationTypes(): array
    {
        $grouped = [];

        $typesByGroup = collect(NotificationType::cases())
            ->filter(fn (NotificationType $type) => $type->canBeDisabledGlobally())
            ->groupBy(fn (NotificationType $type) => $type->group()->value)
            ->sortBy(fn (Collection $types, string $groupKey) => NotificationGroup::from($groupKey)->order());

        foreach ($typesByGroup as $groupKey => $types) {
            /** @var string $groupKey */
            $group = NotificationGroup::from($groupKey);
            $grouped[$groupKey] = [
                'group' => $group,
                'label' => $group->label(),
                'description' => $group->description(),
                'types' => $types->values()->all(),
            ];
        }

        return $grouped;
    }

    /**
     * Get notification retention options.
     *
     * @return array<int, string>
     */
    #[Computed]
    public function notificationRetentionOptions(): array
    {
        $options = [];
        foreach (NotificationRetentionPeriod::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * Handle global notifications master switch toggle.
     */
    public function updatedGlobalNotificationsEnabled(
        bool $value,
        UpdateGlobalNotificationPreferencesAction $updatePreferences
    ): void {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $updatePreferences->toggleMaster($user, $value);

            $message = $value ? 'Global notifications enabled.' : 'Global notifications disabled.';
            $variant = $value ? 'success' : 'info';

            $this->dispatch('add-toast', message: $message, variant: $variant);
            $this->dispatch('global-notifications-updated', enabled: $value);
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update setting: '.$e->getMessage(), variant: 'error');
        }
    }

    /**
     * Handle notification channel change.
     */
    public function updatedNotificationChannel(
        string $value,
        UpdateGlobalNotificationPreferencesAction $updatePreferences
    ): void {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            if (! in_array($value, ['mail', 'slack', 'database'], true)) {
                $this->dispatch('add-toast', message: 'Invalid notification channel selected.', variant: 'error');
                $this->notificationChannel = Setting::getValue('notification_channel', 'mail');

                return;
            }

            $updatePreferences->updateChannel($user, $value);

            $message = match ($value) {
                'slack' => 'Notification channel set to Slack.',
                'database' => 'Notification channel set to in app.',
                default => 'Notification channel set to Email.',
            };
            $this->dispatch('add-toast', message: $message, variant: 'success');
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update notification channel: '.$e->getMessage(), variant: 'error');
            $this->notificationChannel = Setting::getValue('notification_channel', 'mail');
        }
    }

    /**
     * Handle notification retention period change.
     */
    public function updatedNotificationRetentionPeriod(
        int $value,
        UpdateNotificationRetentionPeriodAction $updateNotificationRetention
    ): void {
        try {
            $enum = NotificationRetentionPeriod::from($value);
            $updateNotificationRetention->execute($enum);
            $this->dispatch('add-toast', message: 'Notification retention period updated.', variant: 'success');
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: 'Invalid notification retention period selected.', variant: 'error');
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update notification retention period: '.$e->getMessage(), variant: 'error');
        }
    }

    /**
     * Handle per-type global notification toggle.
     */
    public function updatedNotificationStates(
        bool $value,
        string $key,
        UpdateGlobalNotificationPreferencesAction $updatePreferences
    ): void {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        try {
            $notificationType = NotificationType::from($key);

            $updatePreferences->toggleType($user, $notificationType, $value);

            $message = $value
                ? "Global '{$notificationType->label()}' notification enabled."
                : "Global '{$notificationType->label()}' notification disabled.";
            $variant = $value ? 'success' : 'info';

            $this->dispatch('add-toast', message: $message, variant: $variant);
            $this->dispatch($key.'-global-setting-updated', enabled: $value);
        } catch (\ValueError $e) {
            $this->dispatch('add-toast', message: "Invalid notification type key: {$key}", variant: 'error');
        } catch (Exception $e) {
            $this->dispatch('add-toast', message: 'Failed to update setting: '.$e->getMessage(), variant: 'error');
        }
    }

    public function render()
    {
        return view('livewire.settings.global-notification-settings');
    }
}
