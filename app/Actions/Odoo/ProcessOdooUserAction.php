<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Models\Category;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Action to synchronize Odoo user data (hr.employee) with the local users table.
 *
 * This action encapsulates all business logic for creating or updating a user record
 * from a single OdooUserDTO, including validation, logging, category and schedule sync.
 *
 * The static syncAll method can be used to orchestrate a full batch sync, handling:
 * - Logging users with missing emails
 * - Filtering for valid users
 * - Syncing each user
 * - Deactivating users not present in the current batch
 */
final class ProcessOdooUserAction
{
    /**
     * The Odoo user data transfer object for the current sync operation.
     */
    private OdooUserDTO $dto;

    /**
     * Synchronizes a single Odoo user DTO with the local database.
     *
     * - If the DTO has active=false, the user will be created/updated as inactive (is_active=false).
     * - Users are NEVER deleted by this action.
     *
     * @param  OdooUserDTO  $userDto  The OdooUserDTO to sync.
     */
    public function execute(OdooUserDTO $userDto): void
    {
        $this->dto = $userDto;

        $validator = Validator::make(
            [
                'id' => $userDto->id,
                'work_email' => $userDto->work_email,
                'name' => $userDto->name,
            ],
            [
                'id' => 'required',
                'work_email' => 'required|email',
                'name' => 'required',
            ],
            [
                'work_email.required' => 'Odoo user (ID: '.$userDto->id.', Name: '.$userDto->name.') is missing a work email.',
                'work_email.email' => 'Odoo user (ID: '.$userDto->id.', Name: '.$userDto->name.') has an invalid email address.',
            ]
        );

        if ($validator->fails()) {
            Log::warning(class_basename(self::class).' Skipping user due to validation errors', [
                'user' => $userDto,
                'errors' => $validator->errors()->all(),
            ]);

            return;
        }

        DB::transaction(function () use ($userDto): void {
            User::updateOrCreate(
                ['odoo_id' => $userDto->id],
                [
                    'name' => $userDto->name,
                    'email' => Str::lower($userDto->work_email),
                    'timezone' => $userDto->tz ?? 'UTC',
                    'is_active' => $userDto->active ?? true,
                    'department_id' => $userDto->department_id !== null ? ($userDto->department_id[0] ?? null) : null,
                    'job_title' => $userDto->job_title ?? null,
                    'odoo_manager_id' => $userDto->parent_id !== null ? ($userDto->parent_id[0] ?? null) : null,
                ]
            );
            $user = User::where('odoo_id', $userDto->id)->first();
            $this->syncUserCategories($user);
            $this->syncUserSchedule($user);
        });
    }

    /**
     * Sync the user's categories (many-to-many) with the pivot table.
     *
     * This method updates the pivot table (category_user) to match the categories
     * provided by the OdooUserDTO. It will add and remove links as needed.
     *
     * @param  User  $user  The local user model.
     */
    private function syncUserCategories(User $user): void
    {
        $categoryIds = collect($this->dto->category_ids)
            ->filter()
            ->unique();
        $validLocalCategoryIds = Category::whereIn('odoo_category_id', $categoryIds)->pluck('odoo_category_id');
        $user->categories()->sync($validLocalCategoryIds);
    }

    /**
     * Synchronizes the user's schedule assignments with Odoo data.
     *
     * - Closes the previous active schedule (sets effective_until) if the schedule changes.
     * - Creates a new UserSchedule with effective_from if needed.
     *
     * @param  User  $user  The local user model.
     */
    private function syncUserSchedule(User $user): void
    {
        $startOfDay = now()->startOfDay();
        $newOdooScheduleId = $this->dto->resource_calendar_id !== null ? ($this->dto->resource_calendar_id[0] ?? null) : null;
        if (! $newOdooScheduleId || ! Schedule::where('odoo_schedule_id', $newOdooScheduleId)->exists()) {
            return;
        }

        DB::transaction(function () use ($user, $newOdooScheduleId, $startOfDay): void {
            /** @var \App\Models\UserSchedule|null $activeUserSchedule */
            $activeUserSchedule = $user->activeUserSchedule()->first();
            if ($activeUserSchedule && $activeUserSchedule->odoo_schedule_id === $newOdooScheduleId) {
                return;
            }
            if ($activeUserSchedule) {
                $activeUserSchedule->update(['effective_until' => $startOfDay]);
            }
            $user->userSchedules()->create([
                'odoo_schedule_id' => $newOdooScheduleId,
                'effective_from' => $startOfDay,
                'effective_until' => null,
            ]);
        });
    }
}
