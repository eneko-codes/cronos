<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WeeklyUserReportNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public User $user;

    public array $reportData;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user  The user receiving the report.
     * @param  array  $reportData  Data for the report.
     */
    public function __construct(User $user, array $reportData)
    {
        $this->user = $user;
        $this->reportData = $reportData;
    }

    /**
     * Get the notification's delivery channels.
     * Note: Eligibility checks are handled centrally in User::canReceiveNotification().
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $reportSummary = "This week's summary: \n";

        return (new MailMessage)
            ->subject('Your Weekly Activity Report')
            ->greeting("Hello {$this->user->name},")
            ->line('Here is your activity report for the past week.')
            ->line($reportSummary)
            ->action('View Dashboard', url('/dashboard'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'message' => 'Your weekly activity report is available.',
            'report_data' => $this->reportData,
            'link' => url('/dashboard'),
        ];
    }
}
