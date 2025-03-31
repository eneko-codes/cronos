<?php

namespace App\Jobs;

use App\Models\LeaveType;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Collection;
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
   * This method orchestrates the entire leave type synchronization workflow
   * by calling more specific methods for each step in the process.
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Fetch and map leave types from Odoo
    $mappedLeaveTypes = $this->fetchAndMapLeaveTypes();

    // Synchronize local leave types with Odoo data
    $this->syncLocalLeaveTypes($mappedLeaveTypes);

    // Log leave types that exist locally but not in Odoo
    $this->logMissingLeaveTypes($mappedLeaveTypes);
  }

  /**
   * Fetches leave types from Odoo and maps them to our local structure.
   *
   * @return Collection Collection of mapped leave type data
   * @throws Exception If API call fails
   */
  private function fetchAndMapLeaveTypes(): Collection
  {
    $odooLeaveTypes = $this->odoo->getLeaveTypes();

    return $odooLeaveTypes->map(function ($lt) {
      return [
        'odoo_leave_type_id' => $lt['id'],
        'name' => $lt['name'],
        'limit' => $lt['limit'] ?? false,
        'requires_allocation' => $lt['requires_allocation'] ?? false,
        'active' => $lt['active'] ?? true,
      ];
    });
  }

  /**
   * Creates or updates local leave types based on Odoo data.
   *
   * @param Collection $mappedLeaveTypes Collection of mapped leave type data from Odoo
   */
  private function syncLocalLeaveTypes(Collection $mappedLeaveTypes): void
  {
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
  }

  /**
   * Identifies and logs leave types that exist locally but not in Odoo.
   * Leave types are preserved for historical integrity rather than deleted.
   *
   * @param Collection $mappedLeaveTypes Collection of mapped leave type data from Odoo
   */
  private function logMissingLeaveTypes(Collection $mappedLeaveTypes): void
  {
    $odooIds = $mappedLeaveTypes->pluck('odoo_leave_type_id');
    $localIds = LeaveType::pluck('odoo_leave_type_id');

    $leaveTypesToLog = $localIds->diff($odooIds);

    if ($leaveTypesToLog->isNotEmpty()) {
      LeaveType::whereIn('odoo_leave_type_id', $leaveTypesToLog)
        ->get()
        ->each(function ($leaveType) {
          Log::channel('sync')->info(
            'Leave type no longer exists in Odoo but preserved for historical integrity',
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
