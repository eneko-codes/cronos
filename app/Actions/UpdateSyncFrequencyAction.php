<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SyncFrequencyType;
use App\Models\Setting;

class UpdateSyncFrequencyAction
{
    /**
     * Update or create the sync frequency setting.
     */
    public function execute(SyncFrequencyType $frequency): void
    {
        Setting::setValue('sync_frequency', $frequency->value);
    }
}
