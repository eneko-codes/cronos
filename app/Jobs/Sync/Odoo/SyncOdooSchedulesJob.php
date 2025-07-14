<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooScheduleAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;

/**
 * Job to synchronize Odoo schedule data (resource.calendar) with the local schedules table.
 *
 * This job fetches all schedules from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all schedules from Odoo
 * - Create or update local schedules
 */
class SyncOdooSchedulesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooSchedulesJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching schedules.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches schedules from Odoo and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $schedules = $this->odoo->getSchedules();
        $schedules->each(function (OdooScheduleDTO $schedulesDto): void {
            (new ProcessOdooScheduleAction)->execute($schedulesDto);
        });
    }

    public function failed(): void
    {
        app(CheckOdooHealthAction::class)($this->odoo);
    }
}
