<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DuplicateScheduleWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public int $odooScheduleId;

    public string $scheduleName;

    public array $duplicatesData;

    /**
     * Create a new notification instance.
     * @param int $odooScheduleId
     * @param string $scheduleName
     * @param array $duplicatesData Data about the duplicates.
     */
    public function __construct(int $odooScheduleId, string $scheduleName, array $duplicatesData)
    {
        $this->odooScheduleId = $odooScheduleId;
        $this->scheduleName = $scheduleName;
        $this->duplicatesData = $duplicatesData;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Send via mail and store in the database
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {

        $mailMessage = (new MailMessage)
            ->subject("Duplicated Schedule Details: {$this->scheduleName}")
            ->greeting('Hello ' . $notifiable->name . ',')
            ->line('A potential issue was detected during the Odoo schedule synchronization process.')
            ->line("The schedule '{$this->scheduleName}' (Odoo ID: {$this->odooScheduleId}) has duplicate time slot definitions for the same weekday and period.")
            ->line('This might cause inaccurate calculations of the schedule. When there are duplicated schedule details for the same weekday and period, the app will use the average hours per day pulled from Odoo for that schedule.')
            ->line('Please review the schedule details in Odoo to resolve the conflict.')
            ->line('Details of duplicates:');

        // Add details about the duplicates using chained lines
        foreach ($this->duplicatesData as $duplicate) {
             $day = $duplicate['weekday'] ?? '?';
             $period = $duplicate['day_period'] ?? '?';
             $count = $duplicate['count'] ?? '?';
             $mailMessage->line("- Day: {$day}, Period: {$period}, Count: {$count}");
        }

        // Update the concluding action
        $mailMessage->action("Open " . config('app.name'), url('/'));

        return $mailMessage;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array
     */
    public function toArray(object $notifiable): array
    {
        $subject = "Duplicate Schedule Details: {$this->scheduleName}";

        // Construct the detailed message lines as an array
        $messageLines = [
            "A potential issue was detected during the Odoo schedule synchronization process.",
            "The schedule '{$this->scheduleName}' (Odoo ID: {$this->odooScheduleId}) has duplicate time slot definitions for the same weekday and period.",
            "This might cause inaccurate calculations of the schedule. When there are duplicated schedule details for the same weekday and period, the app will use the average hours per day pulled from Odoo for that schedule.",
            "Please review the schedule details in Odoo to resolve the conflict.",
            "Details of duplicates:",
        ];

        if (!empty($this->duplicatesData)) {
            foreach ($this->duplicatesData as $duplicate) {
                 $day = $duplicate['weekday'] ?? '?';
                 $period = $duplicate['day_period'] ?? '?';
                 $count = $duplicate['count'] ?? '?';
                 $messageLines[] = "- Day: {$day}, Period: {$period}, Count: {$count}";
            }
        } else {
            $messageLines[] = "- Oups! No specific duplicate details are available.";
        }

        $message = implode("\n", $messageLines);

        return [
            'subject' => $subject,
            'message' => $message,
            'level' => 'warning'
        ];
    }

}
