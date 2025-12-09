<?php

use App\Actions\DispatchSyncBatchAction;
use App\Jobs\SendUserLeaveReminderJob;
use App\Services\SyncService;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure-based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

/**
 * Schedule the Leave Reminder job to run daily at 8:00 AM
 */
Schedule::job(new SendUserLeaveReminderJob(1))
    ->daily()
    ->at('08:00')
    ->name('Daily Leave Reminder')
    ->onOneServer()
    ->withoutOverlapping();

/**
 * Schedule daily pruning of Telescope entries.
 */
Schedule::command('telescope:prune')
    ->daily()
    ->at('23:00')
    ->name('Telescope Prune')
    ->environments('local');

/**
 * Schedule daily pruning of old job batches.
 */
Schedule::command('queue:prune-batches --hours=48')
    ->daily()
    ->name('Prune Job Batches')
    ->onOneServer()
    ->withoutOverlapping();

/**
 * Schedule daily pruning of old failed jobs.
 */
Schedule::command('queue:prune-failed --hours=48')
    ->daily()
    ->name('Prune Failed Jobs')
    ->onOneServer()
    ->withoutOverlapping();

/**
 * Schedule daily pruning of old notifications (30 days retention).
 * Uses Laravel's native model:prune command with the DatabaseNotification model.
 */
Schedule::command('model:prune', [
    '--model' => [\App\Models\DatabaseNotification::class],
])
    ->daily()
    ->at('02:00')
    ->name('Prune Old Notifications')
    ->onOneServer()
    ->withoutOverlapping();

/**
 * Schedule the Data Synchronization batch to run at the configured frequency.
 * This closure runs every minute, but only dispatches the sync batch if needed.
 */
Schedule::call(function (): void {
    $syncService = app(SyncService::class);
    if ($syncService->shouldRun()) {
        app(DispatchSyncBatchAction::class)();
    }
})->everyMinute()
    ->name('Scheduled Data Synchronization Dispatcher')
    ->onOneServer()
    ->withoutOverlapping();
