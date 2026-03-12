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
 * Odoo external identity, handles categories, and schedules.
 *
 * ## Email Storage Strategy
 *
 * This action stores the Odoo `work_email` in TWO places:
 *
 * 1. **users.email** (Primary Authentication Email):
 *    - Used by Laravel's authentication system (Auth::attempt)
 *    - Required for login, password reset, email verification
 *    - Always synced from Odoo's work_email field
 *    - See User model documentation for details
 *
 * 2. **user_external_identities.external_email** (Platform-Specific Email):
 *    - Stores the email as it appears in Odoo for data synchronization
 *    - Used for cross-platform user matching
 *    - Allows users to have different emails per platform
 *
 * This dual storage is intentional and necessary:
 * - Laravel 12 requires users.email for authentication
 * - Platform emails may differ across systems (Odoo vs DeskTime vs ProofHub)
 * - Direct column access is faster than joins for authentication queries
 *
 * Both emails are kept in sync during Odoo synchronization to ensure consistency.
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
        });
    }

    /**
     * Find an existing user by Odoo external identity or create a new one.
     *
     * This method implements a three-step lookup strategy:
     * 1. Find by Odoo external identity (most reliable - uses Odoo ID)
     * 2. Find by primary email (users.email) - for users synced before external identities existed
     * 3. Create new user if not found
     *
     * **Important:** When updating or creating a user, this method always sets
     * `users.email` to the Odoo work_email. This ensures the primary authentication
     * email stays in sync with Odoo, which is the source of truth.
     *
     * The Odoo email is also stored in user_external_identities.external_email
     * via syncExternalIdentity() to maintain platform-specific email records.
     *
     * @param  OdooUserDTO  $userDto  The Odoo user data transfer object
     * @return User The found or newly created user instance
     */
    private function findOrCreateUser(OdooUserDTO $userDto): User
    {
        $normalizedEmail = Str::lower($userDto->work_email);

        // First, try to find by Odoo external identity (most reliable match)
        $existingIdentity = UserExternalIdentity::where('platform', Platform::Odoo)
            ->where('external_id', (string) $userDto->id)
            ->first();

        if ($existingIdentity) {
            // User exists, update their data including primary email
            // This keeps users.email in sync with Odoo work_email
            $user = $existingIdentity->user;

            // Prepare update data
            $updateData = [
                'name' => $userDto->name,
                'email' => $normalizedEmail, // Keep primary email synced with Odoo
                'timezone' => $userDto->tz ?? 'UTC',
                'department_id' => $userDto->department_id !== null ? ($userDto->department_id[0] ?? null) : null,
                'job_title' => $userDto->job_title ?? null,
            ];

            $user->update($updateData);

            return $user;
        }

        // Try to find by primary email (for users that exist but don't have Odoo identity yet)
        // This handles legacy users or users created before external identities were implemented
        $user = User::where('email', $normalizedEmail)->first();

        if ($user) {
            // Update existing user's data (email already matches, so no need to update it)
            $updateData = [
                'name' => $userDto->name,
                'timezone' => $userDto->tz ?? 'UTC',
                'department_id' => $userDto->department_id !== null ? ($userDto->department_id[0] ?? null) : null,
                'job_title' => $userDto->job_title ?? null,
            ];

            $user->update($updateData);

            return $user;
        }

        // Create new user with Odoo email as primary authentication email
        // This email will be used for Laravel authentication (login, password reset, etc.)
        // Note: New users are always created as active - archiving must be done manually by admins
        return User::create([
            'name' => $userDto->name,
            'email' => $normalizedEmail, // Primary authentication email from Odoo
            'timezone' => $userDto->tz ?? 'UTC',
            'is_active' => true, // Always create as active - archiving is manual only
            'department_id' => $userDto->department_id !== null ? ($userDto->department_id[0] ?? null) : null,
            'job_title' => $userDto->job_title ?? null,
        ]);
    }

    /**
     * Create or update the Odoo external identity for the user.
     *
     * This method stores the Odoo email in user_external_identities.external_email.
     * Note that the same email is also stored in users.email (via findOrCreateUser)
     * for authentication purposes. This dual storage is intentional:
     *
     * - users.email: Primary authentication email (required by Laravel)
     * - user_external_identities.external_email: Platform-specific email record
     *
     * Both are kept in sync during Odoo synchronization to ensure consistency.
     *
     * @param  User  $user  The user model instance
     * @param  OdooUserDTO  $userDto  The Odoo user data transfer object
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
                'external_email' => Str::lower($userDto->work_email), // Platform-specific email
                'is_manual_link' => false,
                'linked_by' => 'email',
            ]
        );
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
