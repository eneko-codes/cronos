<?php

/**
 * ToggleGlobalNotificationsAction
 *
 * This action toggles the global enabled/disabled state for all notifications.
 * Only admins can perform this action.
 */

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Auth\Access\AuthorizationException;

class ToggleGlobalNotificationsAction
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    /**
     * Toggle the global state of all notifications.
     * Only admins can perform this action.
     */
    public function execute(User $user, bool $enabled): void
    {
        if (! $user->isAdmin()) {
            throw new AuthorizationException('Only admins can manage global notification settings.');
        }

        $this->notificationPreferenceService->toggleGlobalNotifications($enabled);
    }
}
