<?php

declare(strict_types=1);

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApiDownWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public string $serviceName;

    public string $errorMessage;

    public function __construct(string $serviceName, string $errorMessage)
    {
        $this->serviceName = $serviceName;
        $this->errorMessage = $errorMessage;
    }

    /**
     * The notification's delivery channels (here: mail).
     * You could also add 'database', 'slack', etc. if desired.
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Build the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("🔴 {$this->serviceName} API is down")
            ->greeting("Hello {$notifiable->name},")
            ->line("The {$this->serviceName} API service is currently unavailable.")
            ->line("Error details: {$this->errorMessage}")
            ->action('Open '.config('app.name'), url('/'));

    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        // Match mail subject
        $subject = "🔴 {$this->serviceName} API is down";

        // Construct message from mail lines
        $messageLines = [
            "The {$this->serviceName} API service is currently unavailable.",
            'Error details:',
            "{$this->errorMessage}",
        ];
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'error',
        ];
    }

    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::ApiDownWarning;
    }
}
