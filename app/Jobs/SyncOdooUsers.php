<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Category;
use App\Models\Schedule;
use App\Models\User;
use App\Services\OdooApiCalls;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Class SyncOdooUsers
 *
 * Synchronizes hr.employee data from Odoo into the local users table.
 * This job ensures the local users database reflects the current state
 * of the Odoo system, including creating new users, updating existing ones,
 * handling active status, department, categories, and schedule assignments.
 * Users no longer present in Odoo are marked as inactive.
 */
class SyncOdooUsers extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     * Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * SyncOdooUsers constructor.
     *
     * @param  OdooApiCalls  $odoo  An instance of the OdooApiCalls service.
     */
    public function __construct(OdooApiCalls $odoo)
    {
        // Assign the parent's protected ?OdooApiCalls $odoo property.
        $this->odoo = $odoo;
    }

    /**
     * Executes the synchronization process.
     *
     * This method performs the following operations:
     * 1. Fetches employees from Odoo API (now includes dept, cats, schedule)
     * 2. Identifies and logs employees without email addresses
     * 3. Filters to keep only employees with valid emails
     * 4. Creates or updates local user records, including relationships
     * 5. Identifies users in local DB that no longer exist in Odoo
     * 6. Deactivates obsolete user records instead of deleting
     *
     *
     * @throws Exception
     */
    protected function execute(): void
    {
        // Step 1: Fetch all employees from Odoo API as a Collection
        // Ensure getUsers() fetches 'job_title' and 'parent_id' fields
        $odooEmployees = $this->odoo->getUsers();

        // Step 2: Process employees without email addresses
        $this->logEmployeesWithoutEmail($odooEmployees);

        // Step 3: Process valid employees (those with email addresses)
        $validEmployees = $odooEmployees->filter(function ($employee) {
            return filled($employee['work_email']);
        });

        // Step 4: Create or update users and their relationships
        $this->syncValidEmployees($validEmployees);

        // Step 5 & 6: Deactivate obsolete users
        $this->deactivateObsoleteUsers($validEmployees->pluck('id'));
    }

    /**
     * Logs employees who are missing email addresses.
     *
     * @param  Collection  $employees  Collection of employees from Odoo
     */
    private function logEmployeesWithoutEmail(Collection $employees): void
    {
        $employees
            ->filter(function ($employee) {
                return empty($employee['work_email']);
            })
            ->whenNotEmpty(function ($employeesWithoutEmail) {
                $employeesWithoutEmail->each(function ($employee) {
                    Log::warning(
                        class_basename($this).
                          ": Odoo employee '{$employee['name']}' missing work email",
                        [
                            'odoo_id' => $employee['id'],
                            'name' => $employee['name'],
                        ]
                    );
                });
            });
    }

    /**
     * Creates or updates local user records from valid Odoo employees,
     * including department, category, and schedule assignments.
     *
     * @param  Collection  $validEmployees  Collection of valid employees from Odoo
     */
    private function syncValidEmployees(Collection $validEmployees): void
    {
        $validEmployees->each(function ($employee) {
            $user = User::updateOrCreate(
                ['odoo_id' => $employee['id']],
                [
                    'name' => $employee['name'],
                    'email' => Str::lower($employee['work_email']),
                    'timezone' => $employee['tz'] ?? 'UTC',
                    'is_active' => $employee['active'] ?? true, // Sync active status
                    'department_id' => Arr::get($employee, 'department_id.0'), // Sync department
                    'job_title' => $employee['job_title'] ?? null, // Sync job title
                    'odoo_manager_id' => Arr::get($employee, 'parent_id.0'), // Sync manager Odoo ID
                ]
            );

            // Sync categories
            $this->syncUserCategories($user, $employee['category_ids'] ?? []);

            // Sync schedule
            $this->syncUserSchedule($user, Arr::get($employee, 'resource_calendar_id.0'));
        });
    }

    /**
     * Synchronizes the user's categories.
     *
     * @param  User  $user  The local user model
     * @param  array  $odooCategoryIds  Array of Odoo category IDs for this user
     */
    private function syncUserCategories(User $user, array $odooCategoryIds): void
    {
        // Ensure the category IDs exist in the local categories table before syncing
        $validLocalCategoryIds = Category::whereIn('odoo_category_id', $odooCategoryIds)->pluck('odoo_category_id');
        $user->categories()->sync($validLocalCategoryIds);
    }

    /**
     * Synchronizes the user's schedule assignment.
     *
     * @param  User  $user  The local user model
     * @param  int|null  $newOdooScheduleId  The Odoo schedule ID for this user, or null
     */
    private function syncUserSchedule(User $user, ?int $newOdooScheduleId): void
    {
        $startOfDay = Carbon::now()->startOfDay();

        // Get the currently active schedule assignment
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
     * @param  Collection  $currentOdooIds  Collection of current Odoo employee IDs
     */
    private function deactivateObsoleteUsers(Collection $currentOdooIds): void
    {
        // Find users in DB (with odoo_id) not in the current Odoo list
        User::whereNotNull('odoo_id')
            ->whereNotIn('odoo_id', $currentOdooIds)
            ->where('is_active', true) // Only deactivate those currently active
            ->get()
            ->each(function (User $user) {
                Log::info(class_basename($this).': Deactivating user no longer found in Odoo sync.', [
                    'user_id' => $user->id,
                    'odoo_id' => $user->odoo_id,
                    'name' => $user->name,
                ]);
                $user->update(['is_active' => false]);
            });
    }
}
