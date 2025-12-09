<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\Clients\OdooApiClient;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownNotification;
use App\Services\NotificationPreferenceService;

/**
 * Action to check the health of the Odoo API and notify maintenance users on failure.
 *
 * This action is typically called from sync job failure handlers when Odoo
 * sync jobs fail. It performs a health check ping to the Odoo API and,
 * if the check fails, sends ApiDownNotification notifications to all eligible maintenance users.
 *
 * The ApiDownNotification uses built-in rate limiting middleware to prevent
 * email spam when multiple sync jobs fail simultaneously. Each maintenance user will receive
 * at most one notification per 60-minute window per service.
 *
 * @see \App\Notifications\ApiDownNotification For notification rate limiting details
 * @see \App\Jobs\Sync\Odoo\SyncOdooUsersJob Typical caller
 * @see \App\Jobs\Sync\Odoo\SyncOdooSchedulesJob Typical caller
 * @see \App\Jobs\Sync\Odoo\SyncOdooDepartmentsJob Typical caller
 */
class CheckOdooHealthAction
{
    /**
     * Execute the Odoo API health check and send notifications if the API is down.
     *
     * This method:
     * 1. Performs a ping health check to the Odoo API
     * 2. If the health check fails, retrieves all maintenance users
     * 3. Creates an ApiDownNotification notification for each eligible maintenance user
     * 4. Respects user notification preferences before sending
     *
     * The notification is queued (implements ShouldQueue), so it will be processed
     * asynchronously. The ApiDownNotification class handles rate limiting automatically.
     *
     * @param  OdooApiClient  $client  The Odoo API client instance
     * @return array<string, mixed> Health check result with 'success' boolean and optional 'message'
     */
    public function __invoke(OdooApiClient $client): array
    {
        $result = $client->ping();

        // Only send notifications if the health check failed
        if (! ($result['success'] ?? false)) {
            // Get all maintenance users who should receive API down warnings
            $maintenanceUsers = User::where('user_type', RoleType::Maintenance)->get();

            // Create a single notification instance (will be reused for all maintenance users)
            // The ApiDownNotification class handles rate limiting per user automatically
            $apiDownNotification = new ApiDownNotification(
                'Odoo',
                $result['message'] ?? 'API health check failed'
            );

            $preferenceService = resolve(NotificationPreferenceService::class);

            // Send notification to each eligible maintenance user
            foreach ($maintenanceUsers as $maintenanceUser) {
                // Check if this maintenance user has enabled ApiDown notifications
                $preferences = $preferenceService->getPreferences($maintenanceUser);
                $isEligible = $preferences['eligibility'][$apiDownNotification->type()->value] ?? false;

                // Rate limiting is handled by RateLimited middleware in the notification
                if ($isEligible) {
                    // Queue the notification (will be processed asynchronously)
                    // Rate limiting happens at the queue middleware level
                    $maintenanceUser->notify($apiDownNotification);
                }
            }
        }

        return $result;
    }
}
