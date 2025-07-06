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
     * - Fetches employees from Odoo API
     * - Logs employees without email addresses
     * - Filters to keep only employees with valid emails
     * - Creates or updates local user records and relationships
     * - Deactivates users no longer present in Odoo
     *
     * @throws Exception If the sync logic fails.
     */
    protected function execute(): void
    {
        // Fetch all employees from Odoo API as a Collection of UserDTOs
        $odooEmployees = $this->odoo->getUsers();

        // Log employees who are missing email addresses for audit/debugging
        $this->logEmployeesWithoutEmail($odooEmployees);

        // Filter to keep only employees with valid emails (required for local user creation)
        $validEmployees = $odooEmployees->filter(function (OdooUserDTO $employee) {
            return filled($employee->work_email);
        });

        // Create or update users and their relationships (department, categories, schedule)
        $this->syncValidEmployees($validEmployees);

        // Deactivate users not present in Odoo (mark as inactive locally)
        $this->deactivateObsoleteUsers($validEmployees->pluck('id'));
    }

    /**
     * Logs Odoo employees who are missing email addresses.
     *
     * Iterates through the provided collection and logs a warning for each employee
     * that does not have a work email. This helps identify incomplete or problematic
     * records in the Odoo source data.
     *
     * @param  Collection|OdooUserDTO[]  $employees  Collection of employees from Odoo.
     */
    private function logEmployeesWithoutEmail(Collection $employees): void
    {
        $employees
            // Filter employees with empty work_email
            ->filter(function (OdooUserDTO $employee) {
                return empty($employee->work_email);
            })
            // If any are found, log each one
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
     * Creates or updates local user records from valid Odoo employees.
     *
     * For each valid Odoo employee, this method will:
     * - Create a new user or update an existing one in the local database.
     * - Sync department, categories, and schedule assignments.
     *
     * @param  Collection|OdooUserDTO[]  $validEmployees  Collection of valid employees from Odoo.
     */
    private function syncValidEmployees(Collection $validEmployees): void
    {
        $validEmployees->each(function (OdooUserDTO $employee): void {
            // Skip if required fields are missing
            if (empty($employee->name) || empty($employee->work_email)) {
                Log::warning(class_basename(static::class).' Skipping user with missing required fields', [
                    'job' => class_basename(static::class),
                    'entity' => 'user',
                    'employee' => $employee,
                ]);

                return;
            }
            // Create or update the user record
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
            // Sync user categories (many-to-many)
            $this->syncUserCategories($user, $employee->category_ids ?? []);
            // Sync user schedule (one-to-many, with effective dates)
            $this->syncUserSchedule($user, $employee->resource_calendar_id);
        });
    }

    /**
     * Synchronizes the user's categories with the local database.
     *
     * Ensures that the user's category assignments in the local database match
     * the list of Odoo category IDs provided. Only valid local categories are synced.
     *
     * @param  User  $user  The local user model.
     * @param  array  $odooCategoryIds  Array of Odoo category IDs for this user.
     */
    private function syncUserCategories(User $user, array $odooCategoryIds): void
    {
        // Only sync categories that exist locally
        $validLocalCategoryIds = Category::whereIn('odoo_category_id', $odooCategoryIds)->pluck('odoo_category_id');
        $user->categories()->sync($validLocalCategoryIds);
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
