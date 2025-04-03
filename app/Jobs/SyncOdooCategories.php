<?php

namespace App\Jobs;

use App\Models\Category;
use App\Services\OdooApiCalls;
use Exception;
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
   *
   * @var int
   */
  public int $priority = 1;

  /**
   * SyncOdooCategories constructor.
   *
   * @param OdooApiCalls $odoo An instance of the OdooApiCalls service.
   */
  public function __construct(OdooApiCalls $odoo)
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
    $mappedCategories = $this->odoo->getCategories()->map(function ($cat) {
      return [
        'odoo_category_id' => $cat['id'],
        'name' => $cat['name'],
        'active' => $cat['active'] ?? true,
      ];
    });

    // Step 2: Create or update local categories based on Odoo data individually to trigger model events
    $mappedCategories->each(function ($cat) {
      Category::updateOrCreate(
        ['odoo_category_id' => $cat['odoo_category_id']],
        [
          'name' => $cat['name'],
          'active' => $cat['active'],
        ]
      );
    });

    // Step 3: Identifies categories that exist locally but not in Odoo
    $odooCatIds = $mappedCategories->pluck('odoo_category_id');
    $localCatIds = Category::pluck('odoo_category_id');
    $categoriesToLog = $localCatIds->diff($odooCatIds);

    // Step 4: Log categories that exist locally but not in Odoo for historical integrity
    if ($categoriesToLog->isNotEmpty()) {
      Category::whereIn('odoo_category_id', $categoriesToLog)
        ->get()
        ->each(function ($category) {
          Log::channel('sync')->info(
            class_basename($this) .
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
}
