<?php

declare(strict_types=1);

namespace App\Actions\Desktime;

use App\Actions\GetNotificationPreferencesAction;
use App\Clients\DesktimeApiClient;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownWarning;

class CheckDesktimeHealthAction
{
    public function __invoke(DesktimeApiClient $client): array
    {
        $result = $client->ping();
        if (! ($result['success'] ?? false)) {
            $admins = User::where('user_type', RoleType::Admin)->get();
            $apiDownNotification = new ApiDownWarning('DeskTime', $result['message'] ?? 'API health check failed');
            $getPreferences = resolve(GetNotificationPreferencesAction::class);
            foreach ($admins as $admin) {
                if ($getPreferences->execute($admin)['eligibility'][$apiDownNotification->type()->value] ?? false) {
                    $admin->notify($apiDownNotification);
                }
            }
        }

        return $result;
    }
}
