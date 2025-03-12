<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OdooApiCalls;
use Illuminate\Support\Collection;
use Exception;

/**
 * Class SyncOdooUsers
 *
 * Synchronizes hr.employee data from Odoo into the local users table,
 * and invalidates the entire cache store upon completion.
 */
class SyncOdooUsers extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
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
   * @return void
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    $odooUsers = $this->odoo->getUsers()->filter(function ($odooUser) {
      return !empty($odooUser['work_email']);
    });

    $existingUsers = User::whereNotNull('odoo_id')->get()->keyBy('odoo_id');

    foreach ($odooUsers as $odooUser) {
      $userData = [
        'name' => $odooUser['name'],
        'email' => strtolower($odooUser['work_email']),
        'timezone' => $odooUser['tz'] ?? 'UTC',
      ];

      User::updateOrCreate(['odoo_id' => $odooUser['id']], $userData);
    }

    // Clear out any user who has an odoo_id but isn't in Odoo users
    $odooUserIds = $odooUsers->pluck('id');
    $localOdooIds = User::whereNotNull('odoo_id')->pluck('odoo_id');

    $usersToDelete = $localOdooIds->diff($odooUserIds);

    if ($usersToDelete->isNotEmpty()) {
      // Get the users we want to delete
      $users = User::whereIn('odoo_id', $usersToDelete)->get();

      // Delete each user individually to trigger model events
      foreach ($users as $user) {
        $user->delete();
      }
    }
  }
}
