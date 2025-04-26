<?php

namespace App\Notifications;

use App\Models\Schedule;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DuplicateScheduleWarning extends Notification implements ShouldQueue
{
    use Queueable;

    public int $odooScheduleId;
    public string $scheduleName;
    public $duplicatesData; // Store the duplicates data

    /**
     * Create a new notification instance.
     */
    public function __construct(int $odooScheduleId, string $scheduleName, $duplicatesData)
    {
        $this->odooScheduleId = $odooScheduleId;
        $this->scheduleName = $scheduleName;
        $this->duplicatesData = $duplicatesData; // Store the passed data
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
        $subject = "Duplicate Schedule Details Warning: {$this->scheduleName} (#{$this->odooScheduleId})";
        $lines = [
            "A potential issue was detected during the Odoo schedule synchronization process.",
            "The schedule '{$this->scheduleName}' (Odoo ID: {$this->odooScheduleId}) has duplicate time slot definitions for the same weekday and period.",
            "This might cause inconsistencies or errors in schedule processing.",
            "Please review the schedule details in Odoo to resolve the conflict.",
            "Details of duplicates:",
        ];

        // Add details about the duplicates
        foreach ($this->duplicatesData as $duplicate) {
            $lines[] = "- Day: {$duplicate['weekday']}, Period: {$duplicate['day_period']}, Count: {$duplicate['count']}";
        }


        $mailMessage = (new MailMessage)->subject($subject)->error(); // Use error level for warnings
        foreach ($lines as $line) {
            $mailMessage->line($line);
        }
        $mailMessage->line('Thank you.');

        return $mailMessage;

    }

    /**
     * Get the array representation of the notification for the database.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'odoo_schedule_id' => $this->odooScheduleId,
            'schedule_name' => $this->scheduleName,
            'message' => "Duplicate schedule details detected for schedule '{$this->scheduleName}' (#{$this->odooScheduleId}). Please review in Odoo.",
            'duplicates_details' => $this->duplicatesData->toArray(), // Store duplicate details as JSON
        ];
    }

     /**
     * Get the array representation of the notification.
     * Needed for compatibility, delegating to toDatabase.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
