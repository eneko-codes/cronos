<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

class UserPromotedToMaintenanceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct()
    {
        //
    }

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
     * Get the notification channels based on global setting.
     *
     * Reads the global notification channel setting from Settings table.
     * Always includes 'database' channel for in-app notifications.
     *
     * @return array<int, string> Array of channel names
     */
    private function getChannels(): array
    {
        $channel = Setting::getValue('notification_channel', 'mail');
        $channels = ['database']; // Always include database for in-app notifications

        if ($channel === 'slack') {
            $channels[] = 'slack';
        } else {
            $channels[] = 'mail';
        }

        return $channels;
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

    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::UserPromotedToMaintenance;
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
            ->text('You have been promoted to maintenance role')
            ->headerBlock('You have been promoted to maintenance role')
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text('Congratulations! You have been promoted to a maintenance role.');
                $block->text('You now have access to maintenance features and can monitor system health and API status.');
            });
    }
}
