<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooScheduleDetailAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;

/**
 * Job to synchronize Odoo schedule detail data (resource.calendar.attendance) with the local schedule_details table.
 *
 * This job fetches all schedule details from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all schedule details from Odoo
 * - Create or update local schedule details
 */
class SyncOdooScheduleDetailsJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooScheduleDetailsJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching schedule details.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches schedule details from Odoo and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $scheduleDetails = $this->odoo->getScheduleDetails();
        $scheduleDetails->each(function (OdooScheduleDetailDTO $scheduleDetailDTO): void {
            (new ProcessOdooScheduleDetailAction)->execute($scheduleDetailDTO);
        });
    }

    public function failed(): void
    {
        app(CheckOdooHealthAction::class)($this->odoo);
    }
}
