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
 * Notification sent to a user when their account is set to do not track.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be notified
 * when their account is set to do not track as this is an important
 * privacy change that affects their data.
 */
class UserDoNotTrackNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserDoNotTrack;
    }

    /**
     * Build the mail version of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your account has been set to do not track')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your account has been set to do not track in '.config('app.name').'.')
            ->line('All your data (schedules, leaves, attendances, time entries, projects, tasks, and categories) has been permanently removed.')
            ->line('You will be excluded from all future synchronization operations.')
            ->line('If you believe this is an error, please contact your administrator.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        return [
            'subject' => 'Your account has been set to do not track',
            'message' => 'Your account has been set to do not track in '.config('app.name').'. All your data has been permanently removed. You will be excluded from all future synchronization operations.',
            'level' => 'warning',
        ];
    }
}
