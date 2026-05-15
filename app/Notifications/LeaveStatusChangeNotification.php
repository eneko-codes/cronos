<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\UserLeave;
use App\Traits\HandlesNotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;

/**
 * Notification sent to users when their leave request status changes.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 */
class LeaveStatusChangeNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    public UserLeave $leave;

    public string $oldStatus;

    public string $newStatus;

    /**
     * Create a new notification instance.
     *
     * @param  UserLeave  $leave  The leave record that changed status.
     * @param  string  $oldStatus  The previous status.
     * @param  string  $newStatus  The new status.
     */
    public function __construct(UserLeave $leave, string $oldStatus, string $newStatus)
    {
        $this->leave = $leave;
        $this->oldStatus = $oldStatus;
        $this->newStatus = $newStatus;
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::LeaveStatusChange;
    }

    /**
     * Get the status label for display.
     */
    protected function getStatusLabel(string $status): string
    {
        return match ($status) {
            'validate' => 'Approved',
            'refuse' => 'Rejected',
            'cancel' => 'Cancelled',
            'confirm' => 'Pending',
            default => ucfirst($status),
        };
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $leaveType = $this->leave->leaveType->name ?? 'Leave';
        $startDate = $this->leave->start_date->format('M d, Y');
        $endDate = $this->leave->end_date->format('M d, Y');
        $duration = $this->leave->duration_days;
        $durationText = $duration == 1 ? '1 day' : ($duration == 0.5 ? 'Half day' : "{$duration} days");

        $statusLabel = $this->getStatusLabel($this->newStatus);

        $mailMessage = (new MailMessage)
            ->subject("Your Leave Request Has Been {$statusLabel}")
            ->greeting("Hello {$notifiable->name},")
            ->line("Your leave request status has been updated from **{$this->getStatusLabel($this->oldStatus)}** to **{$statusLabel}**.");

        $mailMessage->line('**Leave Details:**')
            ->line("- Type: {$leaveType}")
            ->line("- Start Date: {$startDate}")
            ->line("- End Date: {$endDate}")
            ->line("- Duration: {$durationText}");

        return $mailMessage->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $leaveType = $this->leave->leaveType->name ?? 'Leave';
        $startDate = $this->leave->start_date->format('M d, Y');
        $endDate = $this->leave->end_date->format('M d, Y');
        $duration = $this->leave->duration_days;
        $durationText = $duration == 1 ? '1 day' : ($duration == 0.5 ? 'Half day' : "{$duration} days");

        $statusLabel = $this->getStatusLabel($this->newStatus);
        $oldStatusLabel = $this->getStatusLabel($this->oldStatus);

        $subject = "Your Leave Request Has Been {$statusLabel}";
        $message = "Your leave request status has been updated from {$oldStatusLabel} to {$statusLabel}.\n\n";
        $message .= "Leave Details:\n";
        $message .= "- Type: {$leaveType}\n";
        $message .= "- Start Date: {$startDate}\n";
        $message .= "- End Date: {$endDate}\n";
        $message .= "- Duration: {$durationText}";

        return [
            'subject' => $subject,
            'message' => $message,
            'leave_id' => $this->leave->id,
            'old_status' => $this->oldStatus,
            'new_status' => $this->newStatus,
            'level' => match ($this->newStatus) {
                'validate' => 'success',
                'refuse', 'cancel' => 'warning',
                default => 'info',
            },
        ];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $leaveType = $this->leave->leaveType->name ?? 'Leave';
        $startDate = $this->leave->start_date->format('M d, Y');
        $endDate = $this->leave->end_date->format('M d, Y');
        $duration = $this->leave->duration_days;
        $durationText = $duration == 1 ? '1 day' : ($duration == 0.5 ? 'Half day' : "{$duration} days");

        $statusLabel = $this->getStatusLabel($this->newStatus);
        $oldStatusLabel = $this->getStatusLabel($this->oldStatus);

        return (new SlackMessage)
            ->text("Your Leave Request Has Been {$statusLabel}")
            ->headerBlock("Your Leave Request Has Been {$statusLabel}")
            ->sectionBlock(function ($block) use ($notifiable, $oldStatusLabel, $statusLabel): void {
                $block->text("Hello {$notifiable->name},");
                $block->text("Your leave request status has been updated from **{$oldStatusLabel}** to **{$statusLabel}**.");
            })
            ->sectionBlock(function ($block) use ($leaveType, $startDate, $endDate, $durationText): void {
                $block->text('**Leave Details:**');
                $block->text("- Type: {$leaveType}");
                $block->text("- Start Date: {$startDate}");
                $block->text("- End Date: {$endDate}");
                $block->text("- Duration: {$durationText}");
            });
    }
}
