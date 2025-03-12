<?php

namespace App\Jobs;

use App\Models\LeaveType;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class SyncOdooLeaveTypes
 *
 * Synchronizes hr.leave.type data from Odoo into local leave_types,
 * and invalidates the entire cache store upon completion.
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
   * Removed protected OdooApiCalls $odoo;
   */
  public function __construct(OdooApiCalls $odoo)
  {
    $this->odoo = $odoo;
  }

  protected function execute(): void
  {
    $odooLeaveTypes = $this->odoo->getLeaveTypes();

    $mapped = $odooLeaveTypes->map(
      fn($lt) => [
        'odoo_leave_type_id' => $lt['id'],
        'name' => $lt['name'],
        'limit' => $lt['limit'] ?? false,
        'requires_allocation' => $lt['requires_allocation'] ?? false,
        'active' => $lt['active'] ?? true,
      ]
    );

    // Upsert
    foreach ($mapped as $leaveType) {
      LeaveType::updateOrCreate(
        ['odoo_leave_type_id' => $leaveType['odoo_leave_type_id']],
        [
          'name' => $leaveType['name'],
          'limit' => $leaveType['limit'],
          'requires_allocation' => $leaveType['requires_allocation'],
          'active' => $leaveType['active'],
        ]
      );
    }

    // Delete local leave types not in Odoo
    $odooIds = $mapped->pluck('odoo_leave_type_id');
    $localIds = LeaveType::pluck('odoo_leave_type_id');

    $toDelete = $localIds->diff($odooIds);

    if ($toDelete->isNotEmpty()) {
      LeaveType::whereIn('odoo_leave_type_id', $toDelete)
        ->get()
        ->each(fn($leaveType) => $leaveType->delete());
    }
  }
}
