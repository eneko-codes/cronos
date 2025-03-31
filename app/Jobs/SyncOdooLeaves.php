<?php

namespace App\Jobs;

use App\Models\LeaveType;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncOdooLeaves
 *
 * Synchronizes hr.leave data from Odoo into local user_leaves.
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
   * We'll track the optional date range here.
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
   * Main execution logic.
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    // This calls the updated getLeaves() method that uses full-day UTC domain filters
    $odooLeaves = $this->odoo->getLeaves($this->startDate, $this->endDate);

    // Get IDs to preserve
    $odooLeaveIds = $odooLeaves->pluck('id')->toArray();

    // Delete query using overlap condition
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

    if ($deletedCount > 0) {
      Log::channel('sync')->info(
        "Deleted {$deletedCount} leaves no longer in Odoo"
      );
    }

    $validLeaveTypeIds = LeaveType::pluck('odoo_leave_type_id')->toArray();
    $processedCount = 0;
    $skippedCount = 0;

    foreach ($odooLeaves as $leave) {
      // Validate required fields
      if (
        !isset(
          $leave['holiday_type'],
          $leave['date_from'],
          $leave['date_to'],
          $leave['number_of_days']
        ) ||
        !isset($leave['holiday_status_id'][0])
      ) {
        Log::channel('sync')->warning(
          'Skipped Odoo leave due to missing required fields',
          [
            'leave_id' => $leave['id'] ?? 'unknown',
          ]
        );
        $skippedCount++;
        continue;
      }

      $leaveTypeId = $leave['holiday_status_id'][0];
      if (!in_array($leaveTypeId, $validLeaveTypeIds)) {
        Log::channel('sync')->warning(
          'Skipped Odoo leave due to invalid leave type',
          [
            'leave_id' => $leave['id'] ?? 'unknown',
            'leave_type_id' => $leaveTypeId,
          ]
        );
        $skippedCount++;
        continue;
      }

      // Prepare data for local DB
      $data = [
        'type' => $leave['holiday_type'],
        'start_date' => $leave['date_from'], // stored as UTC
        'end_date' => $leave['date_to'], // stored as UTC
        'status' => $leave['state'],
        'duration_days' => $leave['number_of_days'],
        'leave_type_id' => $leaveTypeId,
        'user_id' => null,
        'department_id' => null,
        'category_id' => null,
        // Explicitly handle half-day information, which can be null for full-day leaves
        'request_hour_from' => $leave['request_hour_from'] ?? null,
        'request_hour_to' => $leave['request_hour_to'] ?? null,
      ];

      // Log status information for reference - Odoo states can be:
      // - 'draft': Leave request created but not yet submitted
      // - 'confirm': Leave request is submitted but waiting for approval
      // - 'refuse': Leave request has been refused by manager
      // - 'validate1': Leave request approved by first approval level
      // - 'validate': Leave request is fully approved and active
      // - 'cancel': Leave request was cancelled
      if (
        isset($leave['state']) &&
        !in_array($leave['state'], [
          'validate',
          'refuse',
          'confirm',
          'validate1',
          'draft',
          'cancel',
        ])
      ) {
        Log::channel('sync')->warning('Found unexpected leave state', [
          'leave_id' => $leave['id'],
          'state' => $leave['state'],
        ]);
      }

      // Log half-day information for debug purposes when present
      if (
        isset($leave['request_hour_from'], $leave['request_hour_to']) &&
        $leave['number_of_days'] == 0.5
      ) {
        Log::channel('sync')->debug('Processing half-day leave', [
          'leave_id' => $leave['id'],
          'request_hour_from' => $leave['request_hour_from'],
          'request_hour_to' => $leave['request_hour_to'],
          'is_morning' => $leave['request_hour_from'] < 12.0,
        ]);
      }

      // Assign user/department/category
      switch ($leave['holiday_type']) {
        case 'employee':
          if (isset($leave['employee_id'][0])) {
            $user = User::where('odoo_id', $leave['employee_id'][0])
              ->where('do_not_track', false)
              ->first();
            $data['user_id'] = $user?->id;

            if (!$user && isset($leave['employee_id'][1])) {
              Log::channel('sync')->info(
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
          $data['department_id'] = $leave['department_id'][0] ?? null;
          break;

        case 'category':
          $data['category_id'] = $leave['category_id'][0] ?? null;
          break;
      }

      // Upsert local record
      UserLeave::updateOrCreate(['odoo_leave_id' => $leave['id']], $data);
      $processedCount++;
    }

    Log::channel('sync')->info('Odoo leaves sync completed', [
      'processed' => $processedCount,
      'skipped' => $skippedCount,
      'date_range' =>
        $this->startDate && $this->endDate
          ? "{$this->startDate} to {$this->endDate}"
          : 'all',
    ]);
  }
}
