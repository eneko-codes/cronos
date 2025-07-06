<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Category;
use App\Models\Schedule;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo employee data (hr.employee) with the local users table.
 *
 * Ensures the local users database reflects the current state of Odoo, including:
 * - Creating new users and updating existing ones
 * - Syncing active status, department, categories, and schedule assignments
 * - Marking users as inactive if they no longer exist in Odoo
 */
class SyncOdooUsers extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncOdooUsers job instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client instance.
     */
    public function __construct(OdooApiClient $odoo)
    {
        // Assign the parent's protected ?OdooApiClient $odoo property.
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Performs the following operations:
     * 1. Fetches employees from Odoo API
     * 2. Logs employees without email addresses
     * 3. Filters to keep only employees with valid emails
     * 4. Creates or updates local user records and relationships
     * 5. Deactivates users no longer present in Odoo
     *
     * @throws Exception If the sync logic fails.
     */
    protected function execute(): void
    {
        // Step 1: Fetch all employees from Odoo API as a Collection of UserDTOs
        $odooEmployees = $this->odoo->getUsers();

        // Step 2: Process employees without email addresses
        $this->logEmployeesWithoutEmail($odooEmployees);

        // Step 3: Process valid employees (those with email addresses)
        $validEmployees = $odooEmployees->filter(function (OdooUserDTO $employee) {
            return filled($employee->work_email);
        });

        // Step 4: Create or update users and their relationships
        $this->syncValidEmployees($validEmployees);

        // Step 5 & 6: Deactivate obsolete users
        $this->deactivateObsoleteUsers($validEmployees->pluck('id'));
        Log::info(class_basename(static::class).' Finished', ['job' => class_basename(static::class)]);
    }

    /**
     * Logs Odoo employees who are missing email addresses.
     *
     * @param  Collection|OdooUserDTO[]  $employees  Collection of employees from Odoo.
     */
    private function logEmployeesWithoutEmail(Collection $employees): void
    {
        $employees
            ->filter(function (OdooUserDTO $employee) {
                return empty($employee->work_email);
            })
            ->whenNotEmpty(function ($employeesWithoutEmail): void {
                $employeesWithoutEmail->each(function (OdooUserDTO $employee): void {
                    Log::warning(
                        class_basename(static::class).
                          ": Odoo employee '{$employee->name}' missing work email",
                        [
                            'odoo_id' => $employee->id,
                            'name' => $employee->name,
                        ]
                    );
                });
            });
    }

    /**
     * Creates or updates local user records from valid Odoo employees, including department, category, and schedule assignments.
     *
     * @param  Collection|OdooUserDTO[]  $validEmployees  Collection of valid employees from Odoo.
     */
    private function syncValidEmployees(Collection $validEmployees): void
    {
        $validEmployees->each(function (OdooUserDTO $employee): void {
            if (empty($employee->name) || empty($employee->work_email)) {
                Log::warning(class_basename(static::class).' Skipping user with missing required fields', [
                    'job' => class_basename(static::class),
                    'entity' => 'user',
                    'employee' => $employee,
                ]);

                return;
            }
            $user = User::updateOrCreate(
                ['odoo_id' => $employee->id],
                [
                    'name' => $employee->name,
                    'email' => \Str::lower($employee->work_email),
                    'timezone' => $employee->tz ?? 'UTC',
                    'is_active' => $employee->active ?? true, // Sync active status
                    'department_id' => $employee->department_id, // Sync department
                    'job_title' => $employee->job_title ?? null, // Sync job title
                    'odoo_manager_id' => $employee->parent_id, // Sync manager Odoo ID
                ]
            );

            // Sync categories
            $this->syncUserCategories($user, $employee->category_ids ?? []);

            // Sync schedule
            $this->syncUserSchedule($user, $employee->resource_calendar_id);
        });
    }

    /**
     * Synchronizes the user's categories with the local database.
     *
     * @param  User  $user  The local user model.
     * @param  array  $odooCategoryIds  Array of Odoo category IDs for this user.
     */
    private function syncUserCategories(User $user, array $odooCategoryIds): void
    {
        // Ensure the category IDs exist in the local categories table before syncing
        $validLocalCategoryIds = Category::whereIn('odoo_category_id', $odooCategoryIds)->pluck('odoo_category_id');
        $user->categories()->sync($validLocalCategoryIds);
    }

    /**
     * Synchronizes the user's schedule assignment with the local database.
     *
     * @param  User  $user  The local user model.
     * @param  int|null  $newOdooScheduleId  The Odoo schedule ID for this user, or null.
     */
    private function syncUserSchedule(User $user, ?int $newOdooScheduleId): void
    {
        $startOfDay = Carbon::now()->startOfDay();

        // Get the currently active schedule assignment
        /** @var \App\Models\UserSchedule|null $activeUserSchedule */
        $activeUserSchedule = $user->activeUserSchedule()->first();
        $currentOdooScheduleId = $activeUserSchedule?->odoo_schedule_id;

        // Case 1: No change
        if ($currentOdooScheduleId === $newOdooScheduleId) {
            return;
        }

        // Case 2: Schedule removed or becomes invalid
        if (! $newOdooScheduleId || ! Schedule::where('odoo_schedule_id', $newOdooScheduleId)->exists()) {
            if ($activeUserSchedule) {
                $activeUserSchedule->update(['effective_until' => $startOfDay]);
            }

            return; // No new schedule to assign
        }

        // Case 3: Schedule changed or assigned for the first time
        // Close the old assignment if it exists
        if ($activeUserSchedule) {
            $activeUserSchedule->update(['effective_until' => $startOfDay]);
        }

        // Create the new assignment
        $user->userSchedules()->create([
            'odoo_schedule_id' => $newOdooScheduleId,
            'effective_from' => $startOfDay,
            'effective_until' => null,
        ]);
    }

    /**
     * Deactivates local user records that no longer exist in the current Odoo fetch.
     *
     * @param  Collection  $currentOdooIds  Collection of current Odoo employee IDs.
     */
    private function deactivateObsoleteUsers(Collection $currentOdooIds): void
    {
        // Find users in DB (with odoo_id) not in the current Odoo list
        User::whereNotNull('odoo_id')
            ->whereNotIn('odoo_id', $currentOdooIds)
            ->where('is_active', true) // Only deactivate those currently active
            ->get()
            ->each(function (User $user): void {
                Log::info(class_basename(static::class).': Deactivating user no longer found in Odoo sync.', [
                    'user_id' => $user->id,
                    'odoo_id' => $user->odoo_id,
                    'name' => $user->name,
                ]);
                $user->update(['is_active' => false]);
            });
    }
}
