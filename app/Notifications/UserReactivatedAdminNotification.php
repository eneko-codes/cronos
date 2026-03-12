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
 * Notification sent to admins when a user is reactivated.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 */
class UserReactivatedAdminNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    public User $reactivatedUser;

    public ?User $performedBy;

    /**
     * Create a new notification instance.
     *
     * @param  User  $reactivatedUser  The user who was reactivated.
     * @param  User|null  $performedBy  The user who performed the reactivation action.
     */
    public function __construct(User $reactivatedUser, ?User $performedBy = null)
    {
        $this->reactivatedUser = $reactivatedUser;
        $this->performedBy = $performedBy;
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::UserReactivatedAdmin;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$this->reactivatedUser->name} has been reactivated")
            ->greeting('Hello '.$notifiable->name.',')
            ->line("{$this->reactivatedUser->name} has been reactivated and now has access to ".config('app.name').' again.');

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
        $subject = "{$this->reactivatedUser->name} has been reactivated";
        $messageLines = [
            "{$this->reactivatedUser->name} has been reactivated and now has access to ".config('app.name').' again.',
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
            ->text("{$this->reactivatedUser->name} has been reactivated")
            ->headerBlock("{$this->reactivatedUser->name} has been reactivated")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text("{$this->reactivatedUser->name} has been reactivated and now has access to ".config('app.name').' again.');
                if ($this->performedBy) {
                    $block->text("This action was performed by {$this->performedBy->name}.");
                }
                $block->text('You are receiving this notification as an administrator.');
            });
    }
}
