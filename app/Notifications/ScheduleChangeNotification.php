<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ScheduleChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public User $user;

    public string $changeDetails;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user  The user whose schedule changed.
     * @param  string  $changeDetails  Description of the change.
     */
    public function __construct(User $user, string $changeDetails)
    {
        $this->user = $user;
        $this->changeDetails = $changeDetails;
    }

    /**
     * Get the notification's delivery channels.
     * Note: Eligibility checks are handled centrally in User::canReceiveNotification().
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Work Schedule Has Been Updated')
            ->greeting("Hello {$this->user->name},")
            ->line('Your assigned work schedule has been updated.')
            ->line("Details: {$this->changeDetails}")
            ->action('View Schedule', url('/schedule'));
    }

    /**
     * Get the array representation of the notification.
     * (Optional: Define if you want to store in 'database' channel)
     *
     * @return array<string, mixed>
     */
    // public function toArray(object $notifiable): array
    // {
    //     return [
    //         'user_id' => $this->user->id,
    //         'message' => 'Your schedule was updated: ' . $this->changeDetails,
    //         // Add other relevant data
    //     ];
    // }
}
