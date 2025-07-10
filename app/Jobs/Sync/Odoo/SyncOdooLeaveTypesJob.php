<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooLeaveTypeAction;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\LeaveType;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
     * It also logs leave types that are no longer present in the provided collection.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        $this->leaveTypes->each(function (OdooLeaveTypeDTO $leaveTypeDto): void {
            (new SyncOdooLeaveTypeAction)->execute($leaveTypeDto);
        });

        // Log leave types that exist locally but not in Odoo
        $this->logMissingLeaveTypes($this->leaveTypes->pluck('id'));
    }

    /**
     * Logs leave types that exist locally but not in Odoo for historical integrity.
     *
     * Finds leave types in the local database that are not present in the current
     * Odoo leave type list and logs them for historical tracking.
     *
     * @param  Collection  $currentOdooLeaveTypeIds  Collection of current Odoo leave type IDs.
     */
    private function logMissingLeaveTypes(
        Collection $currentOdooLeaveTypeIds
    ): void {
        $missingLeaveTypes = LeaveType::whereNotIn('odoo_leave_type_id', $currentOdooLeaveTypeIds)
            ->get();
        // If there are no missing leave types, nothing to log
        if ($missingLeaveTypes->isEmpty()) {
            return;
        }
        // Log each missing leave type for historical integrity
        $missingLeaveTypes->each(function ($leaveType): void {
            Log::info(
                class_basename(self::class).': Leave type no longer exists in Odoo but preserved for historical integrity',
                [
                    'odoo_leave_type_id' => $leaveType->odoo_leave_type_id,
                    'name' => $leaveType->name,
                    'detected_at' => now()->toDateTimeString(),
                ]
            );
        });
    }
}
