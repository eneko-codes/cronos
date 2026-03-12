<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationRetentionPeriod;
use App\Models\Setting;

/**
 * Action to update the notification retention period setting.
 *
 * Used by:
 * - Settings Livewire component (admin changes notification retention period)
 * - DatabaseNotification model prunable() method (reads this setting)
 *
 * This action:
 * - Updates the 'notifications.retention_period' setting in the database
 */
class UpdateNotificationRetentionPeriodAction
{
    /**
     * Update the notification retention period setting.
     *
     * @param  NotificationRetentionPeriod  $period  The new retention period
     */
    public function execute(NotificationRetentionPeriod $period): void
    {
        Setting::setValue('notifications.retention_period', $period->value);
    }
}
