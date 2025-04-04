<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\User;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Collection;

/**
 * Class SyncOdooDepartments
 *
 * Synchronizes hr.department data from Odoo into local departments table.
 * This job ensures the local departments database reflects the current state of Odoo,
 * including creating new departments, updating existing ones, preserving and logging departments
 * that no longer exist in Odoo, and updating users' department assignments.
 */
class SyncOdooDepartments extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   * Lower numbers indicate higher priority.
   *
   * @var int
   */
  public int $priority = 1;

  /**
   * SyncOdooDepartments constructor.
   *
   * @param OdooApiCalls $odoo An instance of the OdooApiCalls service.
   */
  public function __construct(OdooApiCalls $odoo)
  {
    $this->odoo = $odoo;
  }

  /**
   * Executes the synchronization process.
   *
   * This method performs the following operations:
   * 1. Fetches departments from Odoo API and maps them to local structure
   * 2. Creates or updates local departments based on Odoo data
   * 3. Identifies departments that exist locally but not in Odoo
   * 4. Logs missing departments for historical integrity
   * 5. Updates user department assignments based on Odoo relations
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Step 1: Fetch and map departments from Odoo
    $mappedDepartments = $this->mapOdooDepartments();

    // Step 2: Create or update local departments based on Odoo data
    $this->syncDepartments($mappedDepartments);

    // Step 3: Log departments that exist locally but not in Odoo
    $this->logMissingDepartments(
      $mappedDepartments->pluck('odoo_department_id')
    );

    // Step 4: Update user department assignments
    $this->updateUserDepartments();
  }

  /**
   * Maps Odoo departments to our local structure.
   *
   * @return Collection
   */
  private function mapOdooDepartments(): Collection
  {
    return $this->odoo->getDepartments()->map(function ($dept) {
      return [
        'odoo_department_id' => $dept['id'],
        'name' => $dept['name'],
        'active' => $dept['active'] ?? true,
      ];
    });
  }

  /**
   * Creates or updates local departments based on Odoo data.
   *
   * @param Collection $mappedDepartments
   * @return void
   */
  private function syncDepartments(Collection $mappedDepartments): void
  {
    $mappedDepartments->each(function ($dept) {
      Department::updateOrCreate(
        ['odoo_department_id' => $dept['odoo_department_id']],
        [
          'name' => $dept['name'],
          'active' => $dept['active'],
        ]
      );
    });
  }

  /**
   * Logs departments that exist locally but not in Odoo for historical integrity.
   *
   * @param Collection $currentOdooDepartmentIds
   * @return void
   */
  private function logMissingDepartments(
    Collection $currentOdooDepartmentIds
  ): void {
    Department::pluck('odoo_department_id')
      ->diff($currentOdooDepartmentIds)
      ->pipe(function ($departmentsToLog) {
        if ($departmentsToLog->isEmpty()) {
          return;
        }

        Department::whereIn('odoo_department_id', $departmentsToLog)
          ->get()
          ->each(function ($department) {
            Log::channel('sync')->info(
              class_basename($this) .
                ': Department no longer exists in Odoo but preserved for historical integrity',
              [
                'odoo_department_id' => $department->odoo_department_id,
                'name' => $department->name,
                'detected_at' => now()->toDateTimeString(),
              ]
            );
          });
      });
  }

  /**
   * Updates user department assignments based on Odoo relations.
   *
   * @return void
   */
  private function updateUserDepartments(): void
  {
    collect($this->odoo->getUserRelations())
      ->filter(function ($relation) {
        return Arr::has($relation, 'department_id.0');
      })
      ->each(function ($relation) {
        User::where('odoo_id', $relation['id'])
          ->trackable()
          ->get()
          ->each(function ($user) use ($relation) {
            $deptId = $relation['department_id'][0];
            $user->department_id = $deptId;

            // Only save if the department_id actually changed
            if ($user->isDirty('department_id')) {
              $user->save();
            }
          });
      });
  }
}
