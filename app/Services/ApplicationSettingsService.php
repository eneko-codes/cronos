<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Log;

class ApplicationSettingsService
{
    // General Get/Set
    public function get(string $key, mixed $default = null): mixed
    {
        return Setting::getValue($key, $default);
    }

    public function set(string $key, mixed $value): void
    {
        Setting::setValue($key, $value);
        // Optionally dispatch an event or log, though Setting::setValue already logs.
        // $this->dispatchSettingChangedEvent($key, $value);
    }

    // Specific getters/setters for known settings

    public function isGlobalNotificationsEnabled(): bool
    {
        return (bool) $this->get('notifications.global_enabled', true);
    }

    public function setGlobalNotificationsEnabled(bool $enabled): void
    {
        $this->set('notifications.global_enabled', $enabled ? '1' : '0');
        // Event for this specific setting is often dispatched by the calling Livewire component (Settings.php)
        // or could be centralized here if preferred.
    }

    public function isNotificationTypeGloballyEnabled(string $typeKey): bool
    {
        // Example: 'notification.schedule_change.enabled'
        // Note: Sidebar uses 'api_down_warning_mail' for the key part, Settings.php uses 'api_down_warning'.
        // Need to ensure consistency or handle mapping if keys differ in DB.
        // Assuming typeKey matches the pattern like 'schedule_change', 'weekly_user_report' etc.
        $dbSpecificKeyPart = $typeKey;
        if ($typeKey === 'api_down_warning') { // Specific mapping if needed for consistency
            $dbSpecificKeyPart = 'api_down_warning'; // Or 'notification.api_down_warning_mail.enabled' if that's the canonical DB key
        } elseif ($typeKey === 'admin_promotion_email') {
            $dbSpecificKeyPart = 'admin_promotion'; // Map to the part used in the DB key e.g. notification.admin_promotion.enabled
        }
        // Add other mappings if typeKey in Sidebar/preferenceKeys differs significantly from DB key structure for the specific part

        $dbKey = "notification.{$dbSpecificKeyPart}.enabled";

        return (bool) $this->get($dbKey, true);
    }

    public function setNotificationTypeGlobalState(string $typeKey, bool $enabled): void
    {
        $dbSpecificKeyPart = $typeKey;
        if ($typeKey === 'api_down_warning') {
            $dbSpecificKeyPart = 'api_down_warning';
        } elseif ($typeKey === 'admin_promotion_email') {
            $dbSpecificKeyPart = 'admin_promotion';
        }
        // Add other mappings

        $dbKey = "notification.{$dbSpecificKeyPart}.enabled";
        $this->set($dbKey, $enabled ? '1' : '0');
    }

    public function getWelcomeEmailEnabled(): bool
    {
        return (bool) $this->get('notification.welcome_email.enabled', true);
    }

    public function setWelcomeEmailEnabled(bool $enabled): void
    {
        $this->set('notification.welcome_email.enabled', $enabled ? '1' : '0');
    }

    // Consolidated getter/setter for api_down_warning
    public function getApiDownWarningEnabled(): bool
    {
        return (bool) $this->get('notification.api_down_warning.enabled', true);
    }

    public function setApiDownWarningEnabled(bool $enabled): void
    {
        $this->set('notification.api_down_warning.enabled', $enabled ? '1' : '0');
    }

    public function getAdminPromotionEmailEnabled(): bool
    {
        return (bool) $this->get('notification.admin_promotion.enabled', true);
    }

    public function setAdminPromotionEmailEnabled(bool $enabled): void
    {
        $this->set('notification.admin_promotion.enabled', $enabled ? '1' : '0');
    }

    public function getSyncFrequency(): string
    {
        return (string) $this->get('job_frequency.sync', 'everyThirtyMinutes');
    }

    public function setSyncFrequency(string $frequency): void
    {
        $this->set('job_frequency.sync', $frequency);
    }

    public function isDataRetentionEnabled(): bool
    {
        return (bool) $this->get('data_retention.enabled', false);
    }

    public function getDataRetentionGlobalPeriod(): int
    {
        return (int) $this->get('data_retention.global_period', 0);
    }

    public function setDataRetentionSettings(int $periodInDays): void
    {
        $isEnabled = $periodInDays > 0;
        $this->set('data_retention.enabled', $isEnabled ? '1' : '0');
        $this->set('data_retention.global_period', $periodInDays);
    }

    public function getUserPromotionNotificationEnabled(): bool
    {
        return (bool) $this->get('notification.user_promotion.enabled', true); // Default to true
    }

    public function setUserPromotionNotificationEnabled(bool $enabled): void
    {
        $this->set('notification.user_promotion.enabled', $enabled ? '1' : '0');
    }

    // Add other specific setting accessors as needed...
}
