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
 * Notification sent to a user when they are promoted to maintenance.
 *
 * This is a MANDATORY notification - it ALWAYS sends via email only,
 * bypassing all notification preferences. Users must be notified
 * when they receive new maintenance permissions.
 */
class UserPromotedToMaintenanceNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserPromotedToMaintenance;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been promoted to maintenance role')
            ->greeting("Hello {$notifiable->name},")
            ->line('Congratulations! You have been promoted to a maintenance role.')
            ->line('You now have access to maintenance features and can monitor system health and API status.')
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
            'subject' => 'You have been promoted to maintenance role',
            'message' => 'Congratulations! You have been promoted to a maintenance role. You now have access to maintenance features and can monitor system health and API status.',
            'level' => 'info',
        ];
    }
}
