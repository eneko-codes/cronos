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
            ->subject('User Promoted to Administrator')
            ->greeting('Hello Admin,') // Generic greeting for admins
            ->line(
                "The user '{$this->promotedUser->name}' ({$this->promotedUser->email}) has been promoted to an administrator role."
            )
            ->line('You are receiving this notification as an administrator.')
            ->action('View Users', url('/users')); // Link to user management page
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'promoted_user_id' => $this->promotedUser->id,
            'promoted_user_name' => $this->promotedUser->name,
            'promoted_user_email' => $this->promotedUser->email,
            'message' => "User '{$this->promotedUser->name}' was promoted to admin.",
            'link' => url('/users'), // Or a specific link to the user's profile
        ];
    }
}
