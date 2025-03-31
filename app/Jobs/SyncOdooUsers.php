<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OdooApiCalls;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Exception;

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
   * This method performs three main operations:
   * 1. Fetches and filters users from Odoo API
   * 2. Creates or updates local users based on Odoo data
   * 3. Removes local users that no longer exist in Odoo
   *
   * @return void
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    // Retrieve Odoo users and filter out those without an email address (the email is required as primary key to sync users across all platforms)
    $odooUsers = $this->odoo->getUsers()->filter(function ($odooUser) {
      return filled($odooUser['work_email']);
    });

    // Create or update local users based on Odoo data
    foreach ($odooUsers as $odooUser) {
      $userData = [
        'name' => $odooUser['name'],
        'email' => Str::lower($odooUser['work_email']),
        'timezone' => $odooUser['tz'] ?? 'UTC',
      ];

      User::updateOrCreate(['odoo_id' => $odooUser['id']], $userData);
    }

    // Find and remove users that exist locally but not in Odoo anymore
    // This ensures data consistency between systems
    $odooUserIds = $odooUsers->pluck('id');
    $localOdooIds = User::whereNotNull('odoo_id')->pluck('odoo_id');

    // Find IDs that exist locally but not in Odoo
    $usersToDelete = $localOdooIds->diff($odooUserIds);

    if ($usersToDelete->isNotEmpty()) {
      // Get the users we want to delete
      $users = User::whereIn('odoo_id', $usersToDelete)->get();

      // Delete each user individually to trigger model events
      // This is preferred over bulk deletion which doesn't trigger events
      foreach ($users as $user) {
        $user->delete();
      }
    }
  }
}
