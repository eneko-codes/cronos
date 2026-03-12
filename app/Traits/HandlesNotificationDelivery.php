<?php

declare(strict_types=1);

namespace App\Traits;

use App\Enums\NotificationType;
use App\Models\Setting;
use App\Models\User;
use App\Services\NotificationService;

/**
 * Trait for handling notification delivery based on NotificationType.
 *
 * This trait provides a unified approach to notification delivery:
 * - Mandatory notifications (isMandatory() = true): Always send via email + database (in-app)
 * - Configurable notifications: Respect user/global preferences and channel settings
 *
 * Usage:
 * - Add `use HandlesNotificationDelivery, Queueable;` to your notification class
 * - Implement the abstract `type()` method to return the NotificationType
 * - The trait handles `via()` and `shouldSend()` automatically
 *
 * @see \App\Enums\NotificationType For notification type definitions
 * @see \App\Services\NotificationService For preference checking logic
 */
trait HandlesNotificationDelivery
{
    /**
     * Get the notification type enum value.
     *
     * This method determines the notification's behavior:
     * - If type()->isMandatory() is true: Always sends via email, bypasses preferences
     * - Otherwise: Respects global/user preferences and channel settings
     */
    abstract public function type(): NotificationType;

    /**
     * Get the notification's delivery channels.
     *
     * For mandatory notifications: Returns ['database', 'mail'] (always email + in-app record)
     * For configurable notifications: Returns channels based on global settings
     *
     * @param  object  $notifiable  The entity receiving the notification
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Mandatory notifications: always email + database (for in-app visibility)
        if ($this->type()->isMandatory()) {
            return ['database', 'mail'];
        }

        // Configurable: respect global channel setting
        return $this->getConfiguredChannels();
    }

    /**
     * Determine if the notification should be sent.
     *
     * Uses Laravel's native shouldSend() method to check notification eligibility.
     * This is called automatically by Laravel before sending each notification.
     *
     * Checks (for non-mandatory notifications) are delegated to NotificationService::isEligible():
     * - User active status (archived users cannot receive notifications)
     * - Global master switch (all notifications)
     * - Global type-specific settings (admin-configurable)
     * - User mute status
     * - User role restrictions (admin-only, maintenance-only)
     * - User individual preferences
     *
     * @param  object  $notifiable  The entity receiving the notification
     * @param  string  $channel  The notification channel being checked
     * @return bool True if notification should be sent, false to skip
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        // Mandatory notifications always send
        if ($this->type()->isMandatory()) {
            return true;
        }

        // Non-User notifiables: always send (e.g., AnonymousNotifiable for testing)
        if (! $notifiable instanceof User) {
            return true;
        }

        // Ensure notification preferences are loaded to prevent N+1 queries
        $notifiable->loadMissing('notificationPreferences');

        return app(NotificationService::class)->isEligible($this->type(), $notifiable);
    }

    /**
     * Get the notification channels based on global setting.
     *
     * Reads the global notification channel setting from Settings table.
     * Always includes 'database' channel for in-app notifications.
     * Supports 'database' (in-app only), 'mail', or 'slack' channels.
     *
     * @return array<int, string> Array of channel names
     */
    protected function getConfiguredChannels(): array
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
