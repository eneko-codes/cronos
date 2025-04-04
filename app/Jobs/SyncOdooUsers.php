<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OdooApiCalls;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class SyncOdooUsers
 *
 * Synchronizes hr.employee data from Odoo into the local users table.
 * This job ensures the local users database reflects the current state
 * of the Odoo system, including creating new users, updating existing ones,
 * and removing users that no longer exist in Odoo.
 */
class SyncOdooUsers extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   * Lower numbers indicate higher priority.
   *
   * @var int
   */
  public int $priority = 1;

  /**
   * SyncOdooUsers constructor.
   *
   * @param OdooApiCalls $odoo An instance of the OdooApiCalls service.
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
   * 1. Fetches employees from Odoo API
   * 2. Identifies and logs employees without email addresses
   * 3. Filters to keep only employees with valid emails
   * 4. Creates or updates local user records
   * 5. Identifies users in local DB that no longer exist in Odoo
   * 6. Deletes obsolete user records
   *
   * @return void
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    // Step 1: Fetch all employees from Odoo API as a Collection
    $odooEmployees = $this->odoo->getUsers();

    // Step 2: Process employees without email addresses
    $this->logEmployeesWithoutEmail($odooEmployees);

    // Step 3: Process valid employees (those with email addresses)
    $validEmployees = $odooEmployees->filter(function ($employee) {
      return filled($employee['work_email']);
    });

    // Step 4: Create or update users in a single operation
    $this->syncValidEmployees($validEmployees);

    // Step 5: Clean up obsolete users
    $this->removeObsoleteUsers($validEmployees->pluck('id'));
  }

  /**
   * Logs employees who are missing email addresses.
   *
   * @param Collection $employees Collection of employees from Odoo
   * @return void
   */
  private function logEmployeesWithoutEmail(Collection $employees): void
  {
    $employees
      ->filter(function ($employee) {
        return empty($employee['work_email']);
      })
      ->whenNotEmpty(function ($employeesWithoutEmail) {
        $employeesWithoutEmail->each(function ($employee) {
          Log::channel('sync')->warning(
            class_basename($this) .
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
   * Creates or updates local user records from valid Odoo employees.
   *
   * @param Collection $validEmployees Collection of valid employees from Odoo
   * @return void
   */
  private function syncValidEmployees(Collection $validEmployees): void
  {
    $validEmployees->each(function ($employee) {
      User::updateOrCreate(
        ['odoo_id' => $employee['id']],
        [
          'name' => $employee['name'],
          'email' => Str::lower($employee['work_email']),
          'timezone' => $employee['tz'] ?? 'UTC',
        ]
      );
    });
  }

  /**
   * Removes local user records that no longer exist in Odoo.
   *
   * @param Collection $currentOdooIds Collection of current Odoo employee IDs
   * @return void
   */
  private function removeObsoleteUsers(Collection $currentOdooIds): void
  {
    // Identify users that exist in local DB but not in current Odoo data
    User::whereNotNull('odoo_id')
      ->pluck('odoo_id')
      ->diff($currentOdooIds)
      ->pipe(function ($obsoleteUserIds) {
        // Delete obsolete users to properly trigger model events
        if ($obsoleteUserIds->isNotEmpty()) {
          User::whereIn('odoo_id', $obsoleteUserIds)->get()->each->delete();
        }
      });
  }
}
