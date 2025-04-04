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
 * updating existing users with their ProofHub IDs and clearing
 * ProofHub IDs for users no longer in ProofHub.
 */
class SyncProofhubUsers extends BaseSyncJob
{
  /**
   * The priority of the job in the queue.
   * Lower numbers indicate higher priority.
   *
   * @var int
   */
  public int $priority = 1;

  /**
   * SyncProofhubUsers constructor.
   *
   * @param ProofhubApiCalls $proofhub An instance of the ProofhubApiCalls service.
   */
  public function __construct(ProofhubApiCalls $proofhub)
  {
    $this->proofhub = $proofhub;
  }

  /**
   * Executes the synchronization process.
   *
   * This method performs the following operations:
   * 1. Fetches users from ProofHub API
   * 2. Maps ProofHub user IDs to emails
   * 3. Updates local users with ProofHub IDs
   * 4. Clears ProofHub IDs for users no longer in ProofHub
   *
   * @return void
   *
   * @throws Exception If any part of the synchronization process fails
   */
  protected function execute(): void
  {
    // Step 1: Fetch users from ProofHub API and map to emails
    $emailToProofhubId = $this->getProofhubUserMap();

    // Step 2: Update local users with ProofHub IDs
    $this->updateUserProofhubIds($emailToProofhubId);

    // Step 3: Clear ProofHub IDs for users no longer in ProofHub
    $this->clearObsoleteProofhubIds($emailToProofhubId->keys());
  }

  /**
   * Creates a mapping of email addresses to ProofHub user IDs.
   *
   * @return Collection Collection with email as key and ProofHub ID as value
   */
  private function getProofhubUserMap(): Collection
  {
    return $this->proofhub
      ->getUsers()
      ->filter(fn($user) => isset($user['email']))
      ->map(
        fn($user) => [
          'email' => strtolower($user['email']),
          'proofhub_id' => (string) $user['id'],
        ]
      )
      ->pluck('proofhub_id', 'email');
  }

  /**
   * Updates local users with their ProofHub IDs.
   *
   * @param Collection $emailToProofhubId Mapping of emails to ProofHub IDs
   * @return void
   */
  private function updateUserProofhubIds(Collection $emailToProofhubId): void
  {
    $emailToProofhubId->each(function ($proofhubId, $email) {
      User::where('email', $email)->update(['proofhub_id' => $proofhubId]);
    });
  }

  /**
   * Clears ProofHub IDs for users no longer in ProofHub.
   *
   * @param Collection $currentProofhubEmails Emails of current ProofHub users
   * @return void
   */
  private function clearObsoleteProofhubIds(
    Collection $currentProofhubEmails
  ): void {
    User::whereNotIn('email', $currentProofhubEmails)
      ->whereNotNull('proofhub_id')
      ->update(['proofhub_id' => null]);
  }
}
