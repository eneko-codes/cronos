<?php

declare(strict_types=1);

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
            ->greeting("Hello {$notifiable->name},")
            ->line('Here is your activity report for the past week.')
            ->line($reportSummary)
            ->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        // Match mail subject
        $subject = 'Your Weekly Activity Report';

        // Construct message from mail lines
        // NOTE: This matches the current toMail, which might be too simple.
        // Consider if report data should be included here instead.
        $reportSummaryLine = "This week's summary: \n"; // Matches the variable used in toMail
        $messageLines = [
            'Here is your activity report for the past week.',
            $reportSummaryLine,
        ];
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'info',
        ];
    }
}
