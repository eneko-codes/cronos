<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use Illuminate\Notifications\Notification;

/**
 * Action to send notifications to all maintenance users.
 *
 * Maintenance users are responsible for monitoring system health and data quality.
 * This action filters for active maintenance users who can receive notifications.
 */
final class NotifyMaintenanceUsersAction
{
    /**
     * Send a notification to all active maintenance users.
     *
     * @param  Notification  $notification  The notification to send
     * @param  callable|null  $shouldSendCallback  Optional callback to check if notification should be sent per user
     */
    public function execute(Notification $notification, ?callable $shouldSendCallback = null): void
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
