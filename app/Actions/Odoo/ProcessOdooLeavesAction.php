<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize a single Odoo leave DTO with the local database.
 *
 * This class encapsulates the business logic for creating or updating a leave record.
 * It performs validation on the incoming DTO and ensures the database operation is atomic.
 */
final class ProcessOdooLeavesAction
{
    /**
     * Executes the synchronization logic for a single Odoo leave DTO.
     *
     * Performs validation on the provided DTO. If validation fails, a warning is logged,
     * and the synchronization for that leave is skipped. Otherwise, the leave
     * record is created or updated within a database transaction to ensure data integrity.
     *
     * @param  OdooLeaveDTO  $leaveDto  The OdooLeaveDTO to sync.
     */
    public function execute(OdooLeaveDTO $leaveDto): void
    {
        // Validate required fields using Laravel's Validator
        $validator = Validator::make(
            [
                'id' => $leaveDto->id,
                'holiday_type' => $leaveDto->holiday_type,
                'date_from' => $leaveDto->date_from,
                'date_to' => $leaveDto->date_to,
                'number_of_days' => $leaveDto->number_of_days,
                'holiday_status_id' => $leaveDto->holiday_status_id,
                'state' => $leaveDto->state,
            ],
            [
                'id' => 'required',
                'holiday_type' => 'required',
                'date_from' => 'required',
                'date_to' => 'required',
                'number_of_days' => 'required',
                'holiday_status_id' => 'required',
                'state' => 'required',
            ]
        );

        // Additional validation: for employee-type leaves, ensure the user exists locally
        $validator->after(function ($validator) use ($leaveDto): void {
            if ($leaveDto->holiday_type === 'employee') {
                // Extract the Odoo employee ID from the DTO array
                $employeeOdooId = Arr::get($leaveDto->employee_id, 0);
                // If the employee Odoo ID is missing or the user does not exist, add a validation error
                if (! $employeeOdooId || ! User::findByOdooId($employeeOdooId)) {
                    $validator->errors()->add('user_id', 'User not found for Odoo employee ID: '.$employeeOdooId);
                }
            }
        });

        // If validation fails, log a warning and skip this leave
        if ($validator->fails()) {
            Log::warning(class_basename(self::class).' Skipping leave due to validation errors', [
                'leave' => $leaveDto,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        // All validation passed; proceed to create or update the leave record
        DB::transaction(function () use ($leaveDto): void {
            // Extract related Odoo IDs
            $employeeOdooId = Arr::get($leaveDto->employee_id, 0);
            $userId = null;
            if ($leaveDto->holiday_type === 'employee' && $employeeOdooId) {
                // Look up the local user by their Odoo employee ID.
                $user = User::findByOdooId($employeeOdooId);
                $userId = $user ? $user->id : null;
            }
            // Create or update the UserLeave record using odoo_leave_id as the unique key
            UserLeave::updateOrCreate(
                ['odoo_leave_id' => $leaveDto->id],
                [
                    'type' => $leaveDto->holiday_type,
                    'start_date' => $leaveDto->date_from,
                    'end_date' => $leaveDto->date_to,
                    'status' => $leaveDto->state,
                    'duration_days' => $leaveDto->number_of_days,
                    'leave_type_id' => Arr::get($leaveDto->holiday_status_id, 0),
                    'user_id' => $userId,
                    'department_id' => Arr::get($leaveDto->department_id, 0),
                    'category_id' => Arr::get($leaveDto->category_id, 0),
                    'request_hour_from' => $leaveDto->request_hour_from ?? null,
                    'request_hour_to' => $leaveDto->request_hour_to ?? null,
                ]
            );
        });
    }
}
