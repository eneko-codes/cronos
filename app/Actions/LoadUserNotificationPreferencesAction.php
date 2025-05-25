<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\NotificationPreferenceService;

class LoadUserNotificationPreferencesAction
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    /**
     * Load notification preferences for a user.
     */
    public function execute(int $userId): array
    {
        $user = User::findOrFail($userId);
        $preferences = $this->notificationPreferenceService->getUserNotificationPreferences($user);

        return [
            'user_notifications_muted' => $preferences['user_mute_all'],
            'user_notification_states' => $preferences['user_individual'],
            'global_notifications_enabled' => $preferences['global_enabled'],
            'global_notification_type_states' => $preferences['global_types'],
            'available_notification_types' => $preferences['available_types'],
        ];
    }
}
