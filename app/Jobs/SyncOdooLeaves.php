<?php

namespace App\Jobs;

use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Arr;

/**
 * Class SyncOdooLeaves
 *
 * Synchronizes hr.leave data from Odoo into local user_leaves table.
 * This job ensures local leave records match the current state in Odoo.
 * By default, it fetches validated leaves (via OdooApiCalls->getLeaves()).
 * If a date range is passed, it fetches only leaves overlapping that range.
 */
class SyncOdooLeaves extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * Optional date range parameters to limit the scope of the sync.
   */
  private ?string $startDate;
  private ?string $endDate;

  /**
   * SyncOdooLeaves constructor.
   *
   * @param OdooApiCalls $odoo
   * @param string|null  $startDate Optional start date (e.g., '2025-01-13')
   * @param string|null  $endDate   Optional end date (e.g., '2025-01-13')
   */
  public function __construct(
    OdooApiCalls $odoo,
    ?string $startDate = null,
    ?string $endDate = null
  ) {
    $this->odoo = $odoo;
    $this->startDate = $startDate;
    $this->endDate = $endDate;
  }

  /**
   * Executes the synchronization process.
   *
   * This method orchestrates the entire leave synchronization workflow
   * by calling more specific methods for each step in the process.
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Fetch leaves from Odoo API filtered by date range if provided
    $odooLeaves = $this->fetchOdooLeaves();

    // Get the valid leave type IDs from our local database
    $validLeaveTypeIds = LeaveType::pluck('odoo_leave_type_id');

    // Log and remove leaves no longer in Odoo
    $this->handleObsoleteLeaves($odooLeaves->pluck('id'));

    // Process and update local leaves based on Odoo data
    $this->processOdooLeaves($odooLeaves, $validLeaveTypeIds);
  }

  /**
   * Fetches leaves from Odoo API with optional date filtering.
   *
   * @return Collection Collection of leaves from Odoo
   * @throws Exception If API call fails
   */
  private function fetchOdooLeaves(): Collection
  {
    return $this->odoo->getLeaves($this->startDate, $this->endDate);
  }

  /**
   * Identifies and removes local leaves that no longer exist in Odoo.
   *
   * Unlike departments and categories which are preserved, leaves that
   * no longer exist in Odoo are removed from the local database.
   *
   * @param Collection $odooLeaveIds Collection of Odoo leave IDs to preserve
   * @return int Number of deleted leaves
   */
  private function handleObsoleteLeaves(Collection $odooLeaveIds): int
  {
    // Create delete query with optional date range filtering
    $deleteQuery = UserLeave::query();

    if ($this->startDate && $this->endDate) {
      $deleteQuery->where(function ($query) {
        // Same overlap logic: start_date <= range_end AND end_date >= range_start
        $query
          ->where('start_date', '<=', $this->endDate . ' 23:59:59')
          ->where('end_date', '>=', $this->startDate . ' 00:00:00');
      });
    }

    $deletedCount = 0;

    $deleteQuery
      ->whereNotIn('odoo_leave_id', $odooLeaveIds)
      ->get()
      ->each(function ($leave) use (&$deletedCount) {
        $leave->delete();
        $deletedCount++;
      });

    return $deletedCount;
  }

  /**
   * Processes and updates local leaves based on Odoo data.
   *
   * This method validates each leave, skips invalid ones with appropriate
   * logging, and creates or updates valid leaves in the local database.
   *
   * @param Collection $odooLeaves Collection of leaves from Odoo
   * @param Collection $validLeaveTypeIds Collection of valid leave type IDs
   * @return array Array with counts of processed and skipped leaves
   */
  private function processOdooLeaves(
    Collection $odooLeaves,
    Collection $validLeaveTypeIds
  ): array {
    $processedCount = 0;
    $skippedCount = 0;

    $odooLeaves->each(function ($leave) use (
      $validLeaveTypeIds,
      &$processedCount,
      &$skippedCount
    ) {
      // Skip leaves with missing required fields
      if (!$this->hasRequiredFields($leave)) {
        Log::channel('sync')->warning(
          'Skipped Odoo leave due to missing required fields',
          [
            'leave_id' => $leave['id'] ?? 'unknown',
          ]
        );
        $skippedCount++;
        return; // Continue to next iteration
      }

      // Skip leaves with invalid leave type
      $leaveTypeId = $leave['holiday_status_id'][0];
      if (!$validLeaveTypeIds->contains($leaveTypeId)) {
        Log::channel('sync')->warning(
          'Skipped Odoo leave due to invalid leave type',
          [
            'leave_id' => $leave['id'] ?? 'unknown',
            'leave_type_id' => $leaveTypeId,
          ]
        );
        $skippedCount++;
        return; // Continue to next iteration
      }

      // Log unexpected leave states
      $this->logUnexpectedLeaveStates($leave);

      // Prepare and store leave data
      $data = $this->prepareLeaveData($leave);

      // Create or update the leave record
      UserLeave::updateOrCreate(['odoo_leave_id' => $leave['id']], $data);
      $processedCount++;
    });

    return [
      'processed' => $processedCount,
      'skipped' => $skippedCount,
    ];
  }

  /**
   * Checks if a leave record has all required fields.
   *
   * @param array $leave The leave record to check
   * @return bool True if all required fields are present
   */
  private function hasRequiredFields(array $leave): bool
  {
    return Arr::has($leave, [
      'holiday_type',
      'date_from',
      'date_to',
      'number_of_days',
      'holiday_status_id.0',
    ]);
  }

  /**
   * Logs warnings for unexpected leave states.
   *
   * @param array $leave The leave record to check
   */
  private function logUnexpectedLeaveStates(array $leave): void
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
      isset($leave['state']) &&
      !collect($validStates)->contains($leave['state'])
    ) {
      Log::channel('sync')->warning('Found unexpected leave state', [
        'leave_id' => $leave['id'],
        'state' => $leave['state'],
      ]);
    }
  }

  /**
   * Prepares leave data for local storage.
   *
   * This method builds the data array for creating/updating a local leave record,
   * including handling user, department, and category assignments based on leave type.
   *
   * @param array $leave The leave record from Odoo
   * @return array Prepared data for local storage
   */
  private function prepareLeaveData(array $leave): array
  {
    $data = [
      'type' => $leave['holiday_type'],
      'start_date' => $leave['date_from'], // stored as UTC
      'end_date' => $leave['date_to'], // stored as UTC
      'status' => $leave['state'],
      'duration_days' => $leave['number_of_days'],
      'leave_type_id' => $leave['holiday_status_id'][0],
      'user_id' => null,
      'department_id' => null,
      'category_id' => null,
      // Explicitly handle half-day information, which can be null for full-day leaves
      'request_hour_from' => Arr::get($leave, 'request_hour_from'),
      'request_hour_to' => Arr::get($leave, 'request_hour_to'),
    ];

    // Assign user/department/category based on leave type
    switch ($leave['holiday_type']) {
      case 'employee':
        $this->assignEmployeeData($leave, $data);
        break;

      case 'department':
        $data['department_id'] = Arr::get($leave, 'department_id.0');
        break;

      case 'category':
        $data['category_id'] = Arr::get($leave, 'category_id.0');
        break;
    }

    return $data;
  }

  /**
   * Assigns employee-specific data to a leave record.
   *
   * This method finds the corresponding user and handles any missing user cases.
   *
   * @param array $leave The leave record from Odoo
   * @param array &$data Reference to the data array to be updated
   */
  private function assignEmployeeData(array $leave, array &$data): void
  {
    if (Arr::has($leave, 'employee_id.0')) {
      $user = User::where('odoo_id', $leave['employee_id'][0])
        ->where('do_not_track', false)
        ->first();
      $data['user_id'] = $user?->id;

      if (!$user && Arr::has($leave, 'employee_id.1')) {
        Log::channel('sync')->warning(
          'Employee not found or marked do_not_track',
          [
            'odoo_employee_id' => $leave['employee_id'][0],
            'odoo_employee_name' => $leave['employee_id'][1],
          ]
        );
      }
    }
  }
}
