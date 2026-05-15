<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubUserAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\Enums\Platform;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\UserExternalIdentity;
use App\Notifications\UnlinkedPlatformUserNotification;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub users with the local users table.
 *
 * This job fetches all users from ProofHub and updates the corresponding local users
 * with their ProofHub ID. It does not create new users, only updates existing ones.
 *
 * Responsibilities:
 * - Fetch all users from ProofHub
 * - Link local users to their ProofHub identities
 * - Delete external identities for users no longer present in ProofHub
 */
class SyncProofhubUsersJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected ProofhubApiClient $proofhub;

    /**
     * Constructs a new SyncProofhubUsersJob job.
     *
     * @param  ProofhubApiClient  $proofhub  The ProofHub API client.
     */
    public function __construct(ProofhubApiClient $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches users from ProofHub and processes each one, then deletes
     * external identities for users no longer present in ProofHub.
     */
    public function handle(ProcessProofhubUserAction $action, NotificationService $notificationService): void
    {
        $users = $this->proofhub->getUsers();

        // Extract ProofHub IDs before processing
        $apiIds = $users->pluck('id')->filter();

        // Collect unlinked users during sync
        /** @var Collection<int, UnlinkedUser> $unlinkedUsers */
        $unlinkedUsers = new Collection;

        // Process each user DTO
        $users->each(function (ProofhubUserDTO $user) use ($action, $unlinkedUsers): void {
            $unlinkedUser = $action->execute($user);
            if ($unlinkedUser !== null) {
                $unlinkedUsers->push($unlinkedUser);
            }
        });

        // Cleanup: delete external identities no longer in ProofHub
        $this->cleanupMissingIdentities($apiIds);

        // Send aggregated notification if there are unlinked users
        if ($unlinkedUsers->isNotEmpty()) {
            $notification = new UnlinkedPlatformUserNotification(
                platform: Platform::ProofHub,
                unlinkedUsers: $unlinkedUsers,
            );

            $notificationService->notifyMaintenanceUsers($notification);
        }
    }

    /**
     * Delete ProofHub external identities that are no longer present in the API response.
     *
     * @param  Collection  $apiIds  Collection of ProofHub user IDs from the API response.
     */
    private function cleanupMissingIdentities(Collection $apiIds): void
    {
        // Convert IDs to strings for comparison with external_id column
        $apiIdsStr = $apiIds->map(fn ($id) => (string) $id);

        $deletedCount = UserExternalIdentity::where('platform', Platform::ProofHub)
            ->whereNotIn('external_id', $apiIdsStr)
            ->delete();

        if ($deletedCount > 0) {
            Log::debug('SyncProofhubUsersJob: Deleted external identities no longer in ProofHub', [
                'deleted_count' => $deletedCount,
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the ProofHub API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        $notificationService = app(NotificationService::class);
        $checkHealth = new CheckProofhubHealthAction($notificationService);
        $checkHealth($this->proofhub);
    }
}
