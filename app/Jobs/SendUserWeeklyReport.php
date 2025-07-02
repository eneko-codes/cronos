<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Notifications\WeeklyUserReportNotification;
use App\Services\NotificationPreferenceService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to send weekly report notifications to all trackable users.
 *
 * For each trackable user, this job generates a weekly report (placeholder data) and queues a notification if the user is eligible.
 */
class SendUserWeeklyReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Constructs a new SendUserWeeklyReport job instance.
     */
    public function __construct()
    {
        // No parameters needed now
    }

    /**
     * Main entry point for the job.
     *
     * For each trackable user, generates a weekly report and queues a notification if eligible.
     * Logs the process and any errors encountered.
     *
     * @param  NotificationPreferenceService  $notificationService  Service to check notification eligibility.
     */
    public function handle(NotificationPreferenceService $notificationService): void
    {
        Log::info('SendUserWeeklyReport Job started.');

        // Define the date range for the previous week
        $endDate = Carbon::now()->startOfWeek()->subSecond();
        $startDate = $endDate->copy()->subDays(6)->startOfDay();
        Log::info(
            "Report Period: {$startDate->toDateString()} to {$endDate->toDateString()}"
        );

        // Get all trackable users
        $users = User::trackable()->get();
        $sentCount = 0;

        if ($users->isEmpty()) {
            Log::info('SendUserWeeklyReport Job: No trackable users found.');

            return;
        }
        Log::info(
            "SendUserWeeklyReport Job: Found {$users->count()} trackable users to process."
        );

        foreach ($users as $user) {
            Log::info("SendUserWeeklyReport Job: Processing user {$user->id}");

            // --- Placeholder: Gather actual report data for the user --- //
            $reportData = [
                'total_hours_tracked' => rand(20, 40),
                'projects_worked_on' => rand(3, 7),
                'tasks_completed' => rand(10, 25),
                'report_start_date' => $startDate->toDateString(),
                'report_end_date' => $endDate->toDateString(),
            ];
            // --- End Placeholder --- //

            // Prepare notification
            $notification = new WeeklyUserReportNotification($user, $reportData);

            // Check permission using the notification service
            if ($notificationService->isEligibleForNotification($notification->type(), $user)) {
                try {
                    $user->notify($notification);
                    Log::info(
                        "SendUserWeeklyReport Job: Notification queued for user {$user->id}"
                    );
                    $sentCount++;
                } catch (\Exception $e) {
                    Log::error(
                        "SendUserWeeklyReport Job: Failed to queue notification for user {$user->id}",
                        [
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            } else {
                Log::info(
                    "SendUserWeeklyReport Job: Skipped notification for user {$user->id} (checks failed)"
                );
            }
        }

        $summary = "SendUserWeeklyReport Job finished. Processed {$users->count()} users, queued {$sentCount} notifications.";
        Log::info($summary);
    }
}
