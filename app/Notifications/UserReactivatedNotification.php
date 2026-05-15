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
 * Notification sent to a user when their account is reactivated.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be notified
 * when their account is reactivated.
 */
class UserReactivatedNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserReactivated;
    }

    /**
     * Build the mail version of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been reactivated')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your account has been reactivated and you now have access to '.config('app.name').' again.')
            ->line('You can log in using your existing password.')
            ->action('Log In', route('login'))
            ->line('Welcome back!');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subject' => 'Your account has been reactivated',
            'message' => 'Your account has been reactivated and you now have access to '.config('app.name').' again. You can log in using your existing password.',
            'level' => 'success',
        ];
    }
}
