<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Department;
use App\Services\OdooApiService;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncOdooDepartments
 *
 * Synchronizes hr.department data from Odoo into local departments table.
 * This job ensures the local departments database reflects the current state of Odoo,
 * including creating new departments, updating existing ones, preserving and logging departments
 * that no longer exist in Odoo, and updating users' department assignments.
 */
class SyncOdooDepartments extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     * Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * SyncOdooDepartments constructor.
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
     * 1. Fetches all departments from Odoo API
     * 2. Creates or updates local departments based on Odoo data
     * 3. Logs departments that exist locally but not in Odoo for historical integrity
     *
     * @throws Exception If any part of the synchronization process fails
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
     * Maps Odoo departments to our local structure.
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
     */
    private function syncDepartments(Collection $mappedDepartments): void
    {
        $mappedDepartments->each(function ($dept) {
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
     */
    private function logMissingDepartments(
        Collection $currentOdooDepartmentIds
    ): void {
        $missingDepartments = Department::whereNotIn('odoo_department_id', $currentOdooDepartmentIds)
            ->get();

        if ($missingDepartments->isEmpty()) {
            return;
        }

        $missingDepartments->each(function ($department) {
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
