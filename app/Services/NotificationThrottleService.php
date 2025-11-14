<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service for throttling notifications to prevent duplicate sends.
 *
 * Provides cache-based throttling with atomic locks to ensure only one notification
 * per throttle window is sent. Uses a "fail open" strategy to ensure critical
 * notifications are never blocked by cache/lock failures.
 *
 * Example usage:
 * ```php
 * $service = app(NotificationThrottleService::class);
 * if ($service->shouldSend($user, 'api_down_warning', 'odoo', 60)) {
 *     $user->notify(new ApiDownWarning('Odoo', 'API is down'));
 * }
 * ```
 */
class NotificationThrottleService
{
    /**
     * Check if a notification should be sent based on throttling rules.
     *
     * This method implements deduplication logic using cache-based throttling:
     * 1. Creates a unique cache key per notification type, identifier, and user
     * 2. Uses atomic cache locks to prevent race conditions
     * 3. Checks if a notification was already sent within the throttle window
     * 4. If yes: returns false (notification should be skipped)
     * 5. If no: sets cache and returns true (notification should be sent)
     *
     * The method uses a "fail open" strategy: if cache/lock operations fail,
     * the notification is still sent to ensure critical alerts are not blocked.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @param  string  $notificationType  The type of notification (e.g., 'api_down_warning')
     * @param  string  $identifier  Unique identifier for this specific notification instance (e.g., service name)
     * @param  int  $throttleMinutes  Number of minutes to throttle duplicate notifications
     * @return bool True if notification should be sent, false if it should be skipped
     */
    public function shouldSend(object $notifiable, string $notificationType, string $identifier, int $throttleMinutes): bool
    {
        $cacheKey = $this->getThrottleCacheKey($notifiable, $notificationType, $identifier);
        $lockKey = "{$cacheKey}:lock";

        // Use cache lock for atomic operation to prevent race conditions
        $lock = Cache::lock($lockKey, 10);
        $lockAcquired = false;

        try {
            // Attempt to acquire lock with 5 second timeout
            // If lock cannot be acquired, allow notification to proceed (fail open)
            $lockAcquired = $lock->block(5);

            if ($lockAcquired) {
                // Check if we've already sent a notification within the throttle window
                if (Cache::has($cacheKey)) {
                    // Notification already sent recently, skip it
                    return false;
                }

                // Mark that we're sending this notification
                Cache::put($cacheKey, true, now()->addMinutes($throttleMinutes));

                return true;
            }

            // If lock acquisition failed, allow notification to proceed (fail open)
            // This prevents lock failures from blocking critical notifications
            return true;
        } catch (\Throwable $e) {
            // If any exception occurs, allow notification to proceed (fail open)
            // Log the error but don't block the notification
            Log::warning('NotificationThrottleService: Cache lock error', [
                'error' => $e->getMessage(),
                'notification_type' => $notificationType,
                'identifier' => $identifier,
                'user_id' => $notifiable->getKey(),
            ]);

            return true;
        } finally {
            // Only release if lock was successfully acquired
            if ($lockAcquired) {
                $lock->release();
            }
        }
    }

    /**
     * Get the cache key for throttling a notification.
     *
     * The cache key is unique per notification type, identifier, and user, ensuring that:
     * - Each user has their own throttle window
     * - Different notification types have separate throttles
     * - Different identifiers (e.g., service names) have separate throttles
     *
     * Example cache keys:
     * - "notification_throttle:api_down_warning:odoo:user_1"
     * - "notification_throttle:api_down_warning:systempin:user_1"
     * - "notification_throttle:schedule_change:user_1"
     *
     * @param  object  $notifiable  The user receiving the notification
     * @param  string  $notificationType  The type of notification
     * @param  string  $identifier  Unique identifier for this notification instance
     * @return string The cache key in format: "notification_throttle:{type}:{identifier}:user_{user_id}"
     */
    private function getThrottleCacheKey(object $notifiable, string $notificationType, string $identifier): string
    {
        $typeSlug = strtolower(str_replace(' ', '_', $notificationType));
        $identifierSlug = strtolower(str_replace(' ', '_', $identifier));

        return "notification_throttle:{$typeSlug}:{$identifierSlug}:user_{$notifiable->getKey()}";
    }
}
