<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Slack\SlackMessage;
use Illuminate\Support\Str;

class ScheduleChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public User $user;

    public ?Schedule $oldSchedule;

    public ?Schedule $newSchedule;

    /**
     * Create a new notification instance.
     *
     * @param  User  $user  The user whose schedule changed.
     * @param  ?Schedule  $oldSchedule  The user's previous schedule (or null).
     * @param  ?Schedule  $newSchedule  The user's new schedule (or null).
     */
    public function __construct(User $user, ?Schedule $oldSchedule, ?Schedule $newSchedule)
    {
        $this->user = $user;
        $this->oldSchedule = $oldSchedule;
        $this->newSchedule = $newSchedule;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return $this->getChannels();
    }

    /**
     * Get the notification channels based on global setting.
     *
     * Reads the global notification channel setting from Settings table.
     * Always includes 'database' channel for in-app notifications.
     *
     * @return array<int, string> Array of channel names
     */
    private function getChannels(): array
    {
        $channel = Setting::getValue('notification_channel', 'mail');
        $channels = ['database']; // Always include database for in-app notifications

        if ($channel === 'slack') {
            $channels[] = 'slack';
        } else {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $mailMessage = (new MailMessage)
            ->subject('Your Work Schedule Has Been Updated')
            ->greeting("Hello {$notifiable->name},")
            ->line('Your assigned work schedule has been updated.');

        if ($this->oldSchedule) {
            $mailMessage->line("**Previous Schedule:** {$this->oldSchedule->description}")
                ->line($this->formatScheduleDetails($this->oldSchedule));
        }

        if ($this->newSchedule) {
            $mailMessage->line("**New Schedule:** {$this->newSchedule->description}")
                ->line($this->formatScheduleDetails($this->newSchedule));
        } else {
            // This branch is taken if $this->newSchedule is null.
            // PHPStan indicates that in this case, $this->oldSchedule is also null,
            // making '!$this->oldSchedule' always true and the subsequent elseif unreachable.
            $mailMessage->line('No schedule information available for this update.');
        }

        // Add the standard action
        $mailMessage->action('Open '.config('app.name'), url('/'));

        return $mailMessage;
    }

    /**
     * Formats the details of a schedule into a readable string using plain PHP arrays.
     */
    protected function formatScheduleDetails(Schedule $schedule): string
    {
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        // Sort and group details by weekday using Eloquent Collection methods,
        // but then convert to array for processing.
        $groupedDetailsArray = $schedule->scheduleDetails
            ->sortBy(['weekday', 'day_period'])
            ->groupBy('weekday')
            ->toArray(); // Convert to array here

        if (empty($groupedDetailsArray)) {
            return "\n  - No specific details available.";
        }

        $formattedDays = [];
        foreach ($groupedDetailsArray as $weekday => $details) {
            $dayName = $daysOfWeek[$weekday] ?? 'Unknown Day';
            $dayLines = ["**{$dayName}:**"]; // Start with day name

            // Map each detail within the day to its string representation
            foreach ($details as $detail) {
                // Access properties as object or array depending on what ->toArray() returns
                $period = Str::ucfirst(is_array($detail) ? $detail['day_period'] : $detail->day_period);
                $start = is_array($detail) ? $detail['start'] : $detail->start;
                $end = is_array($detail) ? $detail['end'] : $detail->end;
                $dayLines[] = "  - {$period}: {$start} - {$end} (UTC)";
            }
            // Implode the lines for this day
            $formattedDays[] = implode("\n", $dayLines);
        }

        // Combine all formatted days with newlines
        return implode("\n", $formattedDays);
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray(object $notifiable): array
    {
        $subject = 'Your Work Schedule Has Been Updated';

        // Use plain array to build message lines
        $messageLines = [];
        $messageLines[] = 'Your assigned work schedule has been updated.';

        if ($this->oldSchedule) {
            $messageLines[] = "\n**Previous Schedule:** {$this->oldSchedule->description}";
            $messageLines[] = $this->formatScheduleDetails($this->oldSchedule);
        }

        if ($this->newSchedule) {
            $messageLines[] = "\n**New Schedule:** {$this->newSchedule->description}";
            $messageLines[] = $this->formatScheduleDetails($this->newSchedule);
        } else {
            // This branch is taken if $this->newSchedule is null.
            // PHPStan indicates that in this case, $this->oldSchedule is also null.
            $messageLines[] = 'No schedule information available for this update.';
        }

        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'old_schedule_id' => $this->oldSchedule?->odoo_schedule_id,
            'old_schedule_description' => $this->oldSchedule?->description,
            'new_schedule_id' => $this->newSchedule?->odoo_schedule_id,
            'new_schedule_description' => $this->newSchedule?->description,
            'level' => 'info',
        ];
    }

    public function type(): \App\Enums\NotificationType
    {
        return \App\Enums\NotificationType::ScheduleChange;
    }

    /**
     * Get the Slack representation of the notification.
     *
     * @param  object  $notifiable  The user receiving the notification
     * @return \Illuminate\Notifications\Slack\SlackMessage The Slack message instance
     */
    public function toSlack(object $notifiable): SlackMessage
    {
        $message = (new SlackMessage)
            ->text('Your Work Schedule Has Been Updated')
            ->headerBlock('Your Work Schedule Has Been Updated')
            ->sectionBlock(function ($block) use ($notifiable): void {
                $block->text("Hello {$notifiable->name},");
            })
            ->sectionBlock(function ($block): void {
                $block->text('Your assigned work schedule has been updated.');
            });

        if ($this->oldSchedule) {
            $oldScheduleText = "**Previous Schedule:** {$this->oldSchedule->description}\n{$this->formatScheduleDetails($this->oldSchedule)}";
            $message->sectionBlock(function ($block) use ($oldScheduleText): void {
                $block->text($oldScheduleText)->markdown();
            });
        }

        if ($this->newSchedule) {
            $newScheduleText = "**New Schedule:** {$this->newSchedule->description}\n{$this->formatScheduleDetails($this->newSchedule)}";
            $message->sectionBlock(function ($block) use ($newScheduleText): void {
                $block->text($newScheduleText)->markdown();
            });
        } else {
            $message->sectionBlock(function ($block): void {
                $block->text('No schedule information available for this update.');
            });
        }

        return $message;
    }
}
