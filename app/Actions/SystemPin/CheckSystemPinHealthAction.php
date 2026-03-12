<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\Clients\SystemPinApiClient;
use App\Notifications\ApiDownNotification;
use App\Services\NotificationService;

/**
 * Action to check the health of the SystemPin API and notify maintenance users on failure.
 *
 * This action is typically called from sync job failure handlers when SystemPin
 * sync jobs fail. It performs a health check ping to the SystemPin API and,
 * if the check fails, sends ApiDownNotification notifications to all eligible maintenance users.
 *
 * The ApiDownNotification uses built-in rate limiting middleware to prevent
 * email spam when multiple sync jobs fail simultaneously. Each maintenance user will receive
 * at most one notification per 60-minute window per service.
 *
 * @see \App\Notifications\ApiDownNotification For notification rate limiting details
 * @see \App\Jobs\Sync\SystemPin\SyncSystemPinUsersJob Typical caller
 * @see \App\Jobs\Sync\SystemPin\SyncSystemPinAttendancesJob Typical caller
 */
class CheckSystemPinHealthAction
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {}

    /**
     * Execute the SystemPin API health check and send notifications if the API is down.
     *
     * This method:
     * 1. Performs a ping health check to the SystemPin API
     * 2. If the health check fails, sends ApiDownNotification to all eligible maintenance users
     * 3. Respects user notification preferences and rate limiting
     *
     * The notification is queued (implements ShouldQueue), so it will be processed
     * asynchronously. The ApiDownNotification class handles rate limiting automatically.
     *
     * @param  SystemPinApiClient  $client  The SystemPin API client instance
     * @return array<string, mixed> Health check result with 'success' boolean and optional 'message'
     */
    public function __invoke(SystemPinApiClient $client): array
    {
        $result = $client->ping();

        // Only send notifications if the health check failed
        if (! ($result['success'] ?? false)) {
            $apiDownNotification = new ApiDownNotification(
                'SystemPin',
                $result['message'] ?? 'API health check failed'
            );

            // NotificationService handles preference checking and rate limiting
            $this->notificationService->notifyMaintenanceUsers($apiDownNotification);
        }

        return $result;
    }
}
