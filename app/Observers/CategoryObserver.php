<?php

namespace App\Observers;

use App\Models\Category;

class CategoryObserver
{
    /**
     * Handle the Category "deleting" event.
     *
     * @param  \App\Models\Category  $category
     * @return void
     */
    public function deleting(Category $category): void
    {
        // Detach each user individually to emit model events
        foreach ($category->users as $user) {
            $category->users()->detach($user->id);
        }
    }

    // Add other event methods if needed: created, updated, deleted, restored, forceDeleted
} 