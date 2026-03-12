<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Proofhub;

use App\Actions\Proofhub\CheckProofhubHealthAction;
use App\Actions\Proofhub\ProcessProofhubTimeEntryAction;
use App\Clients\ProofhubApiClient;
use App\DataTransferObjects\Proofhub\ProofhubTimeEntryDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\TimeEntry;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize ProofHub time entries with the local time_entries table.
 *
 * This job fetches all time entries within a specified date range from ProofHub
 * and processes each one to ensure the local database is up-to-date.
 *
 * Responsibilities:
 * - Fetch all time entries from ProofHub for the specified date range
 * - Create or update local time entries
 * - Delete time entries no longer present in ProofHub within the synced date range
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
     * Fetches time entries from ProofHub and processes each one, then deletes
     * entries that are no longer present in ProofHub within the synced date range.
     */
    public function handle(): void
    {
        $fromDate = $this->startDate ?: now()->format('Y-m-d');
        $toDate = $this->endDate ?: now()->format('Y-m-d');

        $params = [
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ];

        $timeEntries = $this->proofhub->getTimeEntries($params);

        // Extract ProofHub IDs before processing
        $apiIds = $timeEntries->pluck('id')->filter();

        // Process each time entry DTO
        $timeEntries->each(function (ProofhubTimeEntryDTO $entry): void {
            (new ProcessProofhubTimeEntryAction)->execute($entry);
        });

        // Cleanup: delete time entries no longer in ProofHub within the synced date range
        $this->cleanupMissingTimeEntries($apiIds, $fromDate, $toDate);
    }

    /**
     * Delete time entries that are no longer present in the ProofHub API response within the synced date range.
     *
     * Only deletes entries within the synced date range to avoid affecting
     * entries outside the sync window.
     *
     * @param  Collection  $apiIds  Collection of ProofHub time entry IDs from the API response.
     * @param  string  $fromDate  The start date of the sync range (Y-m-d).
     * @param  string  $toDate  The end date of the sync range (Y-m-d).
     */
    private function cleanupMissingTimeEntries(Collection $apiIds, string $fromDate, string $toDate): void
    {
        $deletedCount = TimeEntry::betweenDates($fromDate, $toDate)
            ->whereNotIn('proofhub_time_entry_id', $apiIds)
            ->delete();

        if ($deletedCount > 0) {
            Log::debug('SyncProofhubTimeEntriesJob: Deleted time entries no longer in ProofHub', [
                'deleted_count' => $deletedCount,
                'from_date' => $fromDate,
                'to_date' => $toDate,
            ]);
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
        $notificationService = app(NotificationService::class);
        $checkHealth = new CheckProofhubHealthAction($notificationService);
        $checkHealth($this->proofhub);
    }
}
