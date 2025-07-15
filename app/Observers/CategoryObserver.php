<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Category;
use Illuminate\Support\Facades\Log;

class CategoryObserver
{
    /**
     * Handle the Category "deleting" event.
     */
    public function deleting(Category $category): void
    {
        // Detach each user individually to emit model events
        foreach ($category->users as $user) {
            $category->users()->detach($category->odoo_category_id);
        }
    }

    public function created($category)
    {
        Log::info('Category created', [
            'odoo_category_id' => $category->odoo_category_id,
            'attributes' => $category->getAttributes(),
        ]);
    }

    public function updated($category)
    {
        $changes = $category->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $category->getOriginal($field);
            }
            Log::info('Category updated', [
                'odoo_category_id' => $category->odoo_category_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted($category)
    {
        Log::info('Category deleted', [
            'odoo_category_id' => $category->odoo_category_id,
            'attributes' => $category->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
