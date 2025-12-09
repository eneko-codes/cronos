<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\UserLeave;
use App\Notifications\LeaveReminderNotification;
use App\Services\NotificationPreferenceService;
use Carbon\Carbon;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to send leave reminder notifications to users with approved upcoming leaves.
 *
 * For each user with an approved leave starting in a specified number of days, this job checks notification eligibility and queues a reminder notification.
 */
class SendUserLeaveReminderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of days in advance to send the reminder.
     */
    protected int $daysInAdvance;

    /**
     * Constructs a new SendUserLeaveReminderJob instance.
     *
     * @param  int  $daysInAdvance  How many days in advance to send the reminder (default: 1).
     */
    public function __construct(int $daysInAdvance = 1)
    {
        $this->daysInAdvance = $daysInAdvance >= 0 ? $daysInAdvance : 1;
    }

    /**
     * Main entry point for the job.
     *
     * Finds all users with approved leaves starting on the target date, checks notification eligibility, and queues reminders.
     * Logs the process and any errors encountered.
     *
     * @param  NotificationPreferenceService  $preferenceService  Service to check notification eligibility.
     */
    public function handle(NotificationPreferenceService $preferenceService): void
    {
        $targetDate = Carbon::today()
            ->addDays($this->daysInAdvance)
            ->toDateString();
        Log::info(
            "SendUserLeaveReminderJob started for target date: $targetDate ($this->daysInAdvance day(s) advance)."
        );

        $upcomingLeaves = UserLeave::with('user')
            ->where('status', 'approved')
            ->whereDate('start_date', $targetDate)
            ->get();

        $sentCount = 0;
        if ($upcomingLeaves->isEmpty()) {
            Log::info(
                "SendUserLeaveReminderJob: No upcoming leaves found starting on $targetDate."
            );

            return;
        }
        Log::info(
            "SendUserLeaveReminderJob: Found {$upcomingLeaves->count()} leaves starting on $targetDate."
        );

        foreach ($upcomingLeaves as $leave) {
            $user = $leave->user;

            if (! $user) {
                Log::warning('SendUserLeaveReminderJob: User not found for leave.', [
                    'leaveId' => $leave->id,
                ]);

                continue;
            }

            if ($user->do_not_track) {
                Log::info(
                    'SendUserLeaveReminderJob: Skipping leave reminder for user marked do_not_track.',
                    [
                        'userId' => $user->id,
                        'leaveId' => $leave->id,
                    ]
                );

                continue;
            }

            Log::info(
                "SendUserLeaveReminderJob: Processing leave $leave->id for user $user->id"
            );

            $notification = new LeaveReminderNotification($user, $leave);

            // Check permission using the notification service
            if ($preferenceService->getPreferences($user)['eligibility'][$notification->type()->value] ?? false) {
                try {
                    $user->notify($notification);
                    Log::info(
                        "SendUserLeaveReminderJob: Notification queued for user $user->id, leave $leave->id"
                    );
                    $sentCount++;
                } catch (Exception $e) {
                    Log::error(
                        "SendUserLeaveReminderJob: Failed to queue notification for user $user->id, leave $leave->id",
                        [
                            'error' => $e->getMessage(),
                        ]
                    );
                }
            } else {
                Log::info(
                    "SendUserLeaveReminderJob: Skipped notification for user $user->id, leave $leave->id (checks failed)"
                );
            }
        }
    }
}
