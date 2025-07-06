<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SyncWindowDays;
use App\Models\Setting;

/**
 * Action to update the data sync window (number of days to sync per batch).
 *
 * Used by:
 * - Settings Livewire component (admin changes sync window days)
 * - DispatchSyncBatchAction (reads this setting to determine date range for sync)
 *
 * This action:
 * - Updates the 'sync_window_days' setting in the database
 */
class UpdateSyncWindowDaysAction
{
    /**
     * Update the sync window days setting.
     *
     * @param  SyncWindowDays  $windowDays  The new sync window (number of days)
     */
    public function execute(SyncWindowDays $windowDays): void
    {
        Setting::setValue('sync_window_days', $windowDays->value);
    }
}
