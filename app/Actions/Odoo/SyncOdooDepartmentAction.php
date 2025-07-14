<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooDepartmentDTO;
use App\Models\Department;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Action to synchronize a single Odoo department DTO with the local database.
 *
 * This class encapsulates the business logic for creating or updating a department record.
 * It performs validation on the incoming DTO and ensures the database operation is atomic.
 */
final class SyncOdooDepartmentAction
{
    /**
     * Executes the synchronization logic for a single Odoo Department DTO.
     *
     * Performs validation on the provided DTO. If validation fails, a warning is logged,
     * and the synchronization for that department is skipped. Otherwise, the department
     * record is created or updated within a database transaction to ensure data integrity.
     *
     * @param  OdooDepartmentDTO  $departmentDto  The OdooDepartmentDTO to sync.
     */
    public function execute(OdooDepartmentDTO $departmentDto): void
    {
        $validator = Validator::make(
            [
                'name' => $departmentDto->name,
                'active' => $departmentDto->active,
            ],
            [
                'name' => 'required',
                'active' => 'required',
            ]
        );

        if ($validator->fails()) {
            Log::warning(class_basename(self::class).' Skipping department due to validation errors', [
                'department' => $departmentDto,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($departmentDto): void {
            // Create or update the department record
            Department::updateOrCreate(
                ['odoo_department_id' => $departmentDto->id],
                [
                    'name' => $departmentDto->name,
                    'active' => $departmentDto->active ?? true,
                    'odoo_manager_id' => is_array($departmentDto->manager_id) ? $departmentDto->manager_id[0] ?? null : null,
                    'odoo_parent_department_id' => is_array($departmentDto->parent_id) ? $departmentDto->parent_id[0] ?? null : null,
                ]
            );
        });
    }
}
