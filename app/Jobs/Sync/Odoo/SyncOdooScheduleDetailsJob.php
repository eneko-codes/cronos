<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooScheduleDetailAction;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;
use Illuminate\Support\Collection;

/**
 * Job to synchronize Odoo schedule detail data (resource.calendar.attendance) with the local schedule_details table.
 *
 * Ensures the local schedule_details database reflects the current state of Odoo, including:
 * - Creating or updating schedule details (time slots)
 */
class SyncOdooScheduleDetailsJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncOdooScheduleDetails job instance.
     *
     * @param  Collection<int, OdooScheduleDetailDTO>  $scheduleDetails  The collection of OdooScheduleDetailDTOs to sync.
     */
    public function __construct(
        private Collection $scheduleDetails
    ) {}

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Creates or updates local schedule details based on Odoo data.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Create or update local schedule details (ensures local DB matches Odoo)
        $this->scheduleDetails->each(function (OdooScheduleDetailDTO $scheduleDetailDTO): void {
            (new SyncOdooScheduleDetailAction)->execute($scheduleDetailDTO);
        });
    }
}
