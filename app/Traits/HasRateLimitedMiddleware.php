<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Queue\Middleware\RateLimited;

/**
 * Trait for notifications that use rate limiting middleware.
 *
 * Provides a standardized way to apply rate limiting middleware to notifications
 * that should be dropped (not released) when rate-limited. This prevents spam
 * during multiple sync cycles throughout the day.
 *
 * Notifications using this trait must define a `getRateLimiterName()` method
 * that returns the rate limiter name configured in AppServiceProvider.
 */
trait HasRateLimitedMiddleware
{
    /**
     * Get the middleware the notification job should pass through.
     *
     * Uses Laravel's native RateLimited middleware to throttle notifications.
     * Rate-limited notifications are dropped (not released) to prevent spam
     * during multiple sync cycles throughout the day.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @param  string  $channel  The notification channel
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [
            (new RateLimited($this->getRateLimiterName()))->dontRelease(),
        ];
    }

    /**
     * Get the rate limiter name for this notification.
     *
     * This method must be implemented by notifications using this trait.
     * The rate limiter must be configured in AppServiceProvider.
     *
     * @return string The rate limiter name
     */
    abstract protected function getRateLimiterName(): string;
}
