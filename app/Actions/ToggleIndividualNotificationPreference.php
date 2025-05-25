<?php

/**
 * ToggleIndividualNotificationPreference
 *
 * This action toggles an individual notification type preference for a user.
 * It updates the relevant column in the user_notification_preferences table for the given user and notification type.
 */

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationPreferenceService;

class ToggleIndividualNotificationPreference
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    /**
     * Toggle an individual notification preference for a user.
     */
    public function execute(int $userId, NotificationType $type, bool $enabled): void
    {
        $user = User::findOrFail($userId);
        $this->notificationPreferenceService->toggleUserNotificationType($user, $type, $enabled);
    }
}
