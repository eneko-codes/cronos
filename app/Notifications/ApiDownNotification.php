<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Traits\HandlesNotificationDelivery;
use App\Traits\HasRateLimitedMiddleware;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

/**
 * Notification sent to maintenance users when an external API service is down.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 *
 * Uses HasRateLimitedMiddleware for rate limiting (60 minutes per service per user).
 *
 * Example scenario:
 * - Multiple SystemPin sync jobs fail (SyncSystemPinUsersJob, SyncSystemPinAttendancesJob)
 * - Each job triggers CheckSystemPinHealthAction
 * - Without rate limiting: Users receive multiple identical emails
 * - With rate limiting: Users receive only one email per 60-minute window per service
 * - Rate-limited notifications are dropped (not released) to prevent spam during sync cycles
 *
 * @see \App\Actions\SystemPin\CheckSystemPinHealthAction
 * @see \App\Actions\Odoo\CheckOdooHealthAction
 * @see \App\Actions\Proofhub\CheckProofhubHealthAction
 * @see \App\Actions\Desktime\CheckDesktimeHealthAction
 */
class ApiDownNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, HasRateLimitedMiddleware, Queueable;

    /**
     * The name of the API service that is down (e.g., "SystemPin", "Odoo", "ProofHub").
     */
    public string $serviceName;

    /**
     * The error message describing why the API health check failed.
     */
    public string $errorMessage;

    /**
     * Create a new API down notification instance.
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
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::ApiDown;
    }

    /**
     * Get the rate limiter name for this notification.
     *
     * @return string The rate limiter name configured in AppServiceProvider
     */
    protected function getRateLimiterName(): string
    {
        return 'api-down-notification';
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
}
