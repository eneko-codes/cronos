<?php

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
            ->greeting("Hello {$this->user->name},")
            ->line("This is a reminder about your upcoming {$leaveType}.")
            ->line("Start Date: {$startDate}")
            ->line("End Date: {$endDate}")
            ->line("Duration: {$duration} day(s)")
            ->line("Description: {$this->leave->description}")
            ->action('View Leave Details', url('/'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $leaveType = $this->leave->leaveType?->name ?? 'Time Off';
        $startDate = $this->leave->start_date->format('F j, Y');

        return [
            'user_id' => $this->user->id,
            'user_name' => $this->user->name,
            'leave_id' => $this->leave->id,
            'leave_type' => $leaveType,
            'start_date' => $this->leave->start_date->toDateString(),
            'end_date' => $this->leave->end_date->toDateString(),
            'duration_days' => $this->leave->duration_days,
            'message' => "Reminder: Your {$leaveType} starts on {$startDate}.",
            'link' => url('/'),
        ];
    }
}
