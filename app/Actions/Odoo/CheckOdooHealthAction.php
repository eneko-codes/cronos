<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\Actions\GetNotificationPreferencesAction;
use App\Clients\OdooApiClient;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownWarning;

class CheckOdooHealthAction
{
    public function __invoke(OdooApiClient $client): array
    {
        $result = $client->ping();
        if (! ($result['success'] ?? false)) {
            $admins = User::where('user_type', RoleType::Admin)->get();
            $apiDownNotification = new ApiDownWarning('Odoo', $result['message'] ?? 'API health check failed');
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
