<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\OdooApiCalls;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
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
   * 1. Fetches users from Odoo API
   * 2. Logs users without work email addresses to the sync log channel
   * 3. Filters out users without email addresses
   * 4. Creates or updates local users based on Odoo data
   * 5. Identifies users that exist locally but not in Odoo
   * 6. Deletes local users that no longer exist in Odoo
   *
   * @return void
   *
   * @throws Exception
   */
  protected function execute(): void
  {
    // Step 1: Fetch all users from Odoo
    $allOdooUsers = $this->odoo->getUsers();

    // Step 2: Log users without work email
    $allOdooUsers->each(function ($odooUser) {
      if (empty($odooUser['work_email'])) {
        Log::channel('sync')->warning('Odoo user missing work email', [
          'odoo_id' => $odooUser['id'],
          'name' => $odooUser['name'],
        ]);
      }
    });

    // Step 3: Filter users to only include those with work email
    $odooUsers = $allOdooUsers->filter(function ($odooUser) {
      return filled($odooUser['work_email']);
    });

    // Step 4: Create or update local users based on Odoo data
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

    // Step 5: Identifies users that exist locally but not in Odoo
    $odooUserIds = $odooUsers->pluck('id');
    $localOdooIds = User::whereNotNull('odoo_id')->pluck('odoo_id');
    $usersToDelete = $localOdooIds->diff($odooUserIds);

    // Step 6: Deletes local users that no longer exist in Odoo individually to trigger model events
    User::whereIn('odoo_id', $usersToDelete)->get()->each->delete();
  }
}
