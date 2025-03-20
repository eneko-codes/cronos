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
    foreach ($emailToProofhubId as $email => $proofhubId) {
      User::where('email', $email)
        ->update(['proofhub_id' => $proofhubId]);
    }

    // Clear out any user who has a proofhub_id but isn't in ProofHub users
    User::whereNotIn('email', $emails)
      ->whereNotNull('proofhub_id')
      ->update(['proofhub_id' => null]);
  }
}
