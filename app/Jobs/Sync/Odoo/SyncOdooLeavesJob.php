<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooLeavesAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;

/**
 * Job to synchronize Odoo leave data (hr.leave) with the local user_leaves table.
 *
 * This job fetches all leaves from Odoo for a given date range using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all leaves from Odoo for the specified date range
 * - Create or update local user leaves
 */
class SyncOdooLeavesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Odoo API client instance used to fetch leave data.
     */
    protected OdooApiClient $odoo;

    /**
     * The start date (Y-m-d) for fetching leaves from Odoo.
     */
    protected string $fromDate;

    /**
     * The end date (Y-m-d) for fetching leaves from Odoo.
     */
    protected string $toDate;

    /**
     * Constructs a new SyncOdooLeavesJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching leaves.
     * @param  string  $fromDate  The start date for fetching leaves (Y-m-d).
     * @param  string  $toDate  The end date for fetching leaves (Y-m-d).
     */
    public function __construct(OdooApiClient $odoo, string $fromDate, string $toDate)
    {
        $this->odoo = $odoo;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches leaves from Odoo for the specified date range and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $leaves = $this->odoo->getLeaves($this->fromDate, $this->toDate);
        $leaves->each(function (OdooLeaveDTO $leaveDto): void {
            (new ProcessOdooLeavesAction)->execute($leaveDto);
        });
    }

    public function failed(): void
    {
        app(CheckOdooHealthAction::class)($this->odoo);
    }
}
