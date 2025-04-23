<?php

use App\Jobs\SendUserLeaveReminder;
use App\Jobs\SendUserWeeklyReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware) {
        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withSchedule(function (Schedule $schedule) {
        $schedule
            ->job(new SendUserWeeklyReport)
            ->weekly()
            ->mondays()
            ->at('08:00');
        $schedule->job(new SendUserLeaveReminder(1))->daily()->at('09:00');
    })
    ->create();
