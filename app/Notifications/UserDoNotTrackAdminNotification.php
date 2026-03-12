<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Traits\HandlesNotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

/**
 * Notification sent to admins when a user is set to do not track.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 */
class UserDoNotTrackAdminNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    public User $affectedUser;

    public ?User $performedBy;

    /**
     * Create a new notification instance.
     *
     * @param  User  $affectedUser  The user who was set to do not track.
     * @param  User|null  $performedBy  The user who performed the action.
     */
    public function __construct(User $affectedUser, ?User $performedBy = null)
    {
        $this->affectedUser = $affectedUser;
        $this->performedBy = $performedBy;
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserDoNotTrackAdmin;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$this->affectedUser->name} has been set to do not track")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->affectedUser->name} has been set to do not track.")
            ->line('All their data (schedules, leaves, attendances, time entries, projects, tasks, and categories) has been permanently removed.')
            ->line('They will be excluded from all future synchronization operations.');

        if ($this->performedBy) {
            $message->line("This action was performed by {$this->performedBy->name}.");
        }

        return $message
            ->line('You are receiving this notification as an administrator.')
            ->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $subject = "{$this->affectedUser->name} has been set to do not track";
        $messageLines = [
            "{$this->affectedUser->name} has been set to do not track.",
            'All their data (schedules, leaves, attendances, time entries, projects, tasks, and categories) has been permanently removed.',
            'They will be excluded from all future synchronization operations.',
        ];
        if ($this->performedBy) {
            $messageLines[] = "This action was performed by {$this->performedBy->name}.";
        }
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'warning',
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
            ->text("{$this->affectedUser->name} has been set to do not track")
            ->headerBlock("{$this->affectedUser->name} has been set to do not track")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text("{$this->affectedUser->name} has been set to do not track.");
                $block->text('All their data (schedules, leaves, attendances, time entries, projects, tasks, and categories) has been permanently removed.');
                $block->text('They will be excluded from all future synchronization operations.');
                if ($this->performedBy) {
                    $block->text("This action was performed by {$this->performedBy->name}.");
                }
                $block->text('You are receiving this notification as an administrator.');
            });
    }
}
