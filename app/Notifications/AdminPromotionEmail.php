<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminPromotionEmail extends Notification implements ShouldQueue
{
    use Queueable;

    public User $promotedUser;

    /**
     * Create a new notification instance.
     *
     * @param  User  $promotedUser  The user who was promoted to admin.
     */
    public function __construct(User $promotedUser)
    {
        $this->promotedUser = $promotedUser;
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
        // Send via mail and store in database.
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("{$this->promotedUser->name} has been promoted to admin")
            ->greeting('Hello '.$notifiable->name.',')
            ->line(
                "{$this->promotedUser->name} has been promoted to an administrator role."
            )
            ->line('You are receiving this notification as an administrator.')
            ->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $subject = "{$this->promotedUser->name} has been promoted to admin";
        $message = "{$this->promotedUser->name} has been promoted to administrator role.";

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'info',
        ];
    }
}
