<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooDepartmentAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;

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
     * Fetches departments from Odoo and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $departments = $this->odoo->getDepartments();
        $departments->each(function (OdooDepartmentDTO $departmentDto): void {
            (new ProcessOdooDepartmentAction)->execute($departmentDto);
        });
    }

    public function failed(): void
    {
        app(CheckOdooHealthAction::class)($this->odoo);
    }
}
