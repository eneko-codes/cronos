<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Category;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo employee category data (hr.employee.category) with the local categories table.
 *
 * Ensures the local categories database reflects the current state of Odoo, including:
 * - Creating new categories and updating existing ones
 * - Preserving and logging categories that no longer exist in Odoo for historical integrity
 */
class SyncOdooCategories extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncOdooCategories job instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client instance.
     */
    public function __construct(OdooApiClient $odoo)
    {
        // Assign to parent's $odoo
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * - Fetches categories from Odoo API and maps them to local structure
     * - Creates or updates local categories based on Odoo data
     * - Logs categories that exist locally but not in Odoo for historical integrity
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        // Fetch and map categories from Odoo
        $mappedCategories = $this->mapOdooCategories();

        // Create or update local categories (ensures local DB matches Odoo)
        $this->syncCategories($mappedCategories);

        // Log categories that exist locally but not in Odoo (for historical integrity)
        $this->logMissingCategories($mappedCategories->pluck('odoo_category_id'));
    }

    /**
     * Maps Odoo categories to the local structure.
     *
     * Calls the Odoo API client to fetch all categories and returns them as a collection
     * of OdooCategoryDTO objects.
     *
     * @return Collection|OdooCategoryDTO[] Mapped category data.
     */
    private function mapOdooCategories(): Collection
    {
        return $this->odoo->getCategories();
    }

    /**
     * Creates or updates local categories based on Odoo data.
     *
     * For each Odoo category, this method will:
     * - Create a new category or update an existing one in the local database.
     * - Skip and log any categories missing required fields.
     *
     * @param  Collection|OdooCategoryDTO[]  $categories  Collection of OdooCategoryDTOs from Odoo.
     */
    private function syncCategories(Collection $categories): void
    {
        $categories->each(function (OdooCategoryDTO $cat): void {
            // Skip if required fields are missing
            if ($cat->name === null || $cat->active === null) {
                Log::warning(class_basename(static::class).' Skipping category with missing required fields', [
                    'job' => class_basename(static::class),
                    'entity' => 'category',
                    'category' => $cat,
                ]);

                return;
            }
            // Create or update the category record
            Category::updateOrCreate(
                ['odoo_category_id' => $cat->id],
                [
                    'name' => $cat->name,
                    'active' => $cat->active,
                ]
            );
        });
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
                class_basename(static::class).
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
