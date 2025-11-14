<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Traits\HasConfigurableChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class AdminDemotionEmail extends Notification implements ShouldQueue
{
    use HasConfigurableChannels, Queueable;

    public User $demotedUser;

    public ?User $performedBy;

    /**
     * Create a new notification instance.
     *
     * @param  User  $demotedUser  The user who was demoted from admin.
     * @param  User|null  $performedBy  The user who performed the demotion action.
     */
    public function __construct(User $demotedUser, ?User $performedBy = null)
    {
        $this->demotedUser = $demotedUser;
        $this->performedBy = $performedBy;
    }

    /**
     * Get the notification's delivery channels.
     * Note: Eligibility checks (including the specific toggle for this notification)
     * are handled centrally in User::canReceiveNotification() before dispatch.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->getChannels();
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject("{$this->demotedUser->name} has been demoted from admin")
            ->greeting('Hello '.$notifiable->name.',')
            ->line(
                "{$this->demotedUser->name} has been demoted from administrator role."
            );

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
        $subject = "{$this->demotedUser->name} has been demoted from admin";
        $messageLines = ["{$this->demotedUser->name} has been demoted from administrator role."];
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

    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::AdminDemotionEmail;
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
            ->text("{$this->demotedUser->name} has been demoted from admin")
            ->headerBlock("{$this->demotedUser->name} has been demoted from admin")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text("{$this->demotedUser->name} has been demoted from administrator role.");
                if ($this->performedBy) {
                    $block->text("This action was performed by {$this->performedBy->name}.");
                }
                $block->text('You are receiving this notification as an administrator.');
            });
    }
}
