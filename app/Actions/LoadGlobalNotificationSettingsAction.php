<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Auth\Access\AuthorizationException;

class LoadGlobalNotificationSettingsAction
{
    public function __construct(
        private NotificationPreferenceService $notificationPreferenceService
    ) {}

    /**
     * Load global notification settings.
     * Only admins can access global settings.
     */
    public function execute(User $user): array
    {
        if (! $user->isAdmin()) {
            throw new AuthorizationException('Only admins can access global notification settings.');
        }

        $preferences = $this->notificationPreferenceService->getUserNotificationPreferences();

        return [
            'global_enabled' => $preferences['global_enabled'],
            'global_types' => $preferences['global_types'],
        ];
    }
}
