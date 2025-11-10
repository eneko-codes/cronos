<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNewUserEmail extends Notification
{
    use Queueable;

    public function __construct() {}

    /**
     * The channels this notification will be delivered on.
     */
    public function via(object $notifiable): array
    {
        // Use both mail and database channels.
        return ['mail', 'database'];
    }

    /**
     * Build the mail version of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Generate secure token for password setup
        $token = hash('sha256', $notifiable->id.$notifiable->email.$notifiable->created_at->toDateTimeString());
        $setupUrl = route('password.setup', [
            'email' => $notifiable->email,
            'token' => $token,
        ]);

        return (new MailMessage)
            ->subject('Welcome to '.config('app.name')." {$notifiable->name}!")
            ->greeting("Hello {$notifiable->name},")
            ->line('You have been added to '.config('app.name').'!')
            ->line('To get started, you need to set up your account password.')
            ->line('This will allow you to access your dashboard and view your synchronized data.')
            ->action('Set Up Your Password', $setupUrl)
            ->line('This password setup link is secure and personalized to your account.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $appName = config('app.name');
        $subject = "Welcome to {$appName} {$notifiable->name}!";

        $messageLines = [
            "You have been added to {$appName}!",
            'To get started, you need to set up your account password.',
            'This will allow you to access your dashboard and view your synchronized data.',
        ];
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'info',
        ];
    }

    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::WelcomeEmail;
    }
}
