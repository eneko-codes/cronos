<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Clients\OdooApiClient;
use App\Models\Department;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo department data (hr.department) with the local departments table.
 *
 * Ensures the local departments database reflects the current state of Odoo, including:
 * - Creating new departments and updating existing ones
 * - Preserving and logging departments that no longer exist in Odoo
 * - Updating users' department assignments as needed
 */
class SyncOdooDepartments extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncOdooDepartments job instance.
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
     * 1. Fetches all departments from Odoo API
     * 2. Creates or updates local departments based on Odoo data
     * 3. Logs departments that exist locally but not in Odoo for historical integrity
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Step 1: Fetch and map departments from Odoo
        $mappedDepartments = $this->mapOdooDepartments();

        // Step 2: Create or update local departments based on Odoo data
        $this->syncDepartments($mappedDepartments);

        // Step 3: Log departments that exist locally but not in Odoo
        $this->logMissingDepartments(
            $mappedDepartments->pluck('odoo_department_id')
        );
    }

    /**
     * Maps Odoo departments to the local structure.
     *
     * @return Collection Mapped department data.
     */
    private function mapOdooDepartments(): Collection
    {
        return $this->odoo->getDepartments()->map(function ($dept) {
            return [
                'odoo_department_id' => $dept['id'],
                'name' => $dept['name'],
                'active' => $dept['active'] ?? true,
                'odoo_manager_employee_id' => Arr::get($dept, 'manager_id.0'),
                'odoo_parent_department_id' => Arr::get($dept, 'parent_id.0'),
            ];
        });
    }

    /**
     * Creates or updates local departments based on Odoo data.
     *
     * @param  Collection  $mappedDepartments  Collection of mapped department data from Odoo.
     */
    private function syncDepartments(Collection $mappedDepartments): void
    {
        $mappedDepartments->each(function ($dept): void {
            Department::updateOrCreate(
                ['odoo_department_id' => $dept['odoo_department_id']],
                [
                    'name' => $dept['name'],
                    'active' => $dept['active'],
                    'odoo_manager_employee_id' => $dept['odoo_manager_employee_id'],
                    'odoo_parent_department_id' => $dept['odoo_parent_department_id'],
                ]
            );
        });
    }

    /**
     * Logs departments that exist locally but not in Odoo for historical integrity.
     *
     * @param  Collection  $currentOdooDepartmentIds  Collection of current Odoo department IDs.
     */
    private function logMissingDepartments(
        Collection $currentOdooDepartmentIds
    ): void {
        $missingDepartments = Department::whereNotIn('odoo_department_id', $currentOdooDepartmentIds)
            ->get();

        if ($missingDepartments->isEmpty()) {
            return;
        }

        $missingDepartments->each(function ($department): void {
            Log::info(
                class_basename($this).
                    ': Department no longer exists in Odoo but preserved for historical integrity',
                [
                    'odoo_department_id' => $department->odoo_department_id,
                    'name' => $department->name,
                    'detected_at' => now()->toDateTimeString(),
                ]
            );
        });
    }
}
