<?php

declare(strict_types=1);

namespace App\Actions\User;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Exception;

class UpdateMuteAllPreference
{
    public function execute(int $userId, bool $isMuted): UserNotificationPreference
    {
        /** @var User|null $user */
        $user = User::find($userId);

        if (! $user) {
            throw new Exception('User not found.');
        }

        $preferencesModel = $user->notificationPreferences()->firstOrNew(['user_id' => $userId]);
        $preferencesModel->mute_all = $isMuted;
        $preferencesModel->save();

        return $preferencesModel;
    }
}
