<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\SyncOdooCategoryAction;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Category;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo employee category data (hr.employee.category) with the local categories table.
 *
 * This job orchestrates the synchronization of categories by fetching them from Odoo
 * and then using `SyncOdooCategoryAction` to process each category individually.
 *
 * Ensures the local categories database reflects the current state of Odoo, including:
 * - Creating new categories and updating existing ones
 * - Logging categories that no longer exist in Odoo for historical integrity
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
     * It also logs categories that exist locally but not in Odoo for historical integrity.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Create or update local categories (ensures local DB matches Odoo)
        $this->categories->each(function (OdooCategoryDTO $categoryDto): void {
            (new SyncOdooCategoryAction)->execute($categoryDto);
        });

        // Log categories that exist locally but not in Odoo
        $this->logMissingCategories($this->categories->pluck('id'));
    }

    /**
     * Logs categories that exist locally but not in Odoo for historical integrity.
     *
     * Finds categories in the local database that are not present in the current
     * Odoo category list and logs them for historical tracking.
     *
     * @param  Collection  $currentOdooCategoryIds  Collection of current Odoo category IDs.
     */
    private function logMissingCategories(Collection $currentOdooCategoryIds): void
    {
        $missingCategories = Category::whereNotIn('odoo_category_id', $currentOdooCategoryIds)
            ->get();
        // If there are no missing categories, nothing to log
        if ($missingCategories->isEmpty()) {
            return;
        }
        // Log each missing category for historical integrity
        $missingCategories->each(function ($category): void {
            Log::info(
                class_basename(self::class).
                    ": Category '{$category->name}' no longer exists in Odoo but preserved for historical integrity",
                [
                    'odoo_category_id' => $category->odoo_category_id,
                    'name' => $category->name,
                    'created_at' => $category->created_at->toDateTimeString(),
                    'updated_at' => $category->updated_at->toDateTimeString(),
                    'detected_at' => now()->toDateTimeString(),
                ]
            );
        });
    }
}
