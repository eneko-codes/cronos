<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooDepartmentAction;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Department;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo department data (hr.department) with the local departments table.
 *
 * Ensures the local departments database reflects the current state of Odoo, including:
 * - Creating new departments and updating existing ones
 * - Updating users\' department assignments as needed
 * - Logging departments that no longer exist in Odoo for historical integrity
 */
class SyncOdooDepartmentsJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncOdooDepartmentsJob instance.
     *
     * @param  Collection|OdooDepartmentDTO[]  $departments  The collection of Odoo Department DTOs.
     */
    public function __construct(private Collection $departments) {}

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Iterates through the provided OdooDepartmentDTOs.
     * 2. Uses SyncOdooDepartmentAction to create or update local departments based on Odoo data.
     * 3. Logs departments that exist locally but not in Odoo for historical integrity.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Create or update local departments based on Odoo data
        $this->departments->each(function (OdooDepartmentDTO $departmentDto): void {
            (new SyncOdooDepartmentAction)->execute($departmentDto);
        });

        // Log departments that exist locally but not in Odoo
        $this->logMissingDepartments($this->departments->pluck('id'));
    }

    /**
     * Logs departments that exist locally but not in Odoo for historical integrity.
     *
     * Finds departments in the local database that are not present in the current
     * Odoo department list and logs them for historical tracking.
     *
     * @param  Collection  $currentOdooDepartmentIds  Collection of current Odoo department IDs.
     */
    private function logMissingDepartments(
        Collection $currentOdooDepartmentIds
    ): void {
        $missingDepartments = Department::whereNotIn('odoo_department_id', $currentOdooDepartmentIds)
            ->get();
        // If there are no missing departments, nothing to log
        if ($missingDepartments->isEmpty()) {
            return;
        }
        // Log each missing department for historical integrity
        $missingDepartments->each(function ($department): void {
            Log::info(
                class_basename(self::class).': Department no longer exists in Odoo but preserved for historical integrity',
                [
                    'odoo_department_id' => $department->odoo_department_id,
                    'name' => $department->name,
                    'detected_at' => now()->toDateTimeString(),
                ]
            );
        });
    }
}
