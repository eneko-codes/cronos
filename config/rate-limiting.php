<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for rate limiting across the application.
    | Rate limits help protect your application from abuse and brute-force attacks.
    |
    | You may configure different rate limits for different routes and scenarios.
    | These values can be overridden via environment variables for flexibility
    | across different environments.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Login Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Controls the rate limiting for login attempts. This helps prevent
    | brute-force attacks on user authentication.
    |
    */

    'login' => [
        'max_attempts' => env('LOGIN_RATE_LIMIT_MAX_ATTEMPTS', 4),
        'decay_minutes' => env('LOGIN_RATE_LIMIT_DECAY_MINUTES', 5),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Controls the rate limiting for admin routes. Admin users typically
    | have higher rate limits for administrative operations.
    |
    */

    'admin' => [
        'max_attempts' => env('ADMIN_RATE_LIMIT_MAX_ATTEMPTS', 60),
        'decay_minutes' => env('ADMIN_RATE_LIMIT_DECAY_MINUTES', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | Web Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Controls the general rate limiting for authenticated web routes.
    |
    */

    'web' => [
        'max_attempts' => env('WEB_RATE_LIMIT_MAX_ATTEMPTS', 30),
        'decay_minutes' => env('WEB_RATE_LIMIT_DECAY_MINUTES', 1),
    ],

];
