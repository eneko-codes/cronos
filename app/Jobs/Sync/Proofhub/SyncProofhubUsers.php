<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubUserAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubUserDTO;
use App\Jobs\Sync\BaseSyncJob;

/**
 * Job to synchronize ProofHub users with the local users table.
 *
 * This job fetches all users from ProofHub and updates the corresponding local users
 * with their ProofHub ID. It does not create new users, only updates existing ones.
 */
class SyncProofhubUsers extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected ProofhubApiClient $proofhub;

    /**
     * Constructs a new SyncProofhubUsers job.
     *
     * @param  ProofhubApiClient  $proofhub  The ProofHub API client.
     */
    public function __construct(ProofhubApiClient $proofhub)
    {
        $this->proofhub = $proofhub;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches users from ProofHub and processes each one.
     */
    public function handle(): void
    {
        $users = $this->proofhub->getUsers();

        $users->each(function (ProofhubUserDTO $user): void {
            (new ProcessProofhubUserAction)->execute($user);
        });
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
