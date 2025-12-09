<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SyncFrequencyType;
use App\Models\Setting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for sync-related business logic and queries.
 *
 * Provides reusable domain logic for sync operations, including
 * determining when sync batches should run based on configured frequency.
 *
 * Used by:
 * - Console scheduler (routes/console.php) to decide if a sync should be dispatched
 */
final class SyncService
{
    /**
     * Determine if a sync batch should be run based on configured frequency and last run time.
     *
     * Logic:
     * - Checks the configured sync frequency (from settings)
     * - Looks up the most recent sync batch in job_batches
     * - Returns true if enough time has passed since the last batch, false otherwise
     *
     * @return bool True if a sync batch should be run now, false otherwise.
     */
    public function shouldRun(): bool
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

        return match ($frequency) {
            SyncFrequencyType::Hourly->value => $diff >= 60,
            SyncFrequencyType::Daily->value => $diff >= 60 * 24,
            SyncFrequencyType::EveryMinute->value => $diff >= 1,
            SyncFrequencyType::EveryFiveMinutes->value => $diff >= 5,
            SyncFrequencyType::EveryFifteenMinutes->value => $diff >= 15,
            SyncFrequencyType::EveryThirtyMinutes->value => $diff >= 30,
            SyncFrequencyType::EveryTwoHours->value => $diff >= 120,
            SyncFrequencyType::EveryThreeHours->value => $diff >= 180,
            SyncFrequencyType::EveryFourHours->value => $diff >= 240,
            SyncFrequencyType::EverySixHours->value => $diff >= 360,
            SyncFrequencyType::EveryTwelveHours->value => $diff >= 720,
            SyncFrequencyType::DailyAt9->value => $this->shouldRunDailyAt9($last, $now),
            default => false,
        };
    }

    /**
     * Check if sync should run for DailyAt9 frequency.
     *
     * @param  Carbon  $last  The last sync time
     * @param  Carbon  $now  The current time
     * @return bool True if sync should run
     */
    private function shouldRunDailyAt9(Carbon $last, Carbon $now): bool
    {
        $todayAt9 = $now->copy()->setTime(9, 0);

        return $last < $todayAt9 && $now >= $todayAt9;
    }
}
