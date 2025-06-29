<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SyncWindowDays;
use App\Models\Setting;

class UpdateSyncWindowDaysAction
{
    public function execute(SyncWindowDays $windowDays): void
    {
        Setting::setValue('sync_window_days', $windowDays->value);
    }
}
