<?php

declare(strict_types=1);

namespace App\Actions;

use App\Contracts\Pingable;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownWarning;
use App\Services\NotificationPreferenceService;
use Exception;

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

    protected function sendApiDownNotification(string $apiName, string $errorMessage): void
    {
        $admins = User::where('user_type', RoleType::Admin)->get();
        $apiDownNotification = new ApiDownWarning($apiName, $errorMessage);
        $notificationService = resolve(NotificationPreferenceService::class);
        foreach ($admins as $admin) {
            if ($notificationService->isEligibleForNotification($apiDownNotification->type(), $admin)) {
                $admin->notifyNow($apiDownNotification);
            }
        }
    }
}
