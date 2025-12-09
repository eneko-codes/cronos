<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Desktime;

use App\Actions\Desktime\CheckDesktimeHealthAction;
use App\Actions\Desktime\ProcessDesktimeUserAction;
use App\Clients\DesktimeApiClient;
use App\DataTransferObjects\Desktime\DesktimeUserDTO;
use App\Jobs\Sync\BaseSyncJob;

/**
 * Job to synchronize DeskTime user information with the local database.
 *
 * Updates local users with their DeskTime IDs.
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
     */
    public function handle(ProcessDesktimeUserAction $action): void
    {
        $users = $this->desktime->getAllEmployees(null, 'month');
        $users->each(function (DesktimeUserDTO $userDto) use ($action): void {
            $action->execute($userDto);
        });
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the DeskTime API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        app(CheckDesktimeHealthAction::class)($this->desktime);
    }
}
