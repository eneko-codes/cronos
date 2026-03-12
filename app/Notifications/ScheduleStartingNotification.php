<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Enums\NotificationType;
use App\Models\Schedule;
use App\Models\User;
use App\Traits\HandlesNotificationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Str;

/**
 * Notification sent to users when a new schedule assignment starts.
 *
 * This is a CONFIGURABLE notification - delivery channels and sending
 * are controlled by global and user notification preferences.
 */
class ScheduleStartingNotification extends Notification implements ShouldQueue
{
    use HandlesNotificationDelivery, Queueable;

    public User $user;

    public Schedule $newSchedule;

    public ?Schedule $oldSchedule;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user  The user whose schedule is starting.
     * @param  Schedule  $newSchedule  The user's new schedule that is starting.
     * @param  ?Schedule  $oldSchedule  The user's previous schedule (or null).
     */
    public function __construct(User $user, Schedule $newSchedule, ?Schedule $oldSchedule = null)
    {
        $this->user = $user;
        $this->newSchedule = $newSchedule;
        $this->oldSchedule = $oldSchedule;
    }

    /**
     * Get the notification type enum value.
     */
    public function type(): NotificationType
    {
        return NotificationType::ScheduleStarting;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Your New Work Schedule Has Started')
            ->greeting("Hello {$notifiable->name},")
            ->line('A new work schedule assignment has started for you.');

        if ($this->oldSchedule) {
            $mailMessage->line("**Previous Schedule:** {$this->oldSchedule->description}")
                ->line($this->formatScheduleDetails($this->oldSchedule));
        }

        $mailMessage->line("**New Schedule:** {$this->newSchedule->description}")
            ->line($this->formatScheduleDetails($this->newSchedule));

        return $mailMessage->action('Open '.config('app.name'), url('/'));
    }

    /**
     * Formats the details of a schedule into a readable string.
     */
    protected function formatScheduleDetails(Schedule $schedule): string
    {
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        $groupedDetailsArray = $schedule->scheduleDetails
            ->sortBy(['weekday', 'day_period'])
            ->groupBy('weekday')
            ->toArray();

        if (empty($groupedDetailsArray)) {
            return "\n  - No specific details available.";
        }

        $formattedDays = [];
        foreach ($groupedDetailsArray as $weekday => $details) {
            $dayName = $daysOfWeek[$weekday] ?? 'Unknown Day';
            $dayLines = ["**{$dayName}:**"];

            foreach ($details as $detail) {
                $period = Str::ucfirst(is_array($detail) ? $detail['day_period'] : $detail->day_period);
                $start = is_array($detail) ? $detail['start'] : $detail->start;
                $end = is_array($detail) ? $detail['end'] : $detail->end;
                $dayLines[] = "  - {$period}: {$start} - {$end} (UTC)";
            }
            $formattedDays[] = implode("\n", $dayLines);
        }

        return implode("\n", $formattedDays);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $subject = 'Your New Work Schedule Has Started';
        $messageLines = ['A new work schedule assignment has started for you.'];

        if ($this->oldSchedule) {
            $messageLines[] = "\n**Previous Schedule:** {$this->oldSchedule->description}";
            $messageLines[] = $this->formatScheduleDetails($this->oldSchedule);
        }

        $messageLines[] = "\n**New Schedule:** {$this->newSchedule->description}";
        $messageLines[] = $this->formatScheduleDetails($this->newSchedule);

        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'old_schedule_id' => $this->oldSchedule?->odoo_schedule_id,
            'old_schedule_description' => $this->oldSchedule?->description,
            'new_schedule_id' => $this->newSchedule->odoo_schedule_id,
            'new_schedule_description' => $this->newSchedule->description,
            'level' => 'info',
        ];
    }

    /**
     * Get the Slack representation of the notification.
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $message = (new SlackMessage)
            ->text('Your New Work Schedule Has Started')
            ->headerBlock('Your New Work Schedule Has Started')
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text('A new work schedule assignment has started for you.');
            });

        if ($this->oldSchedule) {
            $oldScheduleText = "**Previous Schedule:** {$this->oldSchedule->description}\n{$this->formatScheduleDetails($this->oldSchedule)}";
            $message->sectionBlock(function ($block) use ($oldScheduleText): void {
                $block->text($oldScheduleText)->markdown();
            });
        }

        $newScheduleText = "**New Schedule:** {$this->newSchedule->description}\n{$this->formatScheduleDetails($this->newSchedule)}";
        $message->sectionBlock(function ($block) use ($newScheduleText): void {
            $block->text($newScheduleText)->markdown();
        });

        return $message;
    }
}
