<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Models\LeaveType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize a single Odoo leave type DTO with the local database.
 *
 * This class encapsulates the business logic for creating or updating a leave type record.
 * It performs validation on the incoming DTO and ensures the database operation is atomic.
 */
final class SyncOdooLeaveTypeAction
{
    /**
     * Executes the synchronization logic for a single Odoo Leave Type DTO.
     *
     * Performs validation on the provided DTO. If validation fails, a warning is logged,
     * and the synchronization for that leave type is skipped. Otherwise, the leave type
     * record is created or updated within a database transaction to ensure data integrity.
     *
     * @param  OdooLeaveTypeDTO  $leaveTypeDto  The OdooLeaveTypeDTO to sync.
     */
    public function execute(OdooLeaveTypeDTO $leaveTypeDto): void
    {
        $validator = Validator::make(
            [
                'name' => $leaveTypeDto->name,
                'active' => $leaveTypeDto->active,
                'odoo_leave_type_id' => $leaveTypeDto->id,
            ],
            [
                'name' => 'required',
                'active' => 'required',
                'odoo_leave_type_id' => 'required',
            ]
        );

        if ($validator->fails()) {
            Log::warning(class_basename(self::class).' Skipping leave type due to validation errors', [
                'leave_type' => $leaveTypeDto,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($leaveTypeDto): void {
            // Map allocation_type to requires_allocation for compatibility if needed
            $requiresAllocation = match ($leaveTypeDto->allocation_type ?? 'no') {
                'fixed_allocation', 'fixed' => true,
                default => false,
            };

            // Create or update the leave type record
            LeaveType::updateOrCreate(
                ['odoo_leave_type_id' => $leaveTypeDto->id],
                [
                    'name' => $leaveTypeDto->name,
                    'validation_type' => $leaveTypeDto->validation_type ?? null,
                    'request_unit' => $leaveTypeDto->request_unit ?? null,
                    'limit' => false,
                    'requires_allocation' => $requiresAllocation,
                    'active' => $leaveTypeDto->active ?? true,
                    'is_unpaid' => $leaveTypeDto->unpaid ?? false,
                ]
            );
        });
    }
}
