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
 * Notification sent to maintenance users when multiple failed login attempts occur.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 *
 * Uses HasRateLimitedMiddleware for rate limiting (60 minutes per email+IP per user).
 * This prevents spam when multiple failed attempts occur in quick succession.
 */
class FailedLoginAttemptNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, HasRateLimitedMiddleware, Queueable;

    public string $email;

    public int $attemptCount;

    public string $ipAddress;

    public ?string $userAgent;

    /**
     * Create a new notification instance.
     *
     * @param  string  $email  The email address that failed to login.
     * @param  int  $attemptCount  The number of failed attempts.
     * @param  string  $ipAddress  The IP address of the failed attempt.
     * @param  string|null  $userAgent  The user agent of the failed attempt.
     */
    public function __construct(string $email, int $attemptCount, string $ipAddress, ?string $userAgent = null)
    {
        $this->email = $email;
        $this->attemptCount = $attemptCount;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::FailedLoginAttempt;
    }

    /**
     * Get the rate limiter name for this notification.
     *
     * @return string The rate limiter name configured in RateLimitServiceProvider
     */
    protected function getRateLimiterName(): string
    {
        return 'failed-login-attempt-notification';
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Security Alert: Multiple Failed Login Attempts')
            ->greeting("Hello {$notifiable->name},")
            ->line('Multiple failed login attempts have been detected.')
            ->line("**Email:** {$this->email}")
            ->line("**Failed Attempts:** {$this->attemptCount}")
            ->line("**IP Address:** {$this->ipAddress}");

        if ($this->userAgent) {
            $mailMessage->line("**User Agent:** {$this->userAgent}");
        }

        $mailMessage->line('This could indicate a potential security threat or brute-force attack.');

        return $mailMessage->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $message = "Multiple failed login attempts have been detected.\n\n";
        $message .= "Email: {$this->email}\n";
        $message .= "Failed Attempts: {$this->attemptCount}\n";
        $message .= "IP Address: {$this->ipAddress}\n";

        if ($this->userAgent) {
            $message .= "User Agent: {$this->userAgent}\n";
        }

        $message .= "\nThis could indicate a potential security threat or brute-force attack.";

        return [
            'subject' => 'Security Alert: Multiple Failed Login Attempts',
            'message' => $message,
            'email' => $this->email,
            'attempt_count' => $this->attemptCount,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'level' => 'warning',
        ];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        return (new SlackMessage)
            ->text('Security Alert: Multiple Failed Login Attempts')
            ->headerBlock('Security Alert: Multiple Failed Login Attempts')
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
                $block->text('Multiple failed login attempts have been detected.');
            })
            ->sectionBlock(function ($block): void {
                $block->text("**Email:** {$this->email}");
                $block->text("**Failed Attempts:** {$this->attemptCount}");
                $block->text("**IP Address:** {$this->ipAddress}");
                if ($this->userAgent) {
                    $block->text("**User Agent:** {$this->userAgent}");
                }
                $block->text('This could indicate a potential security threat or brute-force attack.');
            });
    }
}
