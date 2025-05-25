<?php

/**
 * ToggleNotificationTypeGlobalStateAction
 *
 * This action toggles the global enabled/disabled state for a specific notification type.
 * Only admins can perform this action.
 */

declare(strict_types=1);

namespace App\Actions;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Auth\Access\AuthorizationException;

class ToggleNotificationTypeGlobalStateAction
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    /**
     * Toggle the global state of a specific notification type.
     * Only admins can perform this action.
     */
    public function execute(User $user, NotificationType $type, bool $enabled): void
    {
        if (! $user->isAdmin()) {
            throw new AuthorizationException('Only admins can manage global notification settings.');
        }

        $this->notificationPreferenceService->toggleGlobalNotificationType($type, $enabled);
    }
}
