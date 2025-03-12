<?php

namespace App\Jobs;

use App\Models\Department;
use App\Models\User;
use App\Services\OdooApiCalls;
use Exception;
use Illuminate\Support\Collection;

/**
 * Class SyncOdooDepartments
 *
 * Updates department data from Odoo, synchronizes users' department assignments,
 * and invalidates the entire cache store upon completion.
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
   * Removed protected OdooApiCalls $odoo;
   */
  public function __construct(OdooApiCalls $odoo)
  {
    $this->odoo = $odoo;
  }

  protected function execute(): void
  {
    $odooDepartments = $this->odoo->getDepartments();

    $mappedDepts = $odooDepartments->map(
      fn($dept) => [
        'odoo_department_id' => $dept['id'],
        'name' => $dept['name'],
        'active' => $dept['active'] ?? true,
      ]
    );

    // Upsert departments
    foreach ($mappedDepts as $dept) {
      Department::updateOrCreate(
        ['odoo_department_id' => $dept['odoo_department_id']],
        [
          'name' => $dept['name'],
          'active' => $dept['active'],
        ]
      );
    }

    // Identify and delete departments not in Odoo
    $odooDeptIds = $mappedDepts->pluck('odoo_department_id');
    $localDeptIds = Department::pluck('odoo_department_id');

    $departmentsToDelete = $localDeptIds->diff($odooDeptIds);

    if ($departmentsToDelete->isNotEmpty()) {
      Department::whereIn('odoo_department_id', $departmentsToDelete)
        ->get()
        ->each(fn($dept) => $dept->delete());
    }

    // Update users' department_id
    $userRelations = $this->odoo->getUserRelations();

    foreach ($userRelations as $relation) {
      $user = User::where('odoo_id', $relation['id'])
        ->where('do_not_track', false)
        ->first();

      if ($user) {
        $deptId = $relation['department_id'][0] ?? null;
        $user->department_id = $deptId;
        $user->save();
      }
    }
  }
}
