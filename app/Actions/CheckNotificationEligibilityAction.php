<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationPreferenceService;

class CheckNotificationEligibilityAction
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    public function execute(NotificationType $type, ?User $user = null): bool
    {
        return $this->notificationPreferenceService->isEligibleForNotification($type, $user);
    }
}
