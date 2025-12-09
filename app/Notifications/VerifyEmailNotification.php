<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmailNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Custom email verification notification that implements ShouldQueue
 * to make email verification emails asynchronous.
 *
 * This extends Laravel's built-in VerifyEmail notification
 * and adds queue support natively.
 *
 * Rate limited to 1 email per 5 minutes per user.
 */
class VerifyEmailNotification extends BaseVerifyEmailNotification implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the notification may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the notification can run before timing out.
     */
    public int $timeout = 30;

    /**
     * Get the middleware the notification job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [
            (new RateLimited('email-verification'))
                ->releaseAfter(300), // Release after 5 minutes (300 seconds)
        ];
    }
}
