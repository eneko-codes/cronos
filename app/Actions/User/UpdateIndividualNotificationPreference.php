<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Exception;

// For checking valid keys

class UpdateIndividualNotificationPreference
{
    // Define valid preference keys here or fetch from a shared source (e.g., a config or the Sidebar component's method)
    // For simplicity, hardcoding them here. In a larger app, consider a shared source.
    private array $validPreferenceKeys = [
        'schedule_change',
        'weekly_user_report',
        'leave_reminder',
        'api_down_warning',
        'admin_promotion_email',
    ];

    public function execute(int $userId, string $preferenceKey, bool $value): UserNotificationPreference
    {
        if (! in_array($preferenceKey, $this->validPreferenceKeys)) {
            throw new Exception("Invalid preference key: {$preferenceKey}.");
        }

        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user) {
            throw new Exception('User not found.');
        }

        $preferencesModel = $user->notificationPreferences()->firstOrNew(['user_id' => $userId]);
        $preferencesModel->{$preferenceKey} = $value;
        $preferencesModel->save();

        return $preferencesModel;
    }
}
