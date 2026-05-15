<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\UserLeave;
use App\Notifications\LeaveStatusChangeNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;

class UserLeaveObserver
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    public function created(UserLeave $leave): void
    {
        Log::debug('UserLeave created', [
            'id' => $leave->id,
            'attributes' => $leave->getAttributes(),
        ]);
    }

    public function updated(UserLeave $leave): void
    {
        $changes = $leave->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $leave->getOriginal($field);
            }
            Log::debug('UserLeave updated', [
                'id' => $leave->id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }

        // Check if the leave status changed
        if ($leave->wasChanged('status')) {
            $oldStatus = $leave->getOriginal('status');
            $newStatus = $leave->status;

            // Only notify if status actually changed and user exists
            if ($oldStatus !== $newStatus && $leave->user) {
                // Eager load relationships if not already loaded
                $leave->loadMissing(['user', 'leaveType']);

                // Create and send notification (eligibility checked by notification's shouldSend())
                $notification = new LeaveStatusChangeNotification($leave, $oldStatus, $newStatus);
                $this->notificationService->notifyUser($leave->user, $notification);
            }
        }
    }

    public function deleted(UserLeave $leave): void
    {
        Log::debug('UserLeave deleted', [
            'id' => $leave->id,
            'attributes' => $leave->getOriginal(),
        ]);
    }
}
