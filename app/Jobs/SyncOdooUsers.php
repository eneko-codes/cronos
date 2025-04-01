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
   * This method performs the following operations:
   * 1. Fetches users from Odoo API and filters out those without email addresses
   * 2. Creates or updates local users based on Odoo data
   * 3. Identifies users that exist locally but not in Odoo
   * 4. Deletes local users that no longer exist in Odoo
   *
   * @return void
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    // Retrieve Odoo users and filter out those without an email address
    $odooUsers = $this->odoo->getUsers()->filter(function ($odooUser) {
      return filled($odooUser['work_email']);
    });

    // Create or update local users based on Odoo data
    $odooUsers->each(function ($odooUser) {
      User::updateOrCreate(
        ['odoo_id' => $odooUser['id']],
        [
          'name' => $odooUser['name'],
          'email' => Str::lower($odooUser['work_email']),
          'timezone' => $odooUser['tz'] ?? 'UTC',
        ]
      );
    });

    // Find and remove users that exist locally but not in Odoo anymore
    $odooUserIds = $odooUsers->pluck('id');
    $localOdooIds = User::whereNotNull('odoo_id')->pluck('odoo_id');

    // Find IDs that exist locally but not in Odoo
    $usersToDelete = $localOdooIds->diff($odooUserIds);

    // Delete users individually to trigger model events
    User::whereIn('odoo_id', $usersToDelete)->get()->each->delete();
  }
}
