<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Traits\HasConfigurableChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Queue\Middleware\RateLimited;

/**
 * Notification sent to administrators when an external API service is down.
 *
 * This notification uses Laravel's native RateLimited middleware to prevent email spam
 * when multiple sync jobs fail simultaneously for the same platform. Rate limiting
 * is handled at the queue middleware level, following Laravel 12 best practices.
 *
 * Example scenario:
 * - Multiple SystemPin sync jobs fail (SyncSystemPinUsersJob, SyncSystemPinAttendancesJob)
 * - Each job triggers CheckSystemPinHealthAction
 * - Without rate limiting: Admins receive multiple identical emails
 * - With rate limiting: Admins receive only one email per 60-minute window per service
 *
 * @see \App\Actions\SystemPin\CheckSystemPinHealthAction
 * @see \App\Actions\Odoo\CheckOdooHealthAction
 * @see \App\Actions\Proofhub\CheckProofhubHealthAction
 * @see \App\Actions\Desktime\CheckDesktimeHealthAction
 */
class ApiDownNotification extends Notification implements ShouldQueue
{
    use HasConfigurableChannels, Queueable;

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
     * Get the notification's delivery channels.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return array<int, string> Array of channel names
     */
    public function via(object $notifiable): array
    {
        return $this->getChannels();
    }

    /**
     * Get the middleware the notification job should pass through.
     *
     * Uses Laravel's native RateLimited middleware to throttle notifications
     * per service per user. The rate limiter is configured in AppServiceProvider
     * and extracts the key from the notification job.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @param  string  $channel  The notification channel
     * @return array<int, object>
     */
    public function middleware(object $notifiable, string $channel): array
    {
        return [
            (new RateLimited('api-down-notification'))
                ->releaseAfter(3600), // Release after 60 minutes (3600 seconds)
        ];
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

    /**
     * Get the notification type enum value.
     *
     * This method is used by the notification preference system to determine
     * if a user is eligible to receive this type of notification.
     *
     * @return \App\Enums\NotificationType The ApiDown notification type
     */
    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::ApiDown;
    }
}
