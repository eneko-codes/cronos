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
     * 1. Fetches categories from Odoo API and maps them to local structure
     * 2. Creates or updates local categories based on Odoo data
     * 3. Logs categories that exist locally but not in Odoo for historical integrity
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        Log::info(class_basename(static::class).' Started', ['job' => class_basename(static::class)]);
        // Step 1: Fetch and map categories from Odoo
        $mappedCategories = $this->mapOdooCategories();

        // Step 2: Create or update local categories
        $this->syncCategories($mappedCategories);

        // Step 3: Log categories that exist locally but not in Odoo
        $this->logMissingCategories($mappedCategories->pluck('odoo_category_id'));
        Log::info(class_basename(static::class).' Finished', ['job' => class_basename(static::class)]);
    }

    /**
     * Maps Odoo categories to the local structure.
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
     * @param  Collection|OdooCategoryDTO[]  $categories  Collection of OdooCategoryDTOs from Odoo.
     */
    private function syncCategories(Collection $categories): void
    {
        $categories->each(function (OdooCategoryDTO $cat): void {
            if ($cat->name === null || $cat->active === null) {
                Log::warning(class_basename(static::class).' Skipping category with missing required fields', [
                    'job' => class_basename(static::class),
                    'entity' => 'category',
                    'category' => $cat,
                ]);

                return;
            }
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
     * @param  Collection  $currentOdooCategoryIds  Collection of current Odoo category IDs.
     */
    private function logMissingCategories(Collection $currentOdooCategoryIds): void
    {
        $missingCategories = Category::whereNotIn('odoo_category_id', $currentOdooCategoryIds)
            ->get();

        if ($missingCategories->isEmpty()) {
            return;
        }

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
