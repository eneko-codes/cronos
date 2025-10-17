<?php

return [
    /*
      |--------------------------------------------------------------------------
      | Third Party Services
      |--------------------------------------------------------------------------
      |
      | This file is for storing the credentials for third party services such
      | as Mailgun, Postmark, AWS and more. This file provides the de facto
      | location for this type of information, allowing packages to have
      | a conventional file to locate the various service credentials.
      |
      */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'odoo' => [
        'base_url' => env('ODOO_BASE_URL'),
        'database' => env('ODOO_DATABASE'),
        'username' => env('ODOO_USERNAME'),
        'password' => env('ODOO_PASSWORD'),
    ],

    'proofhub' => [
        'api_key' => env('PROOFHUB_API_KEY'),
        'company_url' => env('PROOFHUB_COMPANY_URL'),
    ],

    'desktime' => [
        'base_url' => env('DESKTIME_BASE_URL'),
        'api_key' => env('DESKTIME_API_KEY'),
    ],

    'systempin' => [
        'base_url' => env('SYSTEMPIN_BASE_URL'),
        'api_key' => env('SYSTEMPIN_API_KEY'),
        'timezone' => env('SYSTEMPIN_TIMEZONE', 'Europe/Madrid'), // Physical machine timezone
    ],
];
