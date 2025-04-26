<?php

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
            ->error() // Mark as error notification
            ->subject("🔴 API Down Alert: {$this->serviceName}")
            ->greeting('⚠️ API Service Alert')
            ->line("The {$this->serviceName} API service is currently unavailable.")
            ->line("Error details: {$this->errorMessage}")
            ->line('Time: '.now()->toDateTimeString())
            ->line(
                'This issue requires immediate attention to prevent data synchronization problems.'
            )
            ->action('View Dashboard', url('/dashboard'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'service_name' => $this->serviceName,
            'error_message' => $this->errorMessage,
            'timestamp' => now()->toDateTimeString(),
            'message' => "API Down Alert: {$this->serviceName} - {$this->errorMessage}",
            'level' => 'error', // Indicate severity
            'link' => url('/dashboard'),
        ];
    }
}
