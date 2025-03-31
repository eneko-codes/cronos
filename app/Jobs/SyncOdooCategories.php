<?php

namespace App\Jobs;

use App\Models\Category;
use App\Services\OdooApiCalls;
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
   * This method orchestrates the entire category synchronization workflow
   * by calling more specific methods for each step in the process.
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Fetch and map categories from Odoo
    $mappedCategories = $this->fetchAndMapCategories();

    // Synchronize local categories with Odoo data
    $this->syncLocalCategories($mappedCategories);

    // Log categories that exist locally but not in Odoo
    $this->logMissingCategories($mappedCategories);
  }

  /**
   * Fetches categories from Odoo and maps them to our local structure.
   *
   * @return Collection Collection of mapped category data
   * @throws Exception If API call fails
   */
  private function fetchAndMapCategories(): Collection
  {
    $odooCategories = $this->odoo->getCategories();

    return $odooCategories->map(function ($cat) {
      return [
        'odoo_category_id' => $cat['id'],
        'name' => $cat['name'],
        'active' => $cat['active'] ?? true,
      ];
    });
  }

  /**
   * Creates or updates local categories based on Odoo data.
   *
   * @param Collection $mappedCategories Collection of mapped category data from Odoo
   */
  private function syncLocalCategories(Collection $mappedCategories): void
  {
    $mappedCategories->each(function ($cat) {
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
   * Identifies and logs categories that exist locally but not in Odoo.
   * Categories are preserved for historical integrity rather than deleted.
   *
   * @param Collection $mappedCategories Collection of mapped category data from Odoo
   */
  private function logMissingCategories(Collection $mappedCategories): void
  {
    $odooCatIds = $mappedCategories->pluck('odoo_category_id');
    $localCatIds = Category::pluck('odoo_category_id');

    $categoriesToLog = $localCatIds->diff($odooCatIds);

    if ($categoriesToLog->isNotEmpty()) {
      Category::whereIn('odoo_category_id', $categoriesToLog)
        ->get()
        ->each(function ($category) {
          Log::channel('sync')->info(
            'Category no longer exists in Odoo but preserved for historical integrity',
            [
              'odoo_category_id' => $category->odoo_category_id,
              'name' => $category->name,
              'detected_at' => now()->toDateTimeString(),
            ]
          );
        });
    }
  }
}
