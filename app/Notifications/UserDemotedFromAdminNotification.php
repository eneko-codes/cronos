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
 * Notification sent to a user when they are demoted from admin.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be notified
 * when they lose administrative permissions.
 */
class UserDemotedFromAdminNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserDemotedFromAdmin;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been removed from administrator role')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your administrator role has been removed.')
            ->line('You no longer have access to administrative features. If you have any questions about this change, please contact your administrator.')
            ->action('Open '.config('app.name'), url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subject' => 'You have been removed from administrator role',
            'message' => 'Your administrator role has been removed. You no longer have access to administrative features. If you have any questions about this change, please contact your administrator.',
            'level' => 'info',
        ];
    }
}
