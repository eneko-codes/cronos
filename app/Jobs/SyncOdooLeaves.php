<?php

namespace App\Jobs;

use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\OdooApiCalls;
use Exception;
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
   * This method performs the following operations:
   * 1. Fetches leaves from Odoo API filtered by date range if provided
   * 2. Gets valid leave type IDs from local database
   * 3. Removes local leaves that no longer exist in Odoo
   * 4. Processes and updates local leaves based on Odoo data
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Step 1: Fetch leaves from Odoo API with optional date filtering
    $odooLeaves = $this->odoo->getLeaves($this->startDate, $this->endDate);

    // Step 2: Get valid leave type IDs from local database
    $validLeaveTypeIds = LeaveType::pluck('odoo_leave_type_id');

    // Step 3: Remove leaves that no longer exist in Odoo individually to trigger model events
    $deleteQuery = UserLeave::query();
    if ($this->startDate && $this->endDate) {
      $deleteQuery->where(function ($query) {
        $query
          ->where('start_date', '<=', $this->endDate . ' 23:59:59')
          ->where('end_date', '>=', $this->startDate . ' 00:00:00');
      });
    }

    $deleteQuery
      ->whereNotIn('odoo_leave_id', $odooLeaves->pluck('id'))
      ->get()
      ->each->delete();

    // Step 4: Process and update local leaves based on Odoo data
    $odooLeaves->each(function ($leave) use ($validLeaveTypeIds) {
      // Skip leaves with missing required fields
      if (
        !Arr::has($leave, [
          'holiday_type',
          'date_from',
          'date_to',
          'number_of_days',
          'holiday_status_id.0',
        ])
      ) {
        Log::channel('sync')->warning(
          'Skipped Odoo leave due to missing required fields',
          ['leave_id' => $leave['id'] ?? 'unknown']
        );
        return;
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
        return;
      }

      // Log unexpected leave states
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

      // Prepare leave data
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
        'request_hour_from' => Arr::get($leave, 'request_hour_from'),
        'request_hour_to' => Arr::get($leave, 'request_hour_to'),
      ];

      // Assign user/department/category based on leave type
      switch ($leave['holiday_type']) {
        case 'employee':
          if (Arr::has($leave, 'employee_id.0')) {
            $user = User::where('odoo_id', $leave['employee_id'][0])
              ->trackable()
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
          break;

        case 'department':
          $data['department_id'] = Arr::get($leave, 'department_id.0');
          break;

        case 'category':
          $data['category_id'] = Arr::get($leave, 'category_id.0');
          break;
      }

      // Create or update the leave record
      UserLeave::updateOrCreate(['odoo_leave_id' => $leave['id']], $data);
    });
  }
}
