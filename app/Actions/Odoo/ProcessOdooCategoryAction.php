<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooCategoryDTO;
use App\Models\Category;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize a single Odoo category DTO with the local database.
 *
 * This class encapsulates the business logic for creating or updating a category record.
 * It performs validation on the incoming DTO and ensures the database operation is atomic.
 */
final class ProcessOdooCategoryAction
{
    /**
     * Executes the synchronization logic for a single Odoo Category DTO.
     *
     * Performs validation on the provided DTO. If validation fails, a warning is logged,
     * and the synchronization for that category is skipped. Otherwise, the category
     * record is created or updated within a database transaction to ensure data integrity.
     *
     * @param  OdooCategoryDTO  $categoryDto  The OdooCategoryDTO to sync.
     */
    public function execute(OdooCategoryDTO $categoryDto): void
    {
        $validator = Validator::make(
            [
                'id' => $categoryDto->id,
                'name' => $categoryDto->name,
            ],
            [
                'id' => 'required',
                'name' => 'required',
            ]
        );

        if ($validator->fails()) {
            Log::warning(class_basename(self::class).' Skipping category due to validation errors', [
                'category' => $categoryDto,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($categoryDto): void {
            // Create or update the category record
            $category = Category::where('odoo_category_id', $categoryDto->id)->first();

            if ($category) {
                $category->update([
                    'name' => $categoryDto->name,
                    'active' => $categoryDto->active ?? true,
                ]);
            } else {
                Category::create([
                    'odoo_category_id' => $categoryDto->id,
                    'name' => $categoryDto->name,
                    'active' => $categoryDto->active ?? true,
                ]);
            }
        });
    }
}
