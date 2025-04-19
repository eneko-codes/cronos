<?php

use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule as SchedulingSchedule;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Log;

/**
 * Schedule Telescope pruning based on Notification Settings.
 */
try {
  $frequency = Setting::getValue(
    'notification.telescope_prune.value',
    'weekly'
  );

  $schedule = Schedule::command('telescope:prune')
    ->name('Telescope Prune')
    ->withoutOverlapping();

  match ($frequency) {
    'daily' => $schedule->daily()->at('23:00'),
    'weekly' => $schedule->weekly()->at('23:00'),
    'monthly' => $schedule->monthly()->at('23:00'),
    default => $schedule->weekly()->at('23:00'),
  };
} catch (\Exception $e) {
  Log::error('Failed to schedule Telescope pruning: ' . $e->getMessage(), [
    'exception' => $e,
    'trace' => $e->getTraceAsString(),
  ]);
  // Optionally rethrow or handle error
}

/**
 * Schedule the 'queue:prune-batches' command to run daily and clean batched jobs.
 */
Schedule::command('queue:prune-batches --hours=48')->daily();

/**
 * Schedule the 'queue:prune-failed' command to run daily and clean failed jobs.
 */
Schedule::command('queue:prune-failed --hours=48')->daily();

/**
 * Schedule regular queue worker restart to prevent memory leaks.
 * This is important to ensure long-running queue workers don't accumulate memory.
 * Supervisor will start a new worker automatically after the command is executed.
 */
Schedule::command('queue:restart')->hourly();

/**
 * Schedule synchronization jobs using the configured frequency from the database.
 * The sync command with the 'all' parameter dispatches jobs for all platforms in a single batch.
 *
 * This scheduler:
 * 1. Checks if the settings table exists implicitly via Setting model usage.
 * 2. Retrieves the sync configuration from the new settings table.
 * 3. Schedules the sync command based on the configured frequency.
 * 4. Prevents overlapping executions.
 * 5. Runs in the background to prevent blocking.
 * 6. Includes success/failure logging.
 * 7. Validates configuration before scheduling.
 * 8. Skips scheduling in testing environment.
 *
 * @throws \Exception When scheduling fails
 * @return void
 */
try {
  /**
   * Get the job frequency configuration from the database.
   * This determines how often the sync jobs will run (hourly, daily, etc.)
   *
   * @var string $syncFrequency */
  $syncFrequency = Setting::getValue(
    'job_frequency.sync',
    'everyThirtyMinutes'
  );

  if ($syncFrequency !== 'never') {
    // Define the base event first
    $event = Schedule::command('sync all')
      ->name('Daily sync scheduler')
      ->withoutOverlapping()
      ->runInBackground();

    // Apply the frequency method directly to the event
    $isValidFrequency = true;
    match ($syncFrequency) {
      'everyMinute' => $event->everyMinute(),
      'everyFiveMinutes' => $event->everyFiveMinutes(),
      'everyFifteenMinutes' => $event->everyFifteenMinutes(),
      'everyThirtyMinutes' => $event->everyThirtyMinutes(),
      'hourly' => $event->hourly(),
      'everyTwoHours' => $event->everyTwoHours(),
      'everyThreeHours' => $event->everyThreeHours(),
      'everyFourHours' => $event->everyFourHours(),
      'everySixHours' => $event->everySixHours(),
      'everyTwelveHours' => $event->everyTwelveHours(),
      'dailyAt_9' => $event->dailyAt('09:00'),
      'daily' => $event->daily(),
      'weekly' => $event->weeklyOn(7), // Assuming Sunday is 7
      'twiceMonthly' => $event->twiceMonthly(1, 15),
      'monthly' => $event->monthly(),
      default => ($isValidFrequency = false), // Mark frequency as invalid
    };

    if (!$isValidFrequency) {
      Log::warning(
        'Invalid sync frequency configured in settings: ' .
          $syncFrequency .
          '. Sync job not scheduled with dynamic frequency.'
      );
      // Optionally, you could set a default frequency here instead of just logging
      // $event->everyThirtyMinutes(); // Example default
    }
  }
} catch (\Exception $e) {
  Log::error('Failed to schedule sync jobs: ' . $e->getMessage(), [
    'frequency' => $syncFrequency ?? 'not fetched',
    'exception' => $e,
    'trace' => $e->getTraceAsString(),
  ]);
  // Avoid throwing here to not break other schedules
}
