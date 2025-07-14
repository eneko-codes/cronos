<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Models\Category;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
     * Synchronizes a single Odoo user DTO with the local database.
     *
     * Performs validation on the provided DTO. If validation fails, a warning is logged,
     * and the synchronization for that user is skipped. Otherwise, the user
     * record is created or updated within a database transaction to ensure data integrity.
     * Also syncs categories and schedule assignments.
     *
     * @param  OdooUserDTO  $userDto  The OdooUserDTO to sync.
     */
    public function execute(OdooUserDTO $userDto): void
    {
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
            // Create or update the user record using odoo_id as the unique key
            $user = User::updateOrCreate(
                ['odoo_id' => $userDto->id],
                [
                    'name' => $userDto->name,
                    'email' => Str::lower($userDto->work_email),
                    'timezone' => $userDto->tz ?? 'UTC',
                    'is_active' => $userDto->active ?? true,
                    'department_id' => $userDto->department_id !== null ? $userDto->department_id[0] ?? null : null,
                    'job_title' => $userDto->job_title ?? null,
                    'odoo_manager_id' => $userDto->parent_id !== null ? $userDto->parent_id[0] ?? null : null,
                ]
            );
            // Sync user categories (many-to-many)
            $categoryIds = array_map(fn ($c) => is_array($c) ? $c[0] : $c, $userDto->category_ids);
            $validLocalCategoryIds = Category::whereIn('odoo_category_id', $categoryIds)->pluck('odoo_category_id');
            $user->categories()->sync($validLocalCategoryIds);
            // Extract schedule ID from resource_calendar_id ([id, name] or null)
            $scheduleId = $userDto->resource_calendar_id !== null ? $userDto->resource_calendar_id[0] ?? null : null;
            // Sync user schedule (one-to-many, with effective dates)
            $this->syncUserSchedule($user, $scheduleId);
        });
    }

    /**
     * Synchronizes the user's schedule assignment with the local database.
     *
     * If the Odoo schedule ID has changed, closes the old assignment and creates a new one.
     * If the schedule is removed or invalid, closes the current assignment.
     *
     * @param  User  $user  The local user model.
     * @param  int|null  $newOdooScheduleId  The Odoo schedule ID for this user, or null.
     */
    private function syncUserSchedule(User $user, ?int $newOdooScheduleId): void
    {
        $startOfDay = Carbon::now()->startOfDay();
        // Get the currently active schedule assignment (if any)
        /** @var \App\Models\UserSchedule|null $activeUserSchedule */
        $activeUserSchedule = $user->activeUserSchedule()->first();
        $currentOdooScheduleId = $activeUserSchedule?->odoo_schedule_id;
        // If the schedule hasn't changed, do nothing
        if ($currentOdooScheduleId === $newOdooScheduleId) {
            return;
        }
        // If the new schedule is removed or invalid, close the current assignment
        if (! $newOdooScheduleId || ! Schedule::where('odoo_schedule_id', $newOdooScheduleId)->exists()) {
            if ($activeUserSchedule) {
                $activeUserSchedule->update(['effective_until' => $startOfDay]);
            }

            return; // No new schedule to assign
        }
        // If the schedule changed, close the old assignment and create a new one
        if ($activeUserSchedule) {
            $activeUserSchedule->update(['effective_until' => $startOfDay]);
        }
        $user->userSchedules()->create([
            'odoo_schedule_id' => $newOdooScheduleId,
            'effective_from' => $startOfDay,
            'effective_until' => null,
        ]);
    }

    /**
     * Deactivates local user records that no longer exist in the current Odoo fetch.
     *
     * Finds users in the local database (with odoo_id) that are not present in the current
     * Odoo employee list and marks them as inactive.
     *
     * @param  Collection  $currentOdooIds  Collection of current Odoo employee IDs.
     * @return int Number of users deactivated
     */
    public static function deactivateObsoleteUsers(\Illuminate\Support\Collection $currentOdooIds): int
    {
        $deleted = User::whereNotIn('odoo_id', $currentOdooIds)
            ->whereNotNull('odoo_id')
            ->where('is_active', true)
            ->update(['is_active' => false]);

        return $deleted;
    }

    /**
     * Orchestrates the full sync for a collection of OdooUserDTOs.
     *
     * Calls execute() for each DTO, relying on execute()'s validation to log and skip invalid users.
     * Deactivates users not present in the current batch.
     *
     * @param  Collection|OdooUserDTO[]  $users
     */
    public static function syncAll(Collection $users): void
    {
        $users->each(function (OdooUserDTO $employee): void {
            (new ProcessOdooUserAction)->execute($employee);
        });
        ProcessOdooUserAction::deactivateObsoleteUsers($users->pluck('id'));
    }
}
