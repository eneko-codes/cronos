<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\LeaveType;
use App\Services\OdooApiService;
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
     * Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * SyncOdooLeaveTypes constructor.
     *
     * @param  OdooApiService  $odoo  An instance of the OdooApiService service.
     */
    public function __construct(OdooApiService $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Executes the synchronization process.
     *
     * This method performs the following operations:
     * 1. Fetches leave types from Odoo API and maps them to local structure
     * 2. Creates or updates local leave types based on Odoo data
     * 3. Logs leave types that exist locally but not in Odoo for historical integrity
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
     */
    private function mapOdooLeaveTypes(): Collection
    {
        return $this->odoo->getLeaveTypes()->map(function ($lt) {
            // Map allocation_type to requires_allocation for compatibility if needed
            $requiresAllocation = match ($lt['allocation_type'] ?? 'no') {
                'fixed_allocation', 'fixed' => true,
                default => false,
            };

            return [
                'odoo_leave_type_id' => $lt['id'],
                'name' => $lt['name'],
                'validation_type' => $lt['validation_type'] ?? null,
                'request_unit' => $lt['request_unit'] ?? null,
                'limit' => false,
                'requires_allocation' => $requiresAllocation,
                'active' => $lt['active'] ?? true,
                'is_unpaid' => $lt['unpaid'] ?? false,
            ];
        });
    }

    /**
     * Creates or updates local leave types based on Odoo data.
     */
    private function syncLeaveTypes(Collection $mappedLeaveTypes): void
    {
        $mappedLeaveTypes->each(function ($leaveType) {
            LeaveType::updateOrCreate(
                ['odoo_leave_type_id' => $leaveType['odoo_leave_type_id']],
                [
                    'name' => $leaveType['name'],
                    'validation_type' => $leaveType['validation_type'],
                    'request_unit' => $leaveType['request_unit'],
                    'limit' => $leaveType['limit'],
                    'requires_allocation' => $leaveType['requires_allocation'],
                    'active' => $leaveType['active'],
                    'is_unpaid' => $leaveType['is_unpaid'],
                ]
            );
        });
    }

    /**
     * Logs leave types that exist locally but not in Odoo for historical integrity.
     */
    private function logMissingLeaveTypes(
        Collection $currentOdooLeaveTypeIds
    ): void {
        $missingLeaveTypes = LeaveType::whereNotIn('odoo_leave_type_id', $currentOdooLeaveTypeIds)
            ->get();

        if ($missingLeaveTypes->isEmpty()) {
            return;
        }

        $missingLeaveTypes->each(function ($leaveType) {
            Log::info(
                class_basename($this).
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
