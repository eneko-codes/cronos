<?php

use App\Models\Setting;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

/**
 * Schedule the `telescope:prune` command based on the
 * 'notification.telescope_prune.value' setting (default: weekly).
 * Logs errors and throws an exception for invalid frequency settings.
 */
try {
  $frequency = Setting::getValue(
    'notification.telescope_prune.value',
    'weekly'
  );

  $scheduleTelescopePrune = Schedule::command('telescope:prune')
    ->name('Telescope Prune')
    ->withoutOverlapping();

  match ($frequency) {
    'daily' => $scheduleTelescopePrune->daily()->at('23:00'),
    'weekly' => $scheduleTelescopePrune->weekly()->at('23:00'),
    'monthly' => $scheduleTelescopePrune->monthly()->at('23:00'),
    default => throw new \InvalidArgumentException(
      'Invalid Telescope prune frequency configured: ' . $frequency
    ),
  };
} catch (\InvalidArgumentException $e) {
  Log::error(
    'Invalid Telescope prune frequency configuration: ' . $e->getMessage(),
    [
      'frequency' => $frequency ?? 'not fetched',
      'exception' => $e,
      'trace' => $e->getTraceAsString(),
    ]
  );
  throw $e; // Rethrow to make the configuration error visible.
} catch (\Exception $e) {
  // Log other scheduling errors but allow subsequent schedules to run.
  Log::error('Failed to schedule Telescope pruning: ' . $e->getMessage(), [
    'exception' => $e,
    'trace' => $e->getTraceAsString(),
  ]);
}

/**
 * Schedule daily pruning of old job batches.
 */
Schedule::command('queue:prune-batches --hours=48')->daily();

/**
 * Schedule daily pruning of old failed jobs.
 */
Schedule::command('queue:prune-failed --hours=48')->daily();

/**
 * Schedule hourly queue worker restarts to prevent memory leaks.
 * Assumes a process manager like Supervisor is restarting workers.
 */
Schedule::command('queue:restart')->hourly();

/**
 * Schedule the `sync all` command based on the 'job_frequency.sync' setting
 * (default: everyThirtyMinutes).
 * Skips scheduling if frequency is 'never'. Runs the command in the background,
 * prevents overlaps, logs errors, and throws for invalid frequency.
 */
try {
  $syncFrequency = Setting::getValue(
    'job_frequency.sync',
    'everyThirtyMinutes'
  );

  if ($syncFrequency !== 'never') {
    $scheduleSyncJobs = Schedule::command('sync all')
      ->name('Data Synchronization Scheduler')
      ->withoutOverlapping()
      ->runInBackground();

    match ($syncFrequency) {
      'everyMinute' => $scheduleSyncJobs->everyMinute(),
      'everyFiveMinutes' => $scheduleSyncJobs->everyFiveMinutes(),
      'everyFifteenMinutes' => $scheduleSyncJobs->everyFifteenMinutes(),
      'everyThirtyMinutes' => $scheduleSyncJobs->everyThirtyMinutes(),
      'hourly' => $scheduleSyncJobs->hourly(),
      'everyTwoHours' => $scheduleSyncJobs->everyTwoHours(),
      'everyThreeHours' => $scheduleSyncJobs->everyThreeHours(),
      'everyFourHours' => $scheduleSyncJobs->everyFourHours(),
      'everySixHours' => $scheduleSyncJobs->everySixHours(),
      'everyTwelveHours' => $scheduleSyncJobs->everyTwelveHours(),
      'dailyAt_9' => $scheduleSyncJobs->dailyAt('09:00'),
      'daily' => $scheduleSyncJobs->daily(),
      'weekly' => $scheduleSyncJobs->weeklyOn(7),
      'twiceMonthly' => $scheduleSyncJobs->twiceMonthly(1, 15),
      'monthly' => $scheduleSyncJobs->monthly(),
      default => throw new \InvalidArgumentException(
        'Invalid sync frequency configured in settings: ' . $syncFrequency
      ),
    };
  }
} catch (\InvalidArgumentException $e) {
  Log::error('Invalid sync frequency configuration: ' . $e->getMessage(), [
    'frequency' => $syncFrequency ?? 'not fetched',
    'exception' => $e,
    'trace' => $e->getTraceAsString(),
  ]);
  throw $e; // Rethrow to make the configuration error visible.
} catch (\Exception $e) {
  // Log other scheduling errors but allow subsequent schedules to run.
  Log::error('Failed to schedule sync jobs: ' . $e->getMessage(), [
    'frequency' => $syncFrequency ?? 'not fetched',
    'exception' => $e,
    'trace' => $e->getTraceAsString(),
  ]);
}
