<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooScheduleAction;
use App\Actions\Odoo\SyncOdooScheduleDetailAction;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;
use Illuminate\Support\Collection;

/**
 * Job to synchronize Odoo schedule data (resource.calendar) with the local schedules table.
 *
 * Ensures the local schedules database reflects the current state of Odoo, including:
 * - Creating or updating schedules and schedule details (time slots)
 * - Logging and preserving schedules that no longer exist in Odoo
 * - Detecting and notifying about duplicate schedule details
 * - Updating user schedule assignments with historical tracking
 */
class SyncOdooSchedulesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncOdooSchedules job instance.
     *
     * @param  Collection<int, OdooScheduleDTO>  $schedules  The collection of OdooScheduleDTOs to sync.
     * @param  Collection<int, OdooScheduleDetailDTO>  $scheduleDetails  The collection of OdooScheduleDetailDTOs to sync.
     */
    public function __construct(
        private Collection $schedules,
        private Collection $scheduleDetails
    ) {}

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Creates or updates local schedules based on Odoo data.
     * 2. Logs schedules that exist locally but not in Odoo for historical integrity.
     * 3. Synchronizes schedule details (time slots) for each schedule.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Create or update local schedules (ensures local DB matches Odoo)
        $this->schedules->each(function (OdooScheduleDTO $schedulesDto): void {
            (new SyncOdooScheduleAction)->execute($schedulesDto);
        });

        // Create or update local schedule details (ensures local DB matches Odoo)
        $this->scheduleDetails->each(function (OdooScheduleDetailDTO $scheduleDetailDTO): void {
            (new SyncOdooScheduleDetailAction)->execute($scheduleDetailDTO);
        });

    }
}
