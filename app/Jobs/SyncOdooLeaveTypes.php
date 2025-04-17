<?php

namespace App\Jobs;

use App\Models\LeaveType;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

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
   * Lower numbers indicate higher priority.
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
    $mappedLeaveTypes = $this->mapOdooLeaveTypes();

    // Step 2: Create or update local leave types based on Odoo data
    $this->syncLeaveTypes($mappedLeaveTypes);

    // Step 3: Log leave types that exist locally but not in Odoo
    $this->logMissingLeaveTypes($mappedLeaveTypes->pluck('odoo_leave_type_id'));
  }

  /**
   * Maps Odoo leave types to our local structure.
   *
   * @return Collection
   */
  private function mapOdooLeaveTypes(): Collection
  {
    return $this->odoo->getLeaveTypes()->map(function ($lt) {
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
   * @param Collection $mappedLeaveTypes
   * @return void
   */
  private function syncLeaveTypes(Collection $mappedLeaveTypes): void
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
   * Logs leave types that exist locally but not in Odoo for historical integrity.
   *
   * @param Collection $currentOdooLeaveTypeIds
   * @return void
   */
  private function logMissingLeaveTypes(
    Collection $currentOdooLeaveTypeIds
  ): void {
    LeaveType::pluck('odoo_leave_type_id')
      ->diff($currentOdooLeaveTypeIds)
      ->pipe(function ($leaveTypesToLog) {
        if ($leaveTypesToLog->isEmpty()) {
          return;
        }

        LeaveType::whereIn('odoo_leave_type_id', $leaveTypesToLog)
          ->get()
          ->each(function ($leaveType) {
            Log::info(
              class_basename($this) .
                ': Leave type no longer exists in Odoo but preserved for historical integrity',
              [
                'odoo_leave_type_id' => $leaveType->odoo_leave_type_id,
                'name' => $leaveType->name,
                'detected_at' => now()->toDateTimeString(),
              ]
            );
          });
      });
  }
}
