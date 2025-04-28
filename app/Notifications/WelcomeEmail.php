<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct()
    {
    }

    /**
     * The channels this notification will be delivered on.
     *
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
        return (new MailMessage)
            ->subject("Welcome to " . config('app.name') . " {$notifiable->name}!")
            ->greeting("Hello {$notifiable->name},")
            ->line("You have been added to " . config('app.name') . '!')
            ->line(
                "You can log in using your work email: {$notifiable->email}. You'll receive a magic login link."
            )
            ->action("Open " . config('app.name'), route('login'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray(object $notifiable): array
    {
        $appName = config('app.name');
        $subject = "Welcome to {$appName} {$notifiable->name}!";
        
        $messageLines = [
            "You have been added to {$appName}!",
            "You can log in using your work email: {$notifiable->email}.",
            "You'll receive a magic login link."
        ];
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'info',
        ];
    }
}
