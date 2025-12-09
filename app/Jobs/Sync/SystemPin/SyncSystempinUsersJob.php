<?php

declare(strict_types=1);

namespace App\Jobs\Sync\SystemPin;

use App\Actions\SystemPin\CheckSystemPinHealthAction;
use App\Actions\SystemPin\ProcessSystemPinUserAction;
use App\Clients\SystemPinApiClient;
use App\DataTransferObjects\SystemPin\SystemPinUserDTO;
use App\Jobs\Sync\BaseSyncJob;

/**
 * Job to synchronize SystemPin user information with the local database.
 *
 * Updates local users with their SystemPin IDs and clears SystemPin IDs for users no longer present in SystemPin.
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
     * Fetches users from SystemPin and processes each one using the action class.
     */
    public function handle(ProcessSystemPinUserAction $action): void
    {
        $users = $this->systempin->getAllEmployees();
        $users->each(function (SystemPinUserDTO $userDto) use ($action): void {
            $action->execute($userDto);
        });
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the SystemPin API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        (new CheckSystemPinHealthAction)($this->systempin);
    }
}
