<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\Jobs\Sync\BaseSyncJob;
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

    protected ProofhubApiClient $proofhub;

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
    public function handle(): void
    {
        $allUsers = $this->proofhub->getUsers();
        $allProofhubEmails = $this->processUserPage($allUsers);
        $this->clearObsoleteProofhubIds($allProofhubEmails->unique());
    }

    /**
     * Processes a collection of user DTOs from ProofHub.
     *
     * @param  Collection|ProofhubUserDTO[]  $usersPage  Collection of ProofhubUserDTOs from the API.
     * @return Collection Collection of email addresses found in this batch.
     */
    private function processUserPage(Collection $usersPage): Collection
    {
        $emailsOnPage = collect();

        $usersPage
            ->filter(fn (ProofhubUserDTO $user) => isset($user->email) && isset($user->id))
            ->each(function (ProofhubUserDTO $user) use ($emailsOnPage): void {
                $email = strtolower($user->email);
                $proofhubId = $user->id ? (int) $user->id : null;
                $emailsOnPage->push($email);

                // Update local user record
                User::where('email', $email)->update([
                    'proofhub_id' => $proofhubId,
                ]);
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

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the ProofHub API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        app(CheckProofhubHealthAction::class)($this->proofhub);
    }
}
