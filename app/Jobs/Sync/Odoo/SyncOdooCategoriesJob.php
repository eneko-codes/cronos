<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooCategoryAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Jobs\Sync\BaseSyncJob;
use Exception;

/**
 * Job to synchronize Odoo employee category data (hr.employee.category) with the local categories table.
 *
 * This job fetches all categories from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all categories from Odoo
 * - Create or update local categories
 */
class SyncOdooCategoriesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooCategoriesJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching categories.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches categories from Odoo and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $categories = $this->odoo->getCategories();
        $categories->each(function (OdooCategoryDTO $categoryDto): void {
            (new ProcessOdooCategoryAction)->execute($categoryDto);
        });
    }

    public function failed(): void
    {
        app(CheckOdooHealthAction::class)($this->odoo);
    }
}
