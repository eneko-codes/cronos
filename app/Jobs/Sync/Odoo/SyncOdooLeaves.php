<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserLeave;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo leave data (hr.leave) with the local user_leaves table.
 *
 * Ensures local leave records match the current state in Odoo, including:
 * - Fetching validated leaves (optionally filtered by date range)
 * - Creating or updating local leave records
 * - Removing obsolete leaves
 * - Logging and handling invalid or unexpected data
 */
class SyncOdooLeaves extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Optional date range parameters to limit the scope of the sync.
     */
    private ?string $startDate;

    private ?string $endDate;

    /**
     * Constructs a new SyncOdooLeaves job instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client instance.
     * @param  string|null  $startDate  Optional start date (e.g., '2025-01-13').
     * @param  string|null  $endDate  Optional end date (e.g., '2025-01-13').
     */
    public function __construct(
        OdooApiClient $odoo,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $this->odoo = $odoo;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * - Fetches leaves from Odoo API (optionally filtered by date range)
     * - Gets valid leave type IDs from local database
     * - Removes local leaves that no longer exist in Odoo
     * - Processes and updates local leaves based on Odoo data
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Fetch leaves from Odoo API with optional date filtering
        $odooLeaves = $this->odoo->getLeaves($this->startDate, $this->endDate);

        // Get valid leave type IDs from local database (used to validate incoming leaves)
        $validLeaveTypeIds = LeaveType::pluck('odoo_leave_type_id');

        // Remove leaves that no longer exist in Odoo (clean up local records)
        $this->removeObsoleteLeaves($odooLeaves->pluck('id'));

        // Process and update local leaves based on Odoo data
        $this->syncLeaves($odooLeaves, $validLeaveTypeIds);

        // Optionally, you may want to log or audit the actual date range of the data received
        // $fromDates = $odooLeaves->pluck('date_from')->filter();
        // $toDates = $odooLeaves->pluck('date_to')->filter();
        // if ($fromDates->isNotEmpty() && $toDates->isNotEmpty()) {
        //     $minFrom = $fromDates->min();
        //     $maxTo = $toDates->max();
        //     // Use $minFrom and $maxTo as needed
        // }
    }

    /**
     * Removes local leave records that no longer exist in Odoo.
     *
     * Deletes UserLeave records that are not present in the current Odoo dataset.
     * If a date range is provided, only leaves within that range are considered for deletion.
     *
     * @param  Collection  $currentOdooLeaveIds  Collection of leave IDs from Odoo.
     */
    private function removeObsoleteLeaves(Collection $currentOdooLeaveIds): void
    {
        $deleteQuery = UserLeave::query();
        // If a date range is provided, restrict deletion to that range
        if ($this->startDate && $this->endDate) {
            $deleteQuery->where(function ($query): void {
                $query
                    ->where('start_date', '<=', $this->endDate.' 23:59:59')
                    ->where('end_date', '>=', $this->startDate.' 00:00:00');
            });
        }
        // Delete leaves not in the current Odoo dataset
        $deleteQuery
            ->whereNotIn('odoo_leave_id', $currentOdooLeaveIds)
            ->get()
            ->each->delete();
    }

    /**
     * Processes and creates/updates local leave records based on Odoo data.
     *
     * For each Odoo leave, this method will:
     * - Validate required fields
     * - Skip leaves with invalid leave types
     * - Log unexpected leave states
     * - Prepare the data for local storage
     * - Create or update the local UserLeave record
     *
     * @param  Collection|OdooLeaveDTO[]  $odooLeaves  Leaves from Odoo API.
     * @param  Collection  $validLeaveTypeIds  Valid leave type IDs from local database.
     */
    private function syncLeaves(
        Collection $odooLeaves,
        Collection $validLeaveTypeIds
    ): void {
        $odooLeaves->each(function (OdooLeaveDTO $leave) use ($validLeaveTypeIds): void {
            // Skip leaves with missing required fields
            if (! $this->validateLeaveFields($leave)) {
                return;
            }
            // Skip leaves with invalid leave type
            $leaveTypeId = $leave->holiday_status_id;
            if (! $validLeaveTypeIds->contains($leaveTypeId)) {
                $this->logInvalidLeaveType($leave, $leaveTypeId);

                return;
            }
            // Log unexpected leave states (for audit/debugging)
            $this->checkLeaveState($leave);
            // Prepare the data for local storage
            $leaveData = $this->prepareLeaveData($leave);
            // Create or update the leave record in the local database
            UserLeave::updateOrCreate(['odoo_leave_id' => $leave->id], $leaveData);
        });
    }

    /**
     * Validates that a leave record has all required fields.
     *
     * Checks for the presence of all required fields in the OdooLeaveDTO.
     * Logs and skips the record if any required field is missing.
     *
     * @param  OdooLeaveDTO  $leave  Leave record from Odoo.
     * @return bool Whether the leave has all required fields.
     */
    private function validateLeaveFields(OdooLeaveDTO $leave): bool
    {
        $required = [
            'holiday_type' => $leave->holiday_type,
            'date_from' => $leave->date_from,
            'date_to' => $leave->date_to,
            'number_of_days' => $leave->number_of_days,
            'holiday_status_id' => $leave->holiday_status_id,
            'state' => $leave->state,
        ];
        foreach ($required as $field => $value) {
            if ($value === null) {
                Log::warning(class_basename(static::class).' Skipping record: missing required field', [
                    'job' => class_basename(static::class),
                    'field' => $field,
                    'entity' => 'leave',
                    'entity_id' => $leave->id,
                ]);

                return false;
            }
        }

        return true;
    }

    /**
     * Logs when a leave has an invalid leave type.
     *
     * Called when a leave's holiday_status_id is not found in the list of valid leave types.
     *
     * @param  OdooLeaveDTO  $leave  Leave record from Odoo.
     * @param  int  $leaveTypeId  The leave type ID from Odoo.
     */
    private function logInvalidLeaveType(OdooLeaveDTO $leave, int $leaveTypeId): void
    {
        Log::warning(class_basename(static::class).' Skipping: invalid leave type', [
            'job' => class_basename(static::class),
            'entity' => 'leave',
            'entity_id' => $leave->id,
            'leave_type_id' => $leaveTypeId,
        ]);
    }

    /**
     * Checks and logs if a leave has an unexpected state.
     *
     * Compares the leave's state to a list of valid states and logs a warning if it is not recognized.
     *
     * @param  OdooLeaveDTO  $leave  Leave record from Odoo.
     */
    private function checkLeaveState(OdooLeaveDTO $leave): void
    {
        $validStates = [
            'validate',
            'refuse',
            'confirm',
            'validate1',
            'draft',
            'cancel',
        ];
        if (
            isset($leave->state) &&
            ! collect($validStates)->contains($leave->state)
        ) {
            Log::warning(class_basename(static::class).' Unexpected state', [
                'job' => class_basename(static::class),
                'entity' => 'leave',
                'entity_id' => $leave->id,
                'state' => $leave->state,
            ]);
        }
    }

    /**
     * Prepares the data array for creating or updating a UserLeave record.
     *
     * Converts an OdooLeaveDTO into an associative array suitable for the UserLeave model.
     * Handles assignment of user, department, or category based on the leave type.
     *
     * @param  OdooLeaveDTO  $leave  Leave record from Odoo.
     * @return array Prepared data for UserLeave.
     */
    private function prepareLeaveData(OdooLeaveDTO $leave): array
    {
        $data = [
            'type' => $leave->holiday_type,
            'start_date' => $leave->date_from, // stored as UTC
            'end_date' => $leave->date_to, // stored as UTC
            'status' => $leave->state,
            'duration_days' => $leave->number_of_days,
            'leave_type_id' => $leave->holiday_status_id ?? null,
            'user_id' => null,
            'department_id' => null,
            'category_id' => null,
            'request_hour_from' => $leave->request_hour_from ?? null,
            'request_hour_to' => $leave->request_hour_to ?? null,
        ];
        // Assign user/department/category based on leave type
        switch ($leave->holiday_type) {
            case 'employee':
                $this->assignEmployeeToLeave($leave, $data);
                break;
            case 'department':
                $data['department_id'] = $leave->department_id ?? null;
                break;
            case 'category':
                $data['category_id'] = $leave->category_id ?? null;
                break;
        }

        return $data;
    }

    /**
     * Assigns the user to the leave data array for employee-type leaves.
     *
     * Looks up the local user by Odoo employee ID and assigns the user_id in the leave data array.
     * Logs a warning if the user is not found.
     *
     * @param  OdooLeaveDTO  $leave  Leave record from Odoo.
     * @param  array  $data  The leave data array (by reference).
     */
    private function assignEmployeeToLeave(OdooLeaveDTO $leave, array &$data): void
    {
        $user = User::where('odoo_id', $leave->employee_id)->first();
        if (! $user) {
            Log::warning(class_basename(static::class).' Skipping: user not found for leave assignment', [
                'job' => class_basename(static::class),
                'entity' => 'user',
                'entity_id' => $leave->employee_id,
                'leave_id' => $leave->id,
            ]);
            $data['user_id'] = null;

            return;
        }
        $data['user_id'] = $user->id;
    }
}
