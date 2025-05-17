<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class DispatchScheduledSync extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:dispatch-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Checks sync frequency settings and dispatches the "sync all" command if due.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $syncFrequency = Setting::getValue('job_frequency.sync', 'everyThirtyMinutes');

        if ($syncFrequency === 'never') {
            $this->info('[Scheduled Sync] Sync all is set to "never". Skipping dispatch.');
            Log::info('[Scheduled Sync] Sync all is set to "never". Skipping dispatch.');

            return Command::SUCCESS;
        }

        $lastRunCacheKey = 'scheduled_sync_all_last_run_timestamp';
        $lastRunTimestamp = Cache::get($lastRunCacheKey);
        $now = Carbon::now();

        if ($lastRunTimestamp) {
            $lastRunTime = Carbon::createFromTimestamp($lastRunTimestamp);
            $minutesSinceLastRun = $now->diffInMinutes($lastRunTime);
            $requiredMinutes = $this->getMinutesForFrequency($syncFrequency);

            if ($requiredMinutes === null) {
                $this->error("[Scheduled Sync] Invalid sync frequency stored in database: {$syncFrequency}");
                Log::error("[Scheduled Sync] Invalid sync frequency stored in database: {$syncFrequency}");

                return Command::FAILURE;
            }

            if ($minutesSinceLastRun < $requiredMinutes) {
                $this->line("[Scheduled Sync] Sync all not due. Last run: {$lastRunTime->format('Y-m-d H:i:s')} ({$minutesSinceLastRun} mins ago). Required: {$requiredMinutes} mins. Skipping dispatch.");

                // No need to log this every minute if it's not an error, could be verbose.
                return Command::SUCCESS;
            }
        }

        $this->info("[Scheduled Sync] Conditions met. Dispatching 'sync all' command based on frequency: {$syncFrequency}.");
        Log::info("[Scheduled Sync] Dispatching 'sync all' command. Frequency: {$syncFrequency}, Last Run: ".($lastRunTimestamp ? Carbon::createFromTimestamp($lastRunTimestamp)->format('Y-m-d H:i:s') : 'Never'));

        // Execute the 'sync all' command
        // We run it in the background so this dispatcher command can finish quickly.
        // The 'sync all' command itself dispatches jobs to a queue.
        Artisan::call('sync all'); // Consider `Artisan::queue('sync all')` if `sync all` itself is very long and not just a dispatcher

        // If Artisan::call had an issue, it might throw an exception or return a non-zero exit code.
        // For simplicity here, we assume 'sync all' handles its own errors and logging for actual sync failures.
        // We only care if this dispatcher successfully decided to call it.

        Cache::put($lastRunCacheKey, $now->timestamp, Carbon::now()->addDays(2)); // Cache for 2 days, or longer like a week
        $this->info("[Scheduled Sync] 'sync all' command has been called. Last run timestamp updated for scheduled sync.");
        Log::info("[Scheduled Sync] 'sync all' command dispatched. Timestamp updated.");

        return Command::SUCCESS;
    }

    /**
     * Helper to convert frequency string to minutes based on App\Livewire\Settings::getSyncFrequencyOptions.
     */
    protected function getMinutesForFrequency(string $frequency): ?int
    {
        // Ensure this list matches the keys from App\Livewire\Settings getSyncFrequencyOptions
        return match ($frequency) {
            'everyMinute' => 1,
            'everyFiveMinutes' => 5,
            'everyFifteenMinutes' => 15,
            'everyThirtyMinutes' => 30,
            'hourly' => 60,
            'everyTwoHours' => 120,
            'everyThreeHours' => 180,
            'everyFourHours' => 240,
            'everySixHours' => 360,
            'everyTwelveHours' => 12 * 60,
            'dailyAt_9' => 24 * 60, // For simplicity, treat as daily. Exact time matching is complex for this model.
            'daily' => 24 * 60,
            'weekly' => 7 * 24 * 60,
            'twiceMonthly' => 15 * 24 * 60, // Approximation
            'monthly' => 30 * 24 * 60,      // Approximation
            default => null, // Indicates an unknown/unhandled frequency
        };
    }
}
