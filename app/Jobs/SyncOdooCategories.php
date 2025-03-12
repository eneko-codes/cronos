<?php

namespace App\Jobs;

use App\Models\Category;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class SyncOdooCategories
 *
 * Synchronizes hr.employee.category data from Odoo into local categories,
 * and invalidates the entire cache store upon completion.
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
   * Removed protected OdooApiCalls $odoo;
   */
  public function __construct(OdooApiCalls $odoo)
  {
    // Assign to parent's $odoo
    $this->odoo = $odoo;
  }

  /**
   * Executes the synchronization process.
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    $odooCategories = $this->odoo->getCategories();

    $mappedCats = $odooCategories->map(
      fn($cat) => [
        'odoo_category_id' => $cat['id'],
        'name' => $cat['name'],
        'active' => $cat['active'] ?? true,
      ]
    );

    // Upsert categories
    foreach ($mappedCats as $cat) {
      Category::updateOrCreate(
        ['odoo_category_id' => $cat['odoo_category_id']],
        [
          'name' => $cat['name'],
          'active' => $cat['active'],
        ]
      );
    }

    // Identify and delete categories no longer in Odoo
    $odooCatIds = $mappedCats->pluck('odoo_category_id');
    $localCatIds = Category::pluck('odoo_category_id');

    $categoriesToDelete = $localCatIds->diff($odooCatIds);

    if ($categoriesToDelete->isNotEmpty()) {
      Category::whereIn('odoo_category_id', $categoriesToDelete)
        ->get()
        ->each(fn($category) => $category->delete());
    }
  }
}
