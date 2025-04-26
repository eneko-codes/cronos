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
            ->greeting("Hello {$this->user->name},")
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

        // Removed the ->action() button

        return $mailMessage;
    }

    /**
     * Formats the details of a schedule into a readable string.
     */
    protected function formatScheduleDetails(Schedule $schedule): string
    {
        $detailsString = '';
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

        // Group details by weekday
        $groupedDetails = $schedule->scheduleDetails->sortBy(['weekday', 'day_period'])->groupBy('weekday');

        foreach ($groupedDetails as $weekday => $details) {
            $dayName = $daysOfWeek[$weekday] ?? 'Unknown Day';
            $detailsString .= "\n**{$dayName}:**";
            foreach ($details as $detail) {
                $period = Str::ucfirst($detail->day_period);
                $detailsString .= "\n  - {$period}: {$detail->start} - {$detail->end} (UTC)";
            }
        }

        return $detailsString ?: "\n  - No specific details available.";
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
            'message' => 'Your work schedule has been updated.',
            'old_schedule_desc' => $this->oldSchedule?->description,
            'new_schedule_desc' => $this->newSchedule?->description,
            // Optional: Add a link/route for the user to view their schedule
            'link' => route('user.dashboard', ['id' => $this->user->id]),
        ];
    }
}
