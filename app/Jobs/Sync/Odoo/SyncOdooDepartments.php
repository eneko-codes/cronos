<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Clients\OdooApiClient;
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
        Log::info(class_basename(static::class).' Started', ['job' => class_basename(static::class)]);
        // Fetch and map departments from Odoo
        $mappedDepartments = $this->mapOdooDepartments();

        // Create or update local departments based on Odoo data
        $this->syncDepartments($mappedDepartments);

        // Log departments that exist locally but not in Odoo
        $this->logMissingDepartments(
            $mappedDepartments->pluck('odoo_department_id')
        );
        Log::info(class_basename(static::class).' Finished', ['job' => class_basename(static::class)]);
    }

    /**
     * Maps Odoo departments to the local structure.
     *
     * Calls the Odoo API client to fetch all departments and returns them as a collection
     * of OdooDepartmentDTO objects.
     *
     * @return Collection|OdooDepartmentDTO[]
     */
    private function mapOdooDepartments(): Collection
    {
        return $this->odoo->getDepartments();
    }

    /**
     * Creates or updates local departments based on Odoo data.
     *
     * For each Odoo department, this method will:
     * - Create a new department or update an existing one in the local database.
     * - Skip and log any departments missing required fields.
     *
     * @param  Collection|OdooDepartmentDTO[]  $departments  Collection of OdooDepartmentDTOs from Odoo.
     */
    private function syncDepartments(Collection $departments): void
    {
        $departments->each(function (OdooDepartmentDTO $dept): void {
            // Skip if required fields are missing
            if ($dept->name === null || $dept->active === null) {
                Log::warning(class_basename(static::class).' Skipping department with missing required fields', [
                    'job' => class_basename(static::class),
                    'entity' => 'department',
                    'department' => $dept,
                ]);

                return;
            }
            // Create or update the department record
            Department::updateOrCreate(
                ['odoo_department_id' => $dept->id],
                [
                    'name' => $dept->name,
                    'active' => $dept->active,
                    'odoo_manager_employee_id' => $dept->manager_id,
                    'odoo_parent_department_id' => $dept->parent_id,
                ]
            );
        });
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
                class_basename(static::class).': Department no longer exists in Odoo but preserved for historical integrity',
                [
                    'odoo_department_id' => $department->odoo_department_id,
                    'name' => $department->name,
                    'detected_at' => now()->toDateTimeString(),
                ]
            );
        });
    }
}
