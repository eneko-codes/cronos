<?php

use App\Jobs\SendUserLeaveReminder;
use App\Jobs\SendUserWeeklyReport;
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
 * Schedule the Weekly Report job to run every Monday at 8:00 AM
 */
Schedule::job(new SendUserWeeklyReport)
    ->weekly()
    ->mondays()
    ->at('08:00')
    ->name('Weekly User Report')
    ->onOneServer()
    ->withoutOverlapping();

/**
 * Schedule the Leave Reminder job to run daily at 8:00 AM
 */
Schedule::job(new SendUserLeaveReminder(1))
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
 * Schedule the `sync:dispatch-scheduled` command to run every minute.
 * This dispatcher command will then check the database settings and actual
 * last run time to determine if 'sync all' should be executed.
 */
Schedule::command('sync:dispatch-scheduled')
    ->everyMinute()
    ->name('Scheduled Data Synchronization Dispatcher')
    ->onOneServer()
    ->withoutOverlapping();
