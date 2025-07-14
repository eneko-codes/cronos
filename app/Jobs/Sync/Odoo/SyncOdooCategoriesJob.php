<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooCategoryAction;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;
use Illuminate\Support\Collection;

/**
 * Job to synchronize Odoo employee category data (hr.employee.category) with the local categories table.
 *
 * This job orchestrates the synchronization of categories by fetching them from Odoo
 * and then using `SyncOdooCategoryAction` to process each category individually.
 *
 * Ensures the local categories database reflects the current state of Odoo, including:
 * - Creating new categories and updating existing ones
 */
class SyncOdooCategoriesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncOdooCategories job instance.
     *
     * @param  Collection<int, OdooCategoryDTO>  $categories  The collection of OdooCategoryDTOs to sync.
     */
    public function __construct(private Collection $categories) {}

    /**
     * Main entry point for the job's sync logic.
     *
     * Iterates through the provided collection of OdooCategoryDTOs and dispatches
     * `SyncOdooCategoryAction` for each, handling the creation or update of local categories.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Create or update local categories (ensures local DB matches Odoo)
        $this->categories->each(function (OdooCategoryDTO $categoryDto): void {
            (new SyncOdooCategoryAction)->execute($categoryDto);
        });

    }
}
