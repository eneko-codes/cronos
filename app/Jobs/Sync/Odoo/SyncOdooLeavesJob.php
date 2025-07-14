<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooLeavesAction;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;
use Illuminate\Support\Collection;

/**
 * Job to synchronize Odoo leave data (hr.leave) with the local user_leaves table.
 *
 * This job receives a collection of OdooLeaveDTOs and dispatches
 * SyncOdooLeavesAction for each to ensure data integrity and
 * updates the local user_leaves database to reflect the current state of Odoo.
 */
class SyncOdooLeavesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncOdooLeavesJob instance.
     *
     * @param  Collection<int, OdooLeaveDTO>  $leaves  The collection of OdooLeaveDTOs to sync.
     */
    public function __construct(private Collection $leaves) {}

    /**
     * Main entry point for the job's sync logic.
     *
     * Iterates through the provided collection of OdooLeaveDTOs and dispatches
     * SyncOdooLeavesAction for each, handling the creation or update of local leaves.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        $this->leaves->each(function (OdooLeaveDTO $leaveDto): void {
            (new SyncOdooLeavesAction)->execute($leaveDto);
        });
    }
}
