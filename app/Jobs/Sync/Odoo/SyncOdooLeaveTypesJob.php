<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooLeaveTypeAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;

/**
 * Job to synchronize Odoo leave type data (hr.leave.type) with the local leave_types table.
 *
 * This job fetches all leave types from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all leave types from Odoo
 * - Create or update local leave types
 */
class SyncOdooLeaveTypesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooLeaveTypesJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching leave types.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches leave types from Odoo and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $leaveTypes = $this->odoo->getLeaveTypes();
        $leaveTypes->each(function (OdooLeaveTypeDTO $leaveTypeDto): void {
            (new ProcessOdooLeaveTypeAction)->execute($leaveTypeDto);
        });
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the Odoo API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        app(CheckOdooHealthAction::class)($this->odoo);
    }
}
