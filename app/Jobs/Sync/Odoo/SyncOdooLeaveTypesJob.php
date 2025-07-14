<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooLeaveTypeAction;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;
use Illuminate\Support\Collection;

/**
 * Job to synchronize Odoo leave type data (hr.leave.type) with the local leave_types table.
 *
 * This job receives a collection of Odoo leave type DTOs and dispatches
 * `SyncOdooLeaveTypeAction` for each to ensure data integrity and
 * updates the local leave types database to reflect the current state of Odoo.
 */
class SyncOdooLeaveTypesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncOdooLeaveTypes job instance.
     *
     * @param  Collection<int, OdooLeaveTypeDTO>  $leaveTypes  The collection of OdooLeaveTypeDTOs to sync.
     */
    public function __construct(private Collection $leaveTypes) {}

    /**
     * Main entry point for the job's sync logic.
     *
     * Iterates through the provided collection of `OdooLeaveTypeDTO`s and
     * dispatches `SyncOdooLeaveTypeAction` for each to handle the creation
     * or updating of local leave type records.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        $this->leaveTypes->each(function (OdooLeaveTypeDTO $leaveTypeDto): void {
            (new SyncOdooLeaveTypeAction)->execute($leaveTypeDto);
        });

    }
}
