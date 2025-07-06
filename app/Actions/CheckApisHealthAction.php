<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Pingable;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownWarning;
use Exception;

/**
 * Action to check the health of external APIs and notify admins if any are down.
 *
 * Used by:
 * - BaseSyncJob (when a sync job fails, triggers API health checks)
 * - Settings Livewire component (API health check UI)
 *
 * Methods:
 * - execute: Checks each API (Pingable) and sends notifications if any are down
 * - sendApiDownNotification: Notifies all admins if an API is detected as down
 *
 * Notification logic:
 * - Only notifies admins who are eligible for the ApiDownWarning notification type
 * - Uses GetNotificationPreferencesAction to check eligibility
 */
class CheckApisHealthAction
{
    /**
     * Checks the health of the given APIs and sends notifications if any are down.
     *
     * @param  array<string, Pingable|null>  $apis  Service name => API client
     */
    public function execute(array $apis): void
    {
        foreach ($apis as $serviceName => $service) {
            if ($service instanceof Pingable) {
                try {
                    $pingResult = $service->ping();
                    $isDown = ! ($pingResult['success'] ?? false);

                    if ($isDown) {
                        $errorMessage = $pingResult['message'] ?? 'API health check failed';
                        $this->sendApiDownNotification($serviceName, $errorMessage);
                    }
                } catch (Exception $e) {
                    $errorMessage = "Health check failed: {$e->getMessage()}";
                    $this->sendApiDownNotification($serviceName, $errorMessage);
                }
            }
        }
    }

    /**
     * Sends an API down notification to all eligible admins.
     *
     * @param  string  $apiName  The name of the API
     * @param  string  $errorMessage  The error message to include in the notification
     */
    protected function sendApiDownNotification(string $apiName, string $errorMessage): void
    {
        $admins = User::where('user_type', RoleType::Admin)->get();
        $apiDownNotification = new ApiDownWarning($apiName, $errorMessage);
        $getPreferences = resolve(GetNotificationPreferencesAction::class);
        foreach ($admins as $admin) {
            if ($getPreferences->execute($admin)['eligibility'][$apiDownNotification->type()->value] ?? false) {
                $admin->notify($apiDownNotification);
            }
        }
    }
}
