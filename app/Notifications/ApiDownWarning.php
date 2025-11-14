<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Notification sent to administrators when an external API service is down.
 *
 * This notification implements deduplication logic to prevent email spam when multiple
 * sync jobs fail simultaneously for the same platform. The notification uses cache-based
 * throttling with atomic locks to ensure only one notification per service per admin is
 * sent within a 60-minute window.
 *
 * Example scenario:
 * - Multiple SystemPin sync jobs fail (SyncSystempinUsersJob, SyncSystempinAttendancesJob)
 * - Each job triggers CheckSystemPinHealthAction
 * - Without deduplication: Admins receive multiple identical emails
 * - With deduplication: Admins receive only one email per 60-minute window
 *
 * @see \App\Actions\SystemPin\CheckSystemPinHealthAction
 * @see \App\Actions\Odoo\CheckOdooHealthAction
 * @see \App\Actions\Proofhub\CheckProofhubHealthAction
 * @see \App\Actions\Desktime\CheckDesktimeHealthAction
 */
class ApiDownWarning extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * The name of the API service that is down (e.g., "SystemPin", "Odoo", "ProofHub").
     */
    public string $serviceName;

    /**
     * The error message describing why the API health check failed.
     */
    public string $errorMessage;

    /**
     * Number of minutes to throttle duplicate notifications for the same service.
     *
     * After a notification is sent, subsequent notifications for the same service
     * to the same admin will be suppressed for this duration.
     */
    private const THROTTLE_MINUTES = 60;

    /**
     * Create a new API down warning notification instance.
     *
     * @param  string  $serviceName  The name of the API service that is down
     * @param  string  $errorMessage  The error message from the health check
     */
    public function __construct(string $serviceName, string $errorMessage)
    {
        $this->serviceName = $serviceName;
        $this->errorMessage = $errorMessage;
    }

    /**
     * Get the notification's delivery channels.
     *
     * This method implements deduplication logic using cache-based throttling:
     * 1. Creates a unique cache key per service and user
     * 2. Uses atomic cache locks to prevent race conditions
     * 3. Checks if a notification was already sent within the throttle window
     * 4. If yes: returns empty array (notification skipped)
     * 5. If no: sets cache and returns channels (notification sent)
     *
     * The method uses a "fail open" strategy: if cache/lock operations fail,
     * the notification is still sent to ensure critical alerts are not blocked.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return array<int, string> Array of channel names, or empty array if notification should be skipped
     */
    public function via(object $notifiable): array
    {
        $cacheKey = $this->getThrottleCacheKey($notifiable);
        $lockKey = "{$cacheKey}:lock";

        // Use cache lock for atomic operation to prevent race conditions
        $lock = Cache::lock($lockKey, 10);
        $lockAcquired = false;

        try {
            // Attempt to acquire lock with 5 second timeout
            // If lock cannot be acquired, allow notification to proceed (fail open)
            $lockAcquired = $lock->block(5);

            if ($lockAcquired) {
                // Check if we've already sent a notification for this service within the throttle window
                if (Cache::has($cacheKey)) {
                    // Notification already sent recently, skip it
                    return [];
                }

                // Mark that we're sending this notification
                Cache::put($cacheKey, true, now()->addMinutes(self::THROTTLE_MINUTES));

                return $this->getChannels();
            }

            // If lock acquisition failed, allow notification to proceed (fail open)
            // This prevents lock failures from blocking critical notifications
            return $this->getChannels();
        } catch (\Throwable $e) {
            // If any exception occurs, allow notification to proceed (fail open)
            // Log the error but don't block the notification
            Log::warning('ApiDownWarning: Cache lock error', [
                'error' => $e->getMessage(),
                'service' => $this->serviceName,
                'user_id' => $notifiable->getKey(),
            ]);

            return $this->getChannels();
        } finally {
            // Only release if lock was successfully acquired
            if ($lockAcquired) {
                $lock->release();
            }
        }
    }

    /**
     * Get the cache key for throttling this notification.
     *
     * The cache key is unique per service name and user, ensuring that:
     * - Each admin has their own throttle window
     * - Different services (SystemPin, Odoo, etc.) have separate throttles
     * - The same service can send notifications to different admins independently
     *
     * Example cache keys:
     * - "api_down_warning:systempin:user_1" (SystemPin API down for admin user ID 1)
     * - "api_down_warning:odoo:user_1" (Odoo API down for admin user ID 1)
     * - "api_down_warning:systempin:user_2" (SystemPin API down for admin user ID 2)
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return string The cache key in format: "api_down_warning:{service_slug}:user_{user_id}"
     */
    private function getThrottleCacheKey(object $notifiable): string
    {
        $serviceSlug = strtolower(str_replace(' ', '_', $this->serviceName));

        return "api_down_warning:{$serviceSlug}:user_{$notifiable->getKey()}";
    }

    /**
     * Build the mail representation of the notification.
     *
     * Creates an error-styled email message informing the admin that an external
     * API service is currently unavailable. The email includes the service name
     * and error details from the health check.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return \Illuminate\Notifications\Messages\MailMessage The mail message instance
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("{$this->serviceName} API is down")
            ->greeting("Hello {$notifiable->name},")
            ->line("The {$this->serviceName} API service is currently unavailable.")
            ->line("Error details: {$this->errorMessage}")
            ->action('Open '.config('app.name'), url('/'));

    }

    /**
     * Get the array representation of the notification for database storage.
     *
     * This method creates a structured array that is stored in the notifications
     * table when using the 'database' channel. The structure matches the mail
     * notification content for consistency.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return array<string, mixed> Array containing 'subject', 'message', and 'level' keys
     */
    public function toArray(object $notifiable): array
    {
        // Match mail subject
        $subject = "{$this->serviceName} API is down";

        // Construct message from mail lines
        $messageLines = [
            "The {$this->serviceName} API service is currently unavailable.",
            'Error details:',
            "{$this->errorMessage}",
        ];
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'error',
        ];
    }

    /**
     * Get the notification channels based on global setting.
     *
     * Reads the global notification channel setting from Settings table.
     * Always includes 'database' channel for in-app notifications.
     *
     * @return array<int, string> Array of channel names
     */
    private function getChannels(): array
    {
        $channel = Setting::getValue('notification_channel', 'mail');
        $channels = ['database']; // Always include database for in-app notifications

        if ($channel === 'slack') {
            $channels[] = 'slack';
        } else {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return \Illuminate\Notifications\Slack\SlackMessage The Slack message instance
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text("{$this->serviceName} API is down")
            ->headerBlock("{$this->serviceName} API is down")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text("The {$this->serviceName} API service is currently unavailable.");
                $block->field("*Error details:*\n{$this->errorMessage}")->markdown();
            });
    }

    /**
     * Get the notification type enum value.
     *
     * This method is used by the notification preference system to determine
     * if a user is eligible to receive this type of notification.
     *
     * @return \App\Enums\NotificationType The ApiDownWarning notification type
     */
    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::ApiDownWarning;
    }
}
