<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\ProofhubApiCalls;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

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
     */
    public int $priority = 1;

    /**
     * SyncProofhubUsers constructor.
     *
     * @param  ProofhubApiCalls  $proofhub  An instance of the ProofhubApiCalls service.
     */
    public function __construct(ProofhubApiCalls $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Executes the synchronization process page by page.
     *
     * This method performs the following operations:
     * 1. Loops through pages fetched from ProofHub API using callPage
     * 2. Processes users from each page, updating local records
     * 3. Collects all valid emails encountered during pagination
     * 4. Clears ProofHub IDs for local users whose emails were not found in ProofHub
     *
     *
     * @throws Exception If any part of the synchronization process fails
     */
    protected function execute(): void
    {
        $endpoint = 'people';
        $allProofhubEmails = collect(); // Keep track of all emails found in ProofHub
        $currentPage = 1;
        $totalPages = 1; // Initialize for fallback
        $nextPageUrl = null;

        $baseUrl = config('services.proofhub.company_url'); // Needed for initial URL
        if (! $baseUrl) {
            throw new Exception('ProofHub company URL not configured.');
        }
        $currentUrl = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}"; // Initial URL

        Log::info('Starting ProofHub user sync.');

        do {
            $urlToCall = $nextPageUrl ?: $currentUrl;
            $paramsToCall = [];
            // Only add 'page' param if using fallback URL construction
            if ($nextPageUrl === null) {
                $paramsToCall['page'] = $currentPage;
                $urlToCall = "https://{$baseUrl}.proofhub.com/api/v3/{$endpoint}"; // Ensure base URL for fallback
            }

            // Call API for the current page
            $pageResult = $this->proofhub->callPage(
                $urlToCall,
                $paramsToCall,
                $endpoint
            );
            $usersOnPage = $pageResult['data'];
            $nextPageUrl = $pageResult['nextPageUrl'];
            $totalPagesFromHeader = $pageResult['totalPages']; // Might be null if using Link header

            if (
                $usersOnPage->isEmpty() &&
                $currentPage > 1 &&
                $nextPageUrl === null
            ) {
                Log::info('No more users found on subsequent page, ending sync.', [
                    'page' => $currentPage,
                ]);
                break; // Exit loop if a subsequent page is empty
            }

            // Process users found on this page
            $emailsOnPage = $this->processUserPage($usersOnPage);
            $allProofhubEmails = $allProofhubEmails->merge($emailsOnPage);

            // --- Pagination Logic for Next Loop Iteration ---
            if ($nextPageUrl) {
                // Link header provided the next URL
                $currentPage = null; // Not needed when using Link header
                $totalPages = null;
            } else {
                // Using fallback pagination
                if ($currentPage === 1 && $totalPagesFromHeader !== null) {
                    $totalPages = $totalPagesFromHeader; // Set total pages from header on first fallback request
                }

                if ($currentPage < $totalPages) {
                    $currentPage++;
                } else {
                    // Reached the last page according to fallback or only one page
                    $currentPage = null; // Signal to stop the loop
                    Log::debug(
                        "Reached last page ({$totalPages}) via fallback for {$endpoint}."
                    );
                }
            }

            // Loop condition: continue if we have a next URL OR if using fallback and current page is valid
        } while ($nextPageUrl !== null || $currentPage !== null);

        // Step 3: Clear ProofHub IDs for users no longer present
        $this->clearObsoleteProofhubIds($allProofhubEmails->unique());

        Log::info('Finished ProofHub user sync.', [
            'total_emails_found' => $allProofhubEmails->count(),
            'unique_emails_found' => $allProofhubEmails->unique()->count(),
        ]);
    }

    /**
     * Processes a single page of user data.
     *
     * @param  Collection  $usersPage  Collection of users from one API page
     * @return Collection Collection of email addresses found on this page
     */
    private function processUserPage(Collection $usersPage): Collection
    {
        $emailsOnPage = collect();

        $usersPage
            ->filter(fn ($user) => isset($user['email']) && isset($user['id']))
            ->each(function ($user) use ($emailsOnPage) {
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
     * Clears ProofHub IDs for users no longer in ProofHub.
     *
     * @param  Collection  $currentProofhubEmails  All unique emails found in ProofHub
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
