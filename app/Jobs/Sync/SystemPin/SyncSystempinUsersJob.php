<?php

declare(strict_types=1);

namespace App\Jobs\Sync\SystemPin;

use App\Actions\SystemPin\CheckSystemPinHealthAction;
use App\Actions\SystemPin\ProcessSystemPinUserAction;
use App\Clients\SystemPinApiClient;
use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\Enums\Platform;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\UserExternalIdentity;
use App\Notifications\UnlinkedPlatformUserNotification;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize SystemPin user information with the local database.
 *
 * Updates local users with their SystemPin IDs and deletes external identities
 * for users no longer present in SystemPin.
 *
 * Responsibilities:
 * - Fetch all employees from SystemPin
 * - Link local users to their SystemPin identities
 * - Delete external identities for users no longer present in SystemPin
 */
class SyncSystemPinUsersJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected SystemPinApiClient $systempin;

    /**
     * Constructs a new SyncSystemPinUsersJob instance.
     */
    public function __construct(SystemPinApiClient $systempin)
    {
        $this->systempin = $systempin;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches users from SystemPin and processes each one, then deletes
     * external identities for users no longer present in SystemPin.
     */
    public function handle(ProcessSystemPinUserAction $action, NotificationService $notificationService): void
    {
        $users = $this->systempin->getAllEmployees();

        // Extract SystemPin IDs before processing
        $apiIds = $users->pluck('id')->filter();

        // Collect unlinked users during sync
        /** @var Collection<int, UnlinkedUser> $unlinkedUsers */
        $unlinkedUsers = new Collection;

        // Process each user DTO
        $users->each(function (SystemPinUserDTO $userDto) use ($action, $unlinkedUsers): void {
            $unlinkedUser = $action->execute($userDto);
            if ($unlinkedUser !== null) {
                $unlinkedUsers->push($unlinkedUser);
            }
        });

        // Cleanup: delete external identities no longer in SystemPin
        $this->cleanupMissingIdentities($apiIds);

        // Send aggregated notification if there are unlinked users
        if ($unlinkedUsers->isNotEmpty()) {
            $notification = new UnlinkedPlatformUserNotification(
                platform: Platform::SystemPin,
                unlinkedUsers: $unlinkedUsers,
            );

            $notificationService->notifyMaintenanceUsers($notification);
        }
    }

    /**
     * Delete SystemPin external identities that are no longer present in the API response.
     *
     * @param  Collection  $apiIds  Collection of SystemPin user IDs from the API response.
     */
    private function cleanupMissingIdentities(Collection $apiIds): void
    {
        // Convert IDs to strings for comparison with external_id column
        $apiIdsStr = $apiIds->map(fn ($id) => (string) $id);

        $deletedCount = UserExternalIdentity::where('platform', Platform::SystemPin)
            ->whereNotIn('external_id', $apiIdsStr)
            ->delete();

        if ($deletedCount > 0) {
            Log::debug('SyncSystemPinUsersJob: Deleted external identities no longer in SystemPin', [
                'deleted_count' => $deletedCount,
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the SystemPin API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        $notificationService = app(NotificationService::class);
        $checkHealth = new CheckSystemPinHealthAction($notificationService);
        $checkHealth($this->systempin);
    }
}
