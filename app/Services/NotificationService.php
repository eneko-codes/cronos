<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Service for notification-related business logic.
 *
 * Provides reusable domain logic for sending notifications to specific
 * user groups and managing notification delivery.
 *
 * Used by:
 * - Process*UserAction classes to notify about unlinked users
 * - Other parts of the application that need to send notifications to maintenance users
 */
final class NotificationService
{
    /**
     * Send a notification to all active maintenance users.
     *
     * Maintenance users are responsible for monitoring system health and data quality.
     * This method filters for active maintenance users who can receive notifications.
     *
     * @param  Notification  $notification  The notification to send
     * @param  callable|null  $shouldSendCallback  Optional callback to check if notification should be sent per user
     */
    public function notifyMaintenanceUsers(Notification $notification, ?callable $shouldSendCallback = null): void
    {
        $maintenanceUsers = User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user) => $user->isMaintenance());

        foreach ($maintenanceUsers as $user) {
            // Skip if user can't receive email notifications
            if (! $user->canReceiveEmailNotifications()) {
                continue;
            }

            // Check custom shouldSend callback if provided
            if ($shouldSendCallback !== null && ! $shouldSendCallback($user)) {
                continue;
            }

            $user->notify($notification);
        }
    }
}
