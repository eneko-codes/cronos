<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Desktime;

use App\Actions\Desktime\CheckDesktimeHealthAction;
use App\Actions\Desktime\ProcessDesktimeUserAction;
use App\Clients\DesktimeApiClient;
use App\DataTransferObjects\Desktime\DesktimeUserDTO;
use App\DataTransferObjects\UnlinkedUser;
use App\Enums\Platform;
use App\Jobs\Sync\BaseSyncJob;
use App\Notifications\UnlinkedPlatformUserNotification;
use App\Services\NotificationService;
use Illuminate\Support\Collection;

/**
 * Job to synchronize DeskTime user information with the local database.
 *
 * Updates local users with their DeskTime IDs.
 *
 * ## API Limitation - No Automatic Cleanup
 *
 * Unlike other platform sync jobs, this job does NOT automatically delete external
 * identities for users no longer present in DeskTime. This is due to an API limitation:
 *
 * - DeskTime API does not have a dedicated endpoint for listing all users
 * - The /employees endpoint returns users bundled with attendance data for a specific day/month
 * - Users without activity in the queried period may not appear in the response
 * - This makes it impossible to reliably determine if a user has been removed from DeskTime
 *
 * If DeskTime external identities need to be cleaned up, this must be done manually.
 */
class SyncDesktimeUsersJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected DesktimeApiClient $desktime;

    /**
     * Constructs a new SyncDesktimeUsersJob instance.
     *
     * @param  DesktimeApiClient  $desktime  The DeskTime API client.
     */
    public function __construct(DesktimeApiClient $desktime)
    {
        $this->desktime = $desktime;
    }

    /**
     * Main entry point for the job.
     *
     * Fetches users from DeskTime and processes each one using the action class.
     * Collects unlinked users and sends one aggregated notification at the end.
     *
     * Note: No automatic cleanup is performed due to API limitations.
     * See class documentation for details.
     */
    public function handle(ProcessDesktimeUserAction $action, NotificationService $notificationService): void
    {
        $users = $this->desktime->getAllEmployees(null, 'month');

        // Collect unlinked users during sync
        /** @var Collection<int, UnlinkedUser> $unlinkedUsers */
        $unlinkedUsers = new Collection;

        $users->each(function (DesktimeUserDTO $userDto) use ($action, $unlinkedUsers): void {
            $unlinkedUser = $action->execute($userDto);
            if ($unlinkedUser !== null) {
                $unlinkedUsers->push($unlinkedUser);
            }
        });

        // Send aggregated notification if there are unlinked users
        if ($unlinkedUsers->isNotEmpty()) {
            $notification = new UnlinkedPlatformUserNotification(
                platform: Platform::DeskTime,
                unlinkedUsers: $unlinkedUsers,
            );

            $notificationService->notifyMaintenanceUsers($notification);
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the DeskTime API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        $notificationService = app(NotificationService::class);
        $checkHealth = new CheckDesktimeHealthAction($notificationService);
        $checkHealth($this->desktime);
    }
}
