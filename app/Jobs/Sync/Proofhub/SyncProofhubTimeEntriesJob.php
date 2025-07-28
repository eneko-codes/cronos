<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubTimeEntryAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubTimeEntryDTO;
use App\Jobs\Sync\BaseSyncJob;

/**
 * Job to synchronize ProofHub time entries with the local time_entries table.
 *
 * This job fetches all time entries within a specified date range from ProofHub
 * and processes each one to ensure the local database is up-to-date.
 */
class SyncProofhubTimeEntriesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     */
    public int $priority = 4;

    protected ProofhubApiClient $proofhub;

    private ?string $startDate;

    private ?string $endDate;

    /**
     * Constructs a new SyncProofhubTimeEntriesJob job.
     *
     * @param  ProofhubApiClient  $proofhub  The ProofHub API client.
     * @param  string|null  $startDate  The start date for the sync (Y-m-d). Defaults to today.
     * @param  string|null  $endDate  The end date for the sync (Y-m-d). Defaults to today.
     */
    public function __construct(
        ProofhubApiClient $proofhub,
        ?string $startDate = null,
        ?string $endDate = null
    ) {
        $this->proofhub = $proofhub;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches time entries from ProofHub and processes each one.
     */
    public function handle(): void
    {
        $params = [
            'from_date' => $this->startDate ?: now()->format('Y-m-d'),
            'to_date' => $this->endDate ?: now()->format('Y-m-d'),
        ];

        $timeEntries = $this->proofhub->getTimeEntries($params);

        $timeEntries->each(function (ProofhubTimeEntryDTO $entry): void {
            (new ProcessProofhubTimeEntryAction)->execute($entry);
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
