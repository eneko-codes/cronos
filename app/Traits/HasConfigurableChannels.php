<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Setting;

/**
 * Trait for notifications that support configurable delivery channels.
 *
 * Provides a standardized way to determine notification channels based on
 * the global notification_channel setting. Always includes 'database' channel
 * for in-app notifications, plus either 'mail' or 'slack' based on configuration.
 *
 * @see \App\Models\Setting For global notification channel configuration
 */
trait HasConfigurableChannels
{
    /**
     * Get the notification channels based on global setting.
     *
     * Reads the global notification channel setting from Settings table.
     * Always includes 'database' channel for in-app notifications.
     * Supports 'database' (in-app only), 'mail', or 'slack' channels.
     *
     * @return array<int, string> Array of channel names
     */
    protected function getChannels(): array
    {
        $channel = Setting::getValue('notification_channel', 'mail');
        $channels = ['database']; // Always include database for in-app notifications

        // Only add external channel if not 'database' (in-app only)
        if ($channel === 'slack') {
            $channels[] = 'slack';
        } elseif ($channel === 'mail') {
            $channels[] = 'mail';
        }
        // If channel is 'database', only in-app notifications are sent

        return $channels;
    }
}
