<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\User;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Collection;
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
   * This method orchestrates the entire department synchronization workflow
   * by calling more specific methods for each step in the process.
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Fetch and map departments from Odoo
    $mappedDepartments = $this->fetchAndMapDepartments();

    // Synchronize local departments with Odoo data
    $this->syncLocalDepartments($mappedDepartments);

    // Log departments that exist locally but not in Odoo
    $this->logMissingDepartments($mappedDepartments);

    // Update user department assignments
    $this->updateUserDepartments();
  }

  /**
   * Fetches departments from Odoo and maps them to our local structure.
   *
   * @return Collection Collection of mapped department data
   * @throws Exception If API call fails
   */
  private function fetchAndMapDepartments(): Collection
  {
    $odooDepartments = $this->odoo->getDepartments();

    return $odooDepartments->map(function ($dept) {
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
   * @param Collection $mappedDepartments Collection of mapped department data from Odoo
   */
  private function syncLocalDepartments(Collection $mappedDepartments): void
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
   * Identifies and logs departments that exist locally but not in Odoo.
   * Departments are preserved for historical integrity rather than deleted.
   *
   * @param Collection $mappedDepartments Collection of mapped department data from Odoo
   */
  private function logMissingDepartments(Collection $mappedDepartments): void
  {
    $odooDeptIds = $mappedDepartments->pluck('odoo_department_id');
    $localDeptIds = Department::pluck('odoo_department_id');

    $departmentsToLog = $localDeptIds->diff($odooDeptIds);

    if ($departmentsToLog->isNotEmpty()) {
      Department::whereIn('odoo_department_id', $departmentsToLog)
        ->get()
        ->each(function ($department) {
          Log::channel('sync')->info(
            'Department no longer exists in Odoo but preserved for historical integrity',
            [
              'odoo_department_id' => $department->odoo_department_id,
              'name' => $department->name,
              'detected_at' => now()->toDateTimeString(),
            ]
          );
        });
    }
  }

  /**
   * Updates users' department assignments based on Odoo relations.
   * Only updates users who are not marked as do_not_track.
   *
   * @throws Exception If API call fails
   */
  private function updateUserDepartments(): void
  {
    $userRelations = $this->odoo->getUserRelations();

    collect($userRelations)->each(function ($relation) {
      // Only update if department_id exists in the relation
      if (!Arr::has($relation, 'department_id.0')) {
        return;
      }

      // Find and update user if they're not marked as do_not_track
      User::where('odoo_id', $relation['id'])
        ->where('do_not_track', false)
        ->get()
        ->each(function ($user) use ($relation) {
          $deptId = $relation['department_id'][0];
          $user->department_id = $deptId;
          $user->save();
        });
    });
  }
}
