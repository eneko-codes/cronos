<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooDepartmentAction;
use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;
use Illuminate\Support\Collection;

/**
 * Job to synchronize Odoo department data (hr.department) with the local departments table.
 *
 * Ensures the local departments database reflects the current state of Odoo, including:
 * - Creating new departments and updating existing ones
 * - Updating users' department assignments as needed
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
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Create or update local departments based on Odoo data
        $this->departments->each(function (OdooDepartmentDTO $departmentDto): void {
            (new SyncOdooDepartmentAction)->execute($departmentDto);
        });

    }
}
