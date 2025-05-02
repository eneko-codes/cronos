<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public User $user;

    public UserLeave $leave;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user  The user receiving the reminder.
     * @param  UserLeave  $leave  The leave record.
     */
    public function __construct(User $user, UserLeave $leave)
    {
        $this->user = $user;
        $this->leave = $leave;
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
        $leaveType = $this->leave->leaveType?->name ?? 'Time Off';
        $startDate = $this->leave->start_date->format('F j, Y');
        $endDate = $this->leave->end_date->format('F j, Y');
        $duration = $this->leave->duration_days;

        return (new MailMessage)
            ->subject("Upcoming Leave Reminder: {$leaveType}")
            ->greeting("Hello {$notifiable->name},")
            ->line("This is a reminder about your upcoming {$leaveType}.")
            ->line("Start Date: {$startDate}")
            ->line("End Date: {$endDate}")
            ->line("Duration: {$duration} day(s)")
            ->line("Description: {$this->leave->description}")
            ->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $leaveType = $this->leave->leaveType?->name ?? 'Time Off';
        $startDate = $this->leave->start_date->format('F j, Y');
        $endDate = $this->leave->end_date->format('F j, Y');
        $duration = $this->leave->duration_days;

        $subject = "Upcoming Leave Reminder: {$leaveType}";

        $messageLines = [
            "This is a reminder about your upcoming {$leaveType}.",
            "Start Date: {$startDate}",
            "End Date: {$endDate}",
            "Duration: {$duration} day(s)",
            "Description: {$this->leave->description}",
        ];
        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'leave_end_date' => $this->leave->end_date->toDateString(),
            'leave_type' => $this->leave->leaveType->name,
            'level' => 'info',
        ];
    }
}
