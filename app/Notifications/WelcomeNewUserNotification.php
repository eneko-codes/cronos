<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;

class WelcomeNewUserNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct() {}

    /**
     * The channels this notification will be delivered on.
     *
     * This notification always sends via email only, regardless of global notification preferences.
     * It is required for new users to set up their password.
     */
    public function via(object $notifiable): array
    {
        // Only send via email channel - this notification must always be sent.
        return ['mail'];
    }

    /**
     * Build the mail version of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        // Use Laravel's password reset token system for secure, expiring tokens
        $token = Password::createToken($notifiable);
        $setupUrl = route('password.setup', [
            'token' => $token,
            'email' => $notifiable->email,
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
        return \App\Enums\NotificationType::WelcomeNewUser;
    }
}
