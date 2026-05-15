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
        // Detach all users
        $category->users()->detach();
    }

    public function created(Category $category): void
    {
        Log::debug('Category created', [
            'odoo_category_id' => $category->odoo_category_id,
            'attributes' => $category->getAttributes(),
        ]);
    }

    public function updated(Category $category): void
    {
        $changes = $category->getChanges();
        if (! empty($changes)) {
            $old = [];
            foreach (array_keys($changes) as $field) {
                $old[$field] = $category->getOriginal($field);
            }
            Log::debug('Category updated', [
                'odoo_category_id' => $category->odoo_category_id,
                'changed_fields' => $changes,
                'old_values' => $old,
                'new_values' => $changes,
            ]);
        }
    }

    public function deleted(Category $category): void
    {
        Log::debug('Category deleted', [
            'odoo_category_id' => $category->odoo_category_id,
            'attributes' => $category->getOriginal(),
        ]);
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
}
