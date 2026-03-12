<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SyncFrequencyType;
use App\Models\Setting;

/**
 * Action to update the data sync frequency setting.
 *
 * Used by:
 * - Settings Livewire component (admin changes sync frequency)
 * - Console scheduler (reads this setting to determine when to run syncs)
 *
 * This action:
 * - Updates the 'sync_frequency' setting in the database
 */
class UpdateSyncFrequencyAction
{
    /**
     * Update or create the sync frequency setting.
     *
     * @param  SyncFrequencyType  $frequency  The new sync frequency
     */
    public function execute(SyncFrequencyType $frequency): void
    {
        Setting::setValue('sync_frequency', $frequency->value);
    }
}
