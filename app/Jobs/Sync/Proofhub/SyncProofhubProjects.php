<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubProjectAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubProjectDTO;
use App\Jobs\Sync\BaseSyncJob;

/**
 * Job to synchronize ProofHub projects with the local projects table.
 *
 * This job fetches all projects from ProofHub and processes each one to ensure
 * the local database reflects the current state of ProofHub.
 */
class SyncProofhubProjects extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     */
    public int $priority = 2;

    protected ProofhubApiClient $proofhub;

    /**
     * Constructs a new SyncProofhubProjects job.
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
     * Fetches projects from ProofHub and processes each one.
     */
    public function handle(): void
    {
        $projects = $this->proofhub->getProjects();

        $projects->each(function (ProofhubProjectDTO $project): void {
            (new ProcessProofhubProjectAction)->execute($project);
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
