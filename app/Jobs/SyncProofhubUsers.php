<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\ProofhubApiCalls;
use Illuminate\Support\Collection;
use Exception;

/**
 * Class SyncProofhubUsers
 *
 * Synchronizes users from ProofHub into the local database,
 * and invalidates the entire cache store upon completion.
 */
class SyncProofhubUsers extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   *
   * @var int
   */
  public int $priority = 1;

  /**
   * Removed protected ProofhubApiCalls $proofhub;
   */
  public function __construct(ProofhubApiCalls $proofhub)
  {
    $this->proofhub = $proofhub;
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
    $proofhubUsers = $this->proofhub
      ->getUsers()
      ->filter(fn($user) => isset($user['email']))
      ->map(
        fn($user) => [
          'email' => strtolower($user['email']),
          'proofhub_id' => (string) $user['id'],
        ]
      );
    $emailToProofhubId = $proofhubUsers->pluck('proofhub_id', 'email');
    $emails = $emailToProofhubId->keys()->toArray();

    // Update existing users
    $existingUsers = User::whereIn('email', $emails)->get();
    foreach ($existingUsers as $user) {
      $proofhubId = $emailToProofhubId->get($user->email);
      if ($proofhubId) {
        $user->proofhub_id = $proofhubId;
        $user->save();
      }
    }

    // Clear out any user who has a proofhub_id but isn't in ProofHub users
    $proofhubUserEmails = $emailToProofhubId->keys()->toArray();
    $usersToClear = User::whereNotIn('email', $proofhubUserEmails)
      ->whereNotNull('proofhub_id')
      ->pluck('id')
      ->toArray();

    if (!empty($usersToClear)) {
      foreach ($usersToClear as $userId) {
        $user = User::find($userId);
        if ($user) {
          $user->proofhub_id = null;
          $user->save();
        }
      }
    }
  }
}
