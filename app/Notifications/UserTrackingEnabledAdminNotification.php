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
 * Notification sent to admins when a user is removed from do not track.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 */
class UserTrackingEnabledAdminNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    public User $affectedUser;

    public ?User $performedBy;

    /**
     * Create a new notification instance.
     *
     * @param  User  $affectedUser  The user who was removed from do not track.
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
        return NotificationType::UserTrackingEnabledAdmin;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("Tracking has been enabled for {$this->affectedUser->name}")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("Tracking has been enabled for {$this->affectedUser->name}.")
            ->line('They will now be included in synchronization operations and their data will be tracked.');

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
        $subject = "Tracking has been enabled for {$this->affectedUser->name}";
        $messageLines = [
            "Tracking has been enabled for {$this->affectedUser->name}.",
            'They will now be included in synchronization operations and their data will be tracked.',
        ];
        if ($this->performedBy) {
            $messageLines[] = "This action was performed by {$this->performedBy->name}.";
        }
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'info',
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
            ->text("Tracking has been enabled for {$this->affectedUser->name}")
            ->headerBlock("Tracking has been enabled for {$this->affectedUser->name}")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text("Tracking has been enabled for {$this->affectedUser->name}.");
                $block->text('They will now be included in synchronization operations and their data will be tracked.');
                if ($this->performedBy) {
                    $block->text("This action was performed by {$this->performedBy->name}.");
                }
                $block->text('You are receiving this notification as an administrator.');
            });
    }
}
