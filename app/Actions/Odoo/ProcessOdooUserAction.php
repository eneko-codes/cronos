<?php

declare(strict_types=1);

namespace App\Actions\Odoo;

use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Enums\Platform;
use App\Models\Category;
use App\Models\Schedule;
use App\Models\User;
use App\Models\UserExternalIdentity;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Action to synchronize Odoo user data (hr.employee) with the local users table.
 *
 * Odoo is the source of truth for user data. This action creates users and their
 * Odoo external identity, handles categories, schedules, and manager relationships.
 */
final class ProcessOdooUserAction
{
    /**
     * Synchronizes a single Odoo user DTO with the local database.
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
            // Find existing user by Odoo external identity or create new one
            $user = $this->findOrCreateUser($userDto);

            // Create or update the Odoo external identity
            $this->syncExternalIdentity($user, $userDto);

            // Sync related data
            $this->syncUserCategories($user, $userDto);
            $this->syncUserSchedule($user, $userDto);

            // Sync manager relationship (may be null if manager not yet synced)
            $this->syncManagerRelationship($user, $userDto);
        });
    }

    /**
     * Find an existing user by Odoo external identity or create a new one.
     */
    private function findOrCreateUser(OdooUserDTO $userDto): User
    {
        $normalizedEmail = Str::lower($userDto->work_email);

        // First, try to find by Odoo external identity
        $existingIdentity = UserExternalIdentity::where('platform', Platform::Odoo)
            ->where('external_id', (string) $userDto->id)
            ->first();

        if ($existingIdentity) {
            // User exists, update their data
            $user = $existingIdentity->user;
            $user->update([
                'name' => $userDto->name,
                'email' => $normalizedEmail,
                'timezone' => $userDto->tz ?? 'UTC',
                'is_active' => $userDto->active ?? true,
                'department_id' => $userDto->department_id !== null ? ($userDto->department_id[0] ?? null) : null,
                'job_title' => $userDto->job_title ?? null,
            ]);

            return $user;
        }

        // Try to find by email (for users that exist but don't have Odoo identity yet)
        $user = User::where('email', $normalizedEmail)->first();

        if ($user) {
            // Update existing user's data
            $user->update([
                'name' => $userDto->name,
                'timezone' => $userDto->tz ?? 'UTC',
                'is_active' => $userDto->active ?? true,
                'department_id' => $userDto->department_id !== null ? ($userDto->department_id[0] ?? null) : null,
                'job_title' => $userDto->job_title ?? null,
            ]);

            return $user;
        }

        // Create new user
        return User::create([
            'name' => $userDto->name,
            'email' => $normalizedEmail,
            'timezone' => $userDto->tz ?? 'UTC',
            'is_active' => $userDto->active ?? true,
            'department_id' => $userDto->department_id !== null ? ($userDto->department_id[0] ?? null) : null,
            'job_title' => $userDto->job_title ?? null,
        ]);
    }

    /**
     * Create or update the Odoo external identity for the user.
     */
    private function syncExternalIdentity(User $user, OdooUserDTO $userDto): void
    {
        UserExternalIdentity::updateOrCreate(
            [
                'user_id' => $user->id,
                'platform' => Platform::Odoo,
            ],
            [
                'external_id' => (string) $userDto->id,
                'external_email' => Str::lower($userDto->work_email),
                'is_manual_link' => false,
                'linked_by' => 'email',
            ]
        );
    }

    /**
     * Sync the user's manager relationship.
     *
     * Looks up the manager by their Odoo external identity and sets the manager_id.
     * If the manager hasn't been synced yet, manager_id will remain null.
     */
    private function syncManagerRelationship(User $user, OdooUserDTO $userDto): void
    {
        $odooManagerId = $userDto->parent_id !== null ? ($userDto->parent_id[0] ?? null) : null;

        if (! $odooManagerId) {
            // No manager in Odoo, clear the relationship
            if ($user->manager_id !== null) {
                $user->update(['manager_id' => null]);
            }

            return;
        }

        // Look up the manager by their Odoo external identity
        $managerIdentity = UserExternalIdentity::where('platform', Platform::Odoo)
            ->where('external_id', (string) $odooManagerId)
            ->first();

        if ($managerIdentity) {
            // Manager exists, set the relationship
            if ($user->manager_id !== $managerIdentity->user_id) {
                $user->update(['manager_id' => $managerIdentity->user_id]);
            }
        } else {
            // Manager not synced yet, leave manager_id as-is (will be set on next sync)
            Log::debug('Manager not found for user, will be set on next sync.', [
                'user_id' => $user->id,
                'odoo_manager_id' => $odooManagerId,
            ]);
        }
    }

    /**
     * Sync the user's categories (many-to-many) with the pivot table.
     *
     * This method updates the pivot table (category_user) to match the categories
     * provided by the OdooUserDTO. It will add and remove links as needed.
     *
     * @param  User  $user  The local user model.
     * @param  OdooUserDTO  $userDto  The OdooUserDTO containing category data.
     */
    private function syncUserCategories(User $user, OdooUserDTO $userDto): void
    {
        $categoryIds = collect($userDto->category_ids)
            ->filter()
            ->unique();
        // Use odoo_category_id as the primary key for Category
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
     * @param  OdooUserDTO  $userDto  The OdooUserDTO containing schedule data.
     */
    private function syncUserSchedule(User $user, OdooUserDTO $userDto): void
    {
        $startOfDay = now()->startOfDay();
        $newOdooScheduleId = $userDto->resource_calendar_id !== null ? ($userDto->resource_calendar_id[0] ?? null) : null;

        if (! $newOdooScheduleId || ! Schedule::where('odoo_schedule_id', $newOdooScheduleId)->exists()) {
            return;
        }

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
    }
}
