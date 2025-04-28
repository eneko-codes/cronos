<?php

namespace App\Notifications;

use App\Models\Schedule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
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
     * Note: Eligibility checks are handled centrally in User::canReceiveNotification().
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database']; // Use both mail and database channels
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
        } elseif (! $this->oldSchedule) {
            // Case where both are null somehow (shouldn't happen but good to handle)
            $mailMessage->line('No schedule information available for this update.');
        } elseif ($this->oldSchedule && ! $this->newSchedule) {
            // Case where the schedule was removed
            $mailMessage->line('Your previous schedule has now ended.');
        }

        // Add the standard action
        $mailMessage->action("Open " . config('app.name'), url('/'));

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
     *
     * @return array
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
        } elseif (! $this->oldSchedule) {
            $messageLines[] = 'No schedule information available for this update.';
        } elseif ($this->oldSchedule && ! $this->newSchedule) {
            $messageLines[] = 'Your previous schedule has now ended.';
        }

        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'old_schedule_id' => $this->oldSchedule?->id,
            'old_schedule_description' => $this->oldSchedule?->description,
            'new_schedule_id' => $this->newSchedule?->id,
            'new_schedule_description' => $this->newSchedule?->description,
            'level' => 'info',
        ];
    }
}
