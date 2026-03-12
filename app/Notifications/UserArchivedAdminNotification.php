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
 * Notification sent to admins when a user is archived.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 */
class UserArchivedAdminNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    public User $archivedUser;

    public ?User $performedBy;

    /**
     * Create a new notification instance.
     *
     * @param  User  $archivedUser  The user who was archived.
     * @param  User|null  $performedBy  The user who performed the archive action.
     */
    public function __construct(User $archivedUser, ?User $performedBy = null)
    {
        $this->archivedUser = $archivedUser;
        $this->performedBy = $performedBy;
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserArchivedAdmin;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$this->archivedUser->name} has been archived")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->archivedUser->name} has been archived.")
            ->line('All their data (schedules, leaves, attendances, time entries, projects, tasks, and categories) has been permanently removed.');

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
        $subject = "{$this->archivedUser->name} has been archived";
        $messageLines = [
            "{$this->archivedUser->name} has been archived.",
            'All their data (schedules, leaves, attendances, time entries, projects, tasks, and categories) has been permanently removed.',
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
            ->text("{$this->archivedUser->name} has been archived")
            ->headerBlock("{$this->archivedUser->name} has been archived")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text("{$this->archivedUser->name} has been archived.");
                $block->text('All their data (schedules, leaves, attendances, time entries, projects, tasks, and categories) has been permanently removed.');
                if ($this->performedBy) {
                    $block->text("This action was performed by {$this->performedBy->name}.");
                }
                $block->text('You are receiving this notification as an administrator.');
            });
    }
}
