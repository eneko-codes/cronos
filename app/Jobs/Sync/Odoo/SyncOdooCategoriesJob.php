<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooCategoryAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Category;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo employee category data (hr.employee.category) with the local categories table.
 *
 * This job fetches all categories from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all categories from Odoo
 * - Create or update local categories
 * - Deactivate categories no longer present in Odoo
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
     * Fetches categories from Odoo and processes each one, then deactivates
     * categories no longer present in Odoo.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $categories = $this->odoo->getCategories();

        // Extract Odoo IDs before processing
        $apiIds = $categories->pluck('id')->filter();

        // Process each category DTO
        $categories->each(function (OdooCategoryDTO $categoryDto): void {
            (new ProcessOdooCategoryAction)->execute($categoryDto);
        });

        // Cleanup: deactivate categories no longer in Odoo
        $this->cleanupMissingCategories($apiIds);
    }

    /**
     * Deactivate categories that are no longer present in the Odoo API response.
     *
     * @param  Collection  $apiIds  Collection of Odoo category IDs from the API response.
     */
    private function cleanupMissingCategories(Collection $apiIds): void
    {
        $deactivatedCount = Category::where('active', true)
            ->whereNotIn('odoo_category_id', $apiIds)
            ->update(['active' => false]);

        if ($deactivatedCount > 0) {
            Log::debug('SyncOdooCategoriesJob: Deactivated categories no longer in Odoo', [
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
