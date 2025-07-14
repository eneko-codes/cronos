<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooUserAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;

/**
 * Job to synchronize Odoo employee data (hr.employee) with the local users table.
 *
 * This job fetches all users from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all users from Odoo
 * - Create or update local users
 */
class SyncOdooUsersJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooUsersJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching users.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches users from Odoo and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $users = $this->odoo->getUsers();
        $users->each(function (OdooUserDTO $employee): void {
            (new ProcessOdooUserAction)->execute($employee);
        });
    }

    public function failed(): void
    {
        app(CheckOdooHealthAction::class)($this->odoo);
    }
}
