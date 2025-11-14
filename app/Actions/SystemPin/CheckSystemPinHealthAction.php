<?php

declare(strict_types=1);

namespace App\Actions\SystemPin;

use App\Actions\GetNotificationPreferencesAction;
use App\Clients\SystemPinApiClient;
use App\Enums\RoleType;
use App\Models\User;
use App\Notifications\ApiDownWarning;

/**
 * Action to check the health of the SystemPin API and notify maintenance users on failure.
 *
 * This action is typically called from sync job failure handlers when SystemPin
 * sync jobs fail. It performs a health check ping to the SystemPin API and,
 * if the check fails, sends ApiDownWarning notifications to all eligible maintenance users.
 *
 * The ApiDownWarning notification includes built-in deduplication logic to prevent
 * email spam when multiple sync jobs fail simultaneously. Each maintenance user will receive
 * at most one notification per 60-minute window per service.
 *
 * @see \App\Notifications\ApiDownWarning For notification deduplication details
 * @see \App\Jobs\Sync\SystemPin\SyncSystempinUsersJob Typical caller
 * @see \App\Jobs\Sync\SystemPin\SyncSystempinAttendancesJob Typical caller
 */
class CheckSystemPinHealthAction
{
    /**
     * Execute the SystemPin API health check and send notifications if the API is down.
     *
     * This method:
     * 1. Performs a ping health check to the SystemPin API
     * 2. If the health check fails, retrieves all maintenance users
     * 3. Creates an ApiDownWarning notification for each eligible maintenance user
     * 4. Respects user notification preferences before sending
     *
     * The notification is queued (implements ShouldQueue), so it will be processed
     * asynchronously. The ApiDownWarning class handles deduplication automatically.
     *
     * @param  SystemPinApiClient  $client  The SystemPin API client instance
     * @return array<string, mixed> Health check result with 'success' boolean and optional 'message'
     */
    public function __invoke(SystemPinApiClient $client): array
    {
        $result = $client->ping();

        // Only send notifications if the health check failed
        if (! ($result['success'] ?? false)) {
            // Get all maintenance users who should receive API down warnings
            $maintenanceUsers = User::where('user_type', RoleType::Maintenance)->get();

            // Create a single notification instance (will be reused for all maintenance users)
            // The ApiDownWarning class handles deduplication per user automatically
            $apiDownNotification = new ApiDownWarning(
                'SystemPin',
                $result['message'] ?? 'API health check failed'
            );

            $getPreferences = resolve(GetNotificationPreferencesAction::class);

            // Send notification to each eligible maintenance user
            foreach ($maintenanceUsers as $maintenanceUser) {
                // Check if this maintenance user has enabled ApiDownWarning notifications
                $preferences = $getPreferences->execute($maintenanceUser);
                $isEligible = $preferences['eligibility'][$apiDownNotification->type()->value] ?? false;

                if ($isEligible) {
                    // Queue the notification (will be processed asynchronously)
                    // The ApiDownWarning::via() method will check for duplicates
                    $maintenanceUser->notify($apiDownNotification);
                }
            }
        }

        return $result;
    }
}
