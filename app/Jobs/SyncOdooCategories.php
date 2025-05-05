<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Category;
use App\Services\OdooApiService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncOdooCategories
 *
 * Synchronizes hr.employee.category data from Odoo into local categories table.
 * This job ensures the local categories database reflects the current state of the Odoo system,
 * including creating new categories, updating existing ones, and preserving categories
 * that no longer exist in Odoo for historical integrity.
 */
class SyncOdooCategories extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     * Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * SyncOdooCategories constructor.
     *
     * @param  OdooApiService  $odoo  An instance of the OdooApiService service.
     */
    public function __construct(OdooApiService $odoo)
    {
        // Assign to parent's $odoo
        $this->odoo = $odoo;
    }

    /**
     * Executes the synchronization process.
     *
     * This method performs the following operations:
     * 1. Fetches categories from Odoo API and maps them to local structure
     * 2. Creates or updates local categories based on Odoo data
     * 3. Identifies categories that exist locally but not in Odoo
     * 4. Logs missing categories for historical integrity
     *
     * @throws Exception If any part of the synchronization process fails
     */
    protected function execute(): void
    {
        // Step 1: Fetch and map categories from Odoo
        $mappedCategories = $this->mapOdooCategories();

        // Step 2: Create or update local categories
        $this->syncCategories($mappedCategories);

        // Step 3: Log categories that exist locally but not in Odoo
        $this->logMissingCategories($mappedCategories->pluck('odoo_category_id'));
    }

    /**
     * Maps Odoo categories to our local structure.
     */
    private function mapOdooCategories(): Collection
    {
        return $this->odoo->getCategories()->map(function ($cat) {
            return [
                'odoo_category_id' => $cat['id'],
                'name' => $cat['name'],
                'active' => $cat['active'] ?? true,
            ];
        });
    }

    /**
     * Creates or updates local categories based on Odoo data.
     */
    private function syncCategories(Collection $mappedCategories): void
    {
        $mappedCategories->each(function ($cat): void {
            Category::updateOrCreate(
                ['odoo_category_id' => $cat['odoo_category_id']],
                [
                    'name' => $cat['name'],
                    'active' => $cat['active'],
                ]
            );
        });
    }

    /**
     * Logs categories that exist locally but not in Odoo for historical integrity.
     */
    private function logMissingCategories(
        Collection $currentOdooCategoryIds
    ): void {
        $missingCategories = Category::whereNotIn('odoo_category_id', $currentOdooCategoryIds)
            ->get();

        if ($missingCategories->isEmpty()) {
            return;
        }

        $missingCategories->each(function ($category): void {
            Log::info(
                class_basename($this).
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
