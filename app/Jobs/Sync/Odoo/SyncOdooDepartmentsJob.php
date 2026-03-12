<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooDepartmentAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Department;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo department data (hr.department) with the local departments table.
 *
 * This job fetches all departments from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all departments from Odoo
 * - Create or update local departments
 * - Update users' department assignments as needed
 * - Deactivate departments no longer present in Odoo
 */
class SyncOdooDepartmentsJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooDepartmentsJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching departments.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches departments from Odoo and processes each one, then deactivates
     * departments no longer present in Odoo.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $departments = $this->odoo->getDepartments();

        // Extract Odoo IDs before processing
        $apiIds = $departments->pluck('id')->filter();

        // Process each department DTO
        $departments->each(function (OdooDepartmentDTO $departmentDto): void {
            (new ProcessOdooDepartmentAction)->execute($departmentDto);
        });

        // Cleanup: deactivate departments no longer in Odoo
        $this->cleanupMissingDepartments($apiIds);
    }

    /**
     * Deactivate departments that are no longer present in the Odoo API response.
     *
     * @param  Collection  $apiIds  Collection of Odoo department IDs from the API response.
     */
    private function cleanupMissingDepartments(Collection $apiIds): void
    {
        $deactivatedCount = Department::where('active', true)
            ->whereNotIn('odoo_department_id', $apiIds)
            ->update(['active' => false]);

        if ($deactivatedCount > 0) {
            Log::debug('SyncOdooDepartmentsJob: Deactivated departments no longer in Odoo', [
                'deactivated_count' => $deactivatedCount,
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the Odoo API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        $notificationService = app(NotificationService::class);
        $checkHealth = new CheckOdooHealthAction($notificationService);
        $checkHealth($this->odoo);
    }
}
