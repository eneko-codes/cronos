<?php

/**
 * ToggleMuteAllPreference
 *
 * This action toggles the mute all notifications preference for a user.
 * It updates the 'muted_notifications' column in the users table for the given user.
 */

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\NotificationPreferenceService;

class ToggleMuteAllPreference
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    /**
     * Toggle the mute all preference for a user.
     */
    public function execute(int $userId, bool $muteAll): void
    {
        $user = User::findOrFail($userId);
        $this->notificationPreferenceService->toggleUserMuteAll($user, $muteAll);
    }
}
