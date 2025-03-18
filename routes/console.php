<?php

use App\Models\JobFrequency;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Schedule;

/**
 * Schedule the 'telescope:prune' command to run daily and clean records (they stack up fast!).
 */
Schedule::command('telescope:prune --hours=168')->daily();

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
 */
Schedule::command('queue:restart')->hourly();

/**
 * Display an inspiring quote
 */
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Schedule synchronization jobs using the configured frequency from the database.
 * The sync command with the 'all' parameter dispatches jobs for all platforms in a single batch.
 */
try {
    // Only set up the job if the job_frequencies table exists
    if (Schema::hasTable('job_frequencies')) {
        $config = JobFrequency::getConfig();
        
        // If frequency is set to anything other than 'never', schedule it
        if ($config->frequency !== 'never') {
            $scheduleMethod = $config->getScheduleMethod();
            
            if ($scheduleMethod) {
                // Schedule a single batch containing all sync jobs
                $scheduleMethod(Schedule::command('sync all')
                    ->name('sync-scheduler')
                    ->withoutOverlapping()
                    ->onOneServer() // Only run on one server in a multi-server setup
                    ->runInBackground() // Run in background to prevent blocking scheduler
                    ->onFailure(function ($e) {
                        Log::error('Scheduled sync job failed: ' . $e->getMessage(), [
                            'trace' => $e->getTraceAsString(),
                        ]);
                    })
                    ->before(function () {
                        Log::info('Starting scheduled sync job');
                    })
                    ->after(function () {
                        Log::info('Scheduled sync job completed');
                    })
                );
            }
        }
    }
} catch (\Exception $e) {
    Log::error('Failed to schedule sync command: ' . $e->getMessage(), [
        'exception' => get_class($e),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString(),
    ]);
}

// Always schedule a daily fallback sync in case the job_frequencies configuration fails
Schedule::command('sync all')
    ->name('sync-daily-fallback')
    ->dailyAt('01:00')
    ->onOneServer()
    ->onFailure(function ($e) {
        Log::error('Daily fallback sync job failed: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString(),
        ]);
    })
    ->before(function () {
        Log::info('Starting daily fallback sync job');
    })
    ->after(function () {
        Log::info('Daily fallback sync job completed');
    });
