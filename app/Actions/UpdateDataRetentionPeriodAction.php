<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\DataRetentionPeriod;
use App\Models\Setting;

class UpdateDataRetentionPeriodAction
{
    public function execute(DataRetentionPeriod $period): void
    {
        Setting::setValue('data_retention.global_period', $period->value);
    }
}
