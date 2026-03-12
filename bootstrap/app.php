<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up'
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Handle expired or invalid signed URLs (Laravel framework exception)
        // This is the documented approach for framework exceptions you can't modify
        $exceptions->render(function (InvalidSignatureException $e) {
            return redirect()->route('settings')->with('toast', [
                'message' => 'This verification link has expired or is invalid. Please request a new one.',
                'variant' => 'warning',
            ]);
        });
    })
    ->create();
