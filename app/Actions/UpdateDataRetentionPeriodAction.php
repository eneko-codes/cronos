<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DataRetentionPeriod;
use App\Models\Setting;

/**
 * Action to update the global data retention period setting.
 *
 * Used by:
 * - Settings Livewire component (admin changes data retention period)
 * - Console command for purging old time data (reads this setting)
 *
 * This action:
 * - Updates the 'data_retention.global_period' setting in the database
 */
class UpdateDataRetentionPeriodAction
{
    /**
     * Update the global data retention period setting.
     *
     * @param  DataRetentionPeriod  $period  The new retention period
     */
    public function execute(DataRetentionPeriod $period): void
    {
        Setting::setValue('data_retention.global_period', $period->value);
    }
}
