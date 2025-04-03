<?php

namespace App\Jobs;

use App\Models\LeaveType;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncOdooLeaveTypes
 *
 * Synchronizes hr.leave.type data from Odoo into local leave_types table.
 * This job ensures local leave types match the current state in Odoo,
 * including creating new types, updating existing ones, and preserving
 * leave types that no longer exist in Odoo for historical integrity.
 */
class SyncOdooLeaveTypes extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 2;

  /**
   * SyncOdooLeaveTypes constructor.
   *
   * @param OdooApiCalls $odoo An instance of the OdooApiCalls service.
   */
  public function __construct(OdooApiCalls $odoo)
  {
    $this->odoo = $odoo;
  }

  /**
   * Executes the synchronization process.
   *
   * This method performs the following operations:
   * 1. Fetches leave types from Odoo API and maps them to local structure
   * 2. Creates or updates local leave types based on Odoo data
   * 3. Identifies leave types that exist locally but not in Odoo
   * 4. Logs missing leave types for historical integrity
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Step 1: Fetch and map leave types from Odoo
    $mappedLeaveTypes = $this->odoo->getLeaveTypes()->map(function ($lt) {
      return [
        'odoo_leave_type_id' => $lt['id'],
        'name' => $lt['name'],
        'limit' => $lt['limit'] ?? false,
        'requires_allocation' => $lt['requires_allocation'] ?? false,
        'active' => $lt['active'] ?? true,
      ];
    });

    // Step 2: Create or update local leave types based on Odoo data individually to trigger model events
    $mappedLeaveTypes->each(function ($leaveType) {
      LeaveType::updateOrCreate(
        ['odoo_leave_type_id' => $leaveType['odoo_leave_type_id']],
        [
          'name' => $leaveType['name'],
          'limit' => $leaveType['limit'],
          'requires_allocation' => $leaveType['requires_allocation'],
          'active' => $leaveType['active'],
        ]
      );
    });

    // Step 3: Identifies leave types that exist locally but not in Odoo
    $odooIds = $mappedLeaveTypes->pluck('odoo_leave_type_id');
    $localIds = LeaveType::pluck('odoo_leave_type_id');
    $leaveTypesToLog = $localIds->diff($odooIds);

    // Step 4: Log leave types that exist locally but not in Odoo for historical integrity
    if ($leaveTypesToLog->isNotEmpty()) {
      LeaveType::whereIn('odoo_leave_type_id', $leaveTypesToLog)
        ->get()
        ->each(function ($leaveType) {
          Log::channel('sync')->info(
            class_basename($this) .
              ': Leave type no longer exists in Odoo but preserved for historical integrity',
            [
              'odoo_leave_type_id' => $leaveType->odoo_leave_type_id,
              'name' => $leaveType->name,
              'detected_at' => now()->toDateTimeString(),
            ]
          );
        });
    }
  }
}
