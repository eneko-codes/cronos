<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Traits\HasConfigurableChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class UserPromotedToAdminNotification extends Notification implements ShouldQueue
{
    use HasConfigurableChannels, Queueable;

    /**
     * Get the notification's delivery channels.
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
        return (new MailMessage)
            ->subject('You have been promoted to administrator')
            ->greeting("Hello {$notifiable->name},")
            ->line('Congratulations! You have been promoted to an administrator role.')
            ->line('You now have access to administrative features and can manage the application.')
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
            'subject' => 'You have been promoted to administrator',
            'message' => 'Congratulations! You have been promoted to an administrator role. You now have access to administrative features and can manage the application.',
            'level' => 'info',
        ];
    }

    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::UserPromotedToAdmin;
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
            ->text('You have been promoted to administrator')
            ->headerBlock('You have been promoted to administrator')
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text('Congratulations! You have been promoted to an administrator role.');
                $block->text('You now have access to administrative features and can manage the application.');
            });
    }
}
