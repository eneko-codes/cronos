<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\User;
use App\Models\UserLeave;
use App\Traits\HandlesNotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

/**
 * Notification sent to users to remind them of upcoming leave.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 */
class LeaveReminderNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

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
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::LeaveReminder;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $leaveType = $this->leave->leaveType->name ?? 'Time Off';
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
            ->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $leaveType = $this->leave->leaveType->name ?? 'Time Off';
        $startDate = $this->leave->start_date->format('M d');
        $endDate = $this->leave->end_date->format('M d, Y');
        $fullStartDate = $this->leave->start_date->format('F j, Y ');
        $fullEndDate = $this->leave->end_date->format('F j, Y ');

        $subject = "Upcoming Leave Reminder: {$leaveType} starting {$startDate}";
        $message = "This is a reminder for your upcoming {$leaveType} from {$fullStartDate} to {$fullEndDate}.";

        return [
            'subject' => $subject,
            'message' => $message,
            'leave_end_date' => $this->leave->end_date->toDateString(),
            'leave_type' => $this->leave->leaveType->name,
            'level' => 'info',
        ];
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return \Illuminate\Notifications\Slack\SlackMessage The Slack message instance
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $leaveType = $this->leave->leaveType->name ?? 'Time Off';
        $startDate = $this->leave->start_date->format('F j, Y');
        $endDate = $this->leave->end_date->format('F j, Y');
        $duration = $this->leave->duration_days;

        return (new SlackMessage)
            ->text("Upcoming Leave Reminder: {$leaveType}")
            ->headerBlock("Upcoming Leave Reminder: {$leaveType}")
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block) use ($leaveType, $startDate, $endDate, $duration): void {
                $block->text("This is a reminder about your upcoming {$leaveType}.");
                $block->field("*Start Date:*\n{$startDate}")->markdown();
                $block->field("*End Date:*\n{$endDate}")->markdown();
                $block->field("*Duration:*\n{$duration} day(s)")->markdown();
            });
    }
}
