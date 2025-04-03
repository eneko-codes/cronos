<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\User;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

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
    $mappedDepartments = $this->odoo->getDepartments()->map(function ($dept) {
      return [
        'odoo_department_id' => $dept['id'],
        'name' => $dept['name'],
        'active' => $dept['active'] ?? true,
      ];
    });

    // Step 2: Create or update local departments based on Odoo data individually to trigger model events
    $mappedDepartments->each(function ($dept) {
      Department::updateOrCreate(
        ['odoo_department_id' => $dept['odoo_department_id']],
        [
          'name' => $dept['name'],
          'active' => $dept['active'],
        ]
      );
    });

    // Step 3: Identifies departments that exist locally but not in Odoo
    $odooDeptIds = $mappedDepartments->pluck('odoo_department_id');
    $localDeptIds = Department::pluck('odoo_department_id');
    $departmentsToLog = $localDeptIds->diff($odooDeptIds);

    // Step 4: Log departments that exist locally but not in Odoo for historical integrity
    if ($departmentsToLog->isNotEmpty()) {
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
    }

    // Step 5: Update user department assignments
    $userRelations = $this->odoo->getUserRelations();
    collect($userRelations)->each(function ($relation) {
      // If the user has no department, skip
      if (!Arr::has($relation, 'department_id.0')) {
        return;
      }

      // Update the user's department if they're not marked as do_not_track
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
