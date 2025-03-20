<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\DesktimeApiCalls;
use Illuminate\Support\Facades\Log;

/**
 * Class SyncDesktimeUsers
 *
 * Synchronizes DeskTime user info into the local database,
 * then flushes the entire cache store once complete.
 */
class SyncDesktimeUsers extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 1;

  /**
   * Remove explicit protected DesktimeApiCalls $desktime;
   * using parent's property instead.
   */
  public function __construct(DesktimeApiCalls $desktime)
  {
    // Assign to parent's protected $desktime
    $this->desktime = $desktime;
  }

  /**
   * Main job logic: fetch, filter, update local user desksTime_id.
   *
   * @return void
   */
  protected function execute(): void
  {
    $employeesData = $this->desktime->getAllEmployees(null, 'month');

    // Merge users from all dates of the response JSON
    $desktimeUsers = $employeesData
      ->reduce(function ($allUsers, $dateUsers) {
        return $allUsers->merge($dateUsers);
      }, collect())
      ->unique('id'); // Remove duplicates

    // Filter & map
    $validUsers = $desktimeUsers
      ->filter(fn($user) => !empty($user['email']) && !empty($user['id']))
      ->map(
        fn($user) => [
          'email' => strtolower(trim($user['email'])),
          'desktime_id' => $user['id'],
        ]
      );

    // Update existing users with Desktime IDs
    foreach ($validUsers as $desktimeUser) {
      User::where('email', $desktimeUser['email'])
        ->update(['desktime_id' => $desktimeUser['desktime_id']]);
    }

    // Clear out any user who has a desktime_id but isn't in Desktime users
    $desktimeUserEmails = $validUsers->pluck('email')->toArray();
    User::whereNotIn('email', $desktimeUserEmails)
      ->whereNotNull('desktime_id')
      ->update(['desktime_id' => null]);
  }
}
