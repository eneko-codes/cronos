<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Clients\ProofhubApiClient;
use App\Models\User;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub user information with the local database.
 *
 * Updates local users with their ProofHub IDs and clears ProofHub IDs for users no longer present in ProofHub.
 */
class SyncProofhubUsers extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncProofhubUsers job instance.
     *
     * @param  ProofhubApiClient  $proofhub  The ProofHub API client.
     */
    public function __construct(ProofhubApiClient $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Main entry point for the job.
     *
     * Fetches users from ProofHub, updates local users with ProofHub IDs, and clears ProofHub IDs for users no longer present in ProofHub.
     * Logs the process and any errors encountered.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    protected function execute(): void
    {
        Log::info('Starting ProofHub user sync.');
        $allUsers = $this->proofhub->getUsers();
        $allProofhubEmails = $this->processUserPage($allUsers);
        $this->clearObsoleteProofhubIds($allProofhubEmails->unique());
        Log::info('Finished ProofHub user sync.', [
            'total_emails_found' => $allProofhubEmails->count(),
            'unique_emails_found' => $allProofhubEmails->unique()->count(),
        ]);
    }

    /**
     * Processes a collection of user data from ProofHub.
     *
     * @param  Collection  $usersPage  Collection of users from the API.
     * @return Collection Collection of email addresses found in this batch.
     */
    private function processUserPage(Collection $usersPage): Collection
    {
        $emailsOnPage = collect();

        $usersPage
            ->filter(fn ($user) => isset($user['email']) && isset($user['id']))
            ->each(function ($user) use ($emailsOnPage): void {
                $email = strtolower($user['email']);
                $proofhubId = (string) $user['id'];
                $emailsOnPage->push($email);

                // Update local user record
                User::where('email', $email)->update(['proofhub_id' => $proofhubId]);
            });

        Log::debug('Processed user page.', [
            'users_processed' => $usersPage->count(),
            'emails_found' => $emailsOnPage->count(),
        ]);

        return $emailsOnPage;
    }

    /**
     * Clears ProofHub IDs for users no longer present in ProofHub.
     *
     * @param  Collection  $currentProofhubEmails  All unique emails found in ProofHub.
     */
    private function clearObsoleteProofhubIds(
        Collection $currentProofhubEmails
    ): void {
        if ($currentProofhubEmails->isEmpty()) {
            Log::info(
                'No ProofHub emails found during sync, skipping obsolete ID cleanup.'
            );

            return;
        }

        $query = User::whereNotIn('email', $currentProofhubEmails)->whereNotNull(
            'proofhub_id'
        );

        $count = $query->count();

        if ($count > 0) {
            Log::info("Clearing ProofHub ID for {$count} obsolete users.");
            $query->update(['proofhub_id' => null]);
        } else {
            Log::info('No obsolete ProofHub user IDs to clear.');
        }
    }
}
