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
 * Notification sent to a user when tracking is enabled for their account.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be notified
 * when tracking is enabled for their account as this is an important
 * privacy change that affects their data.
 */
class UserTrackingEnabledNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserTrackingEnabled;
    }

    /**
     * Build the mail version of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tracking has been enabled for your account')
            ->greeting("Hello {$notifiable->name},")
            ->line('Tracking has been enabled for your account in '.config('app.name').'.')
            ->line('You will now be included in synchronization operations and your data will be tracked.')
            ->line('If you believe this is an error, please contact your administrator.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subject' => 'Tracking has been enabled for your account',
            'message' => 'Tracking has been enabled for your account in '.config('app.name').'. You will now be included in synchronization operations and your data will be tracked.',
            'level' => 'info',
        ];
    }
}
