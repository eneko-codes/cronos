<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\Actions\GetNotificationPreferencesAction;
use App\Clients\SystemPinApiClient;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownWarning;

class CheckSystemPinHealthAction
{
    public function __invoke(SystemPinApiClient $client): array
    {
        $result = $client->ping();
        if (! ($result['success'] ?? false)) {
            $admins = User::where('user_type', RoleType::Admin)->get();
            $apiDownNotification = new ApiDownWarning('SystemPin', $result['message'] ?? 'API health check failed');
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
