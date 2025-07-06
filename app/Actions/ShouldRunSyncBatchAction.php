<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\SyncFrequencyType;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Action to determine if a sync batch should be run based on configured frequency and last run time.
 *
 * Used by:
 * - Console scheduler (routes/console.php) to decide if a sync should be dispatched
 *
 * Logic:
 * - Checks the configured sync frequency (from settings)
 * - Looks up the most recent sync batch in job_batches
 * - Returns true if enough time has passed since the last batch, false otherwise
 */
class ShouldRunSyncBatchAction
{
    /**
     * Returns true if a sync batch should be run now, based on frequency and last run.
     *
     * Called by the scheduler closure in routes/console.php.
     *
     * @return bool True if a sync batch should be run now, false otherwise.
     */
    public function __invoke(): bool
    {
        $frequency = Setting::getValue('sync_frequency');
        if (! $frequency) {
            return false;
        }

        // Find the most recent sync batch in job_batches
        $lastBatch = DB::table('job_batches')
            ->where('name', 'like', 'Data Sync Batch%')
            ->orderByDesc('created_at')
            ->first();

        $now = now();

        if (! $lastBatch) {
            return true;
        }

        $last = Carbon::createFromTimestamp($lastBatch->created_at);
        $diff = $last->diffInMinutes($now);

        switch ($frequency) {
            case SyncFrequencyType::Hourly->value:
                return $diff >= 60;
            case SyncFrequencyType::Daily->value:
                return $diff >= 60 * 24;
            case SyncFrequencyType::EveryMinute->value:
                return $diff >= 1;
            case SyncFrequencyType::EveryFiveMinutes->value:
                return $diff >= 5;
            case SyncFrequencyType::EveryFifteenMinutes->value:
                return $diff >= 15;
            case SyncFrequencyType::EveryThirtyMinutes->value:
                return $diff >= 30;
            case SyncFrequencyType::EveryTwoHours->value:
                return $diff >= 120;
            case SyncFrequencyType::EveryThreeHours->value:
                return $diff >= 180;
            case SyncFrequencyType::EveryFourHours->value:
                return $diff >= 240;
            case SyncFrequencyType::EverySixHours->value:
                return $diff >= 360;
            case SyncFrequencyType::EveryTwelveHours->value:
                return $diff >= 720;
            case SyncFrequencyType::DailyAt9->value:
                $todayAt9 = $now->copy()->setTime(9, 0);

                return $last < $todayAt9 && $now >= $todayAt9;
            default:
                return false;
        }
    }
}
