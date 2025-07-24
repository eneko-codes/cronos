<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubTaskAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubTaskDTO;
use App\Jobs\Sync\BaseSyncJob;

/**
 * Job to synchronize ProofHub tasks with the local tasks table.
 *
 * This job fetches all tasks from ProofHub and processes each one to ensure
 * the local database reflects the current state of ProofHub.
 */
class SyncProofhubTasks extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     */
    public int $priority = 3;

    protected ProofhubApiClient $proofhub;

    /**
     * Constructs a new SyncProofhubTasks job.
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
     * Fetches tasks from ProofHub and processes each one.
     */
    public function handle(): void
    {
        $tasks = $this->proofhub->getTasks();

        $tasks->each(function (ProofhubTaskDTO $task): void {
            (new ProcessProofhubTaskAction)->execute($task);
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
