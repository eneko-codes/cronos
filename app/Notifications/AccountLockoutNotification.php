<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Traits\HandlesNotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to a user when their account is locked due to failed login attempts.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be notified
 * when their account is locked for security reasons.
 */
class AccountLockoutNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    public string $ipAddress;

    public ?string $userAgent;

    public int $lockoutDurationMinutes;

    /**
     * Create a new notification instance.
     *
     * @param  string  $ipAddress  The IP address of the failed attempts.
     * @param  string|null  $userAgent  The user agent of the failed attempts.
     * @param  int  $lockoutDurationMinutes  The duration of the lockout in minutes.
     */
    public function __construct(string $ipAddress, ?string $userAgent = null, int $lockoutDurationMinutes = 60)
    {
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->lockoutDurationMinutes = $lockoutDurationMinutes;
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::AccountLockout;
    }

    /**
     * Build the mail version of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $durationText = $this->lockoutDurationMinutes == 60 ? '1 hour' : "{$this->lockoutDurationMinutes} minutes";

        $mailMessage = (new MailMessage)
            ->subject('Security Alert: Your Account Has Been Temporarily Locked')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your account has been temporarily locked due to multiple failed login attempts.')
            ->line("**Lockout Duration:** {$durationText}")
            ->line("**IP Address:** {$this->ipAddress}");

        if ($this->userAgent) {
            $mailMessage->line("**Device:** {$this->userAgent}");
        }

        $mailMessage->line('Your account will be automatically unlocked after the lockout period expires.')
            ->line('If you did not attempt to log in, please contact your administrator immediately as your account may be compromised.');

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $durationText = $this->lockoutDurationMinutes == 60 ? '1 hour' : "{$this->lockoutDurationMinutes} minutes";

        $message = "Your account has been temporarily locked due to multiple failed login attempts.\n\n";
        $message .= "Lockout Duration: {$durationText}\n";
        $message .= "IP Address: {$this->ipAddress}\n";

        if ($this->userAgent) {
            $message .= "Device: {$this->userAgent}\n";
        }

        $message .= "\nYour account will be automatically unlocked after the lockout period expires.\n";
        $message .= 'If you did not attempt to log in, please contact your administrator immediately as your account may be compromised.';

        return [
            'subject' => 'Security Alert: Your Account Has Been Temporarily Locked',
            'message' => $message,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'lockout_duration_minutes' => $this->lockoutDurationMinutes,
            'level' => 'warning',
        ];
    }
}
