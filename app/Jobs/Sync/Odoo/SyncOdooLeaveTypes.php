<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveTypeDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\LeaveType;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo leave type data (hr.leave.type) with the local leave_types table.
 *
 * Ensures local leave types match the current state in Odoo, including:
 * - Creating new leave types and updating existing ones
 * - Preserving and logging leave types that no longer exist in Odoo for historical integrity
 */
class SyncOdooLeaveTypes extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Constructs a new SyncOdooLeaveTypes job instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client instance.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Fetches leave types from Odoo API and maps them to local structure
     * 2. Creates or updates local leave types based on Odoo data
     * 3. Logs leave types that exist locally but not in Odoo for historical integrity
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        Log::info(class_basename(static::class).' Started', ['job' => class_basename(static::class)]);
        // Step 1: Fetch and map leave types from Odoo
        $mappedLeaveTypes = $this->mapOdooLeaveTypes();

        // Step 2: Create or update local leave types based on Odoo data
        $this->syncLeaveTypes($mappedLeaveTypes);

        // Step 3: Log leave types that exist locally but not in Odoo
        $this->logMissingLeaveTypes($mappedLeaveTypes->pluck('odoo_leave_type_id'));
        Log::info(class_basename(static::class).' Finished', ['job' => class_basename(static::class)]);
    }

    /**
     * Maps Odoo leave types to the local structure.
     *
     * @return Collection Mapped leave type data.
     */
    private function mapOdooLeaveTypes(): Collection
    {
        return $this->odoo->getLeaveTypes()->map(function (OdooLeaveTypeDTO $lt) {
            // Map allocation_type to requires_allocation for compatibility if needed
            $requiresAllocation = match ($lt->allocation_type ?? 'no') {
                'fixed_allocation', 'fixed' => true,
                default => false,
            };

            return [
                'odoo_leave_type_id' => $lt->id,
                'name' => $lt->name,
                'validation_type' => $lt->validation_type ?? null,
                'request_unit' => $lt->request_unit ?? null,
                'limit' => false,
                'requires_allocation' => $requiresAllocation,
                'active' => $lt->active ?? true,
                'is_unpaid' => $lt->unpaid ?? false,
            ];
        });
    }

    /**
     * Creates or updates local leave types based on Odoo data.
     *
     * @param  Collection  $mappedLeaveTypes  Collection of mapped leave type data from Odoo.
     */
    private function syncLeaveTypes(Collection $mappedLeaveTypes): void
    {
        $mappedLeaveTypes->each(function ($leaveType): void {
            if ($leaveType['name'] === null || $leaveType['active'] === null) {
                Log::warning(class_basename(static::class).' Skipping leave type with missing required fields', [
                    'job' => class_basename(static::class),
                    'entity' => 'leave_type',
                    'leave_type' => $leaveType,
                ]);

                return;
            }
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
     *
     * @param  Collection  $currentOdooLeaveTypeIds  Collection of current Odoo leave type IDs.
     */
    private function logMissingLeaveTypes(
        Collection $currentOdooLeaveTypeIds
    ): void {
        $missingLeaveTypes = LeaveType::whereNotIn('odoo_leave_type_id', $currentOdooLeaveTypeIds)
            ->get();

        if ($missingLeaveTypes->isEmpty()) {
            return;
        }

        $missingLeaveTypes->each(function ($leaveType): void {
            Log::info(
                class_basename(static::class).': Leave type no longer exists in Odoo but preserved for historical integrity',
                [
                    'odoo_leave_type_id' => $leaveType->odoo_leave_type_id,
                    'name' => $leaveType->name,
                    'detected_at' => now()->toDateTimeString(),
                ]
            );
        });
    }
}
