<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooLeavesAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooLeaveDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\UserLeave;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo leave data (hr.leave) with the local user_leaves table.
 *
 * This job fetches all leaves from Odoo for a given date range using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all leaves from Odoo for the specified date range
 * - Create or update local user leaves
 * - Delete leaves no longer present in Odoo within the synced date range
 */
class SyncOdooLeavesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    /**
     * Odoo API client instance used to fetch leave data.
     */
    protected OdooApiClient $odoo;

    /**
     * The start date (Y-m-d) for fetching leaves from Odoo.
     */
    protected string $fromDate;

    /**
     * The end date (Y-m-d) for fetching leaves from Odoo.
     */
    protected string $toDate;

    /**
     * Constructs a new SyncOdooLeavesJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching leaves.
     * @param  string  $fromDate  The start date for fetching leaves (Y-m-d).
     * @param  string  $toDate  The end date for fetching leaves (Y-m-d).
     */
    public function __construct(OdooApiClient $odoo, string $fromDate, string $toDate)
    {
        $this->odoo = $odoo;
        $this->fromDate = $fromDate;
        $this->toDate = $toDate;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches leaves from Odoo for the specified date range and processes each one,
     * then deletes leaves that are no longer present in Odoo within the synced range.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $leaves = $this->odoo->getLeaves($this->fromDate, $this->toDate);

        // Extract Odoo IDs before processing
        $apiIds = $leaves->pluck('id')->filter();

        // Process each leave DTO
        $leaves->each(function (OdooLeaveDTO $leaveDto): void {
            (new ProcessOdooLeavesAction)->execute($leaveDto);
        });

        // Cleanup: delete leaves no longer in Odoo within the synced date range
        $this->cleanupMissingLeaves($apiIds);
    }

    /**
     * Delete leaves that are no longer present in the Odoo API response within the synced date range.
     *
     * Only deletes leaves that overlap with the synced date range to avoid affecting
     * leaves outside the sync window.
     *
     * @param  Collection  $apiIds  Collection of Odoo leave IDs from the API response.
     */
    private function cleanupMissingLeaves(Collection $apiIds): void
    {
        // Delete leaves that:
        // 1. Have an odoo_leave_id (were synced from Odoo)
        // 2. Are not in the current API response
        // 3. Overlap with the synced date range
        $deletedCount = UserLeave::whereNotNull('odoo_leave_id')
            ->whereNotIn('odoo_leave_id', $apiIds)
            ->where('start_date', '<=', $this->toDate.' 23:59:59')
            ->where('end_date', '>=', $this->fromDate.' 00:00:00')
            ->delete();

        if ($deletedCount > 0) {
            Log::debug('SyncOdooLeavesJob: Deleted leaves no longer in Odoo', [
                'deleted_count' => $deletedCount,
                'from_date' => $this->fromDate,
                'to_date' => $this->toDate,
            ]);
        }
    }

    /**
     * Handle a job failure.
     *
     * This method is called automatically by Laravel if the job fails after all retry attempts.
     * It triggers a health check for the Odoo API and notifies admins if the API is down.
     */
    public function failed(): void
    {
        $notificationService = app(NotificationService::class);
        $checkHealth = new CheckOdooHealthAction($notificationService);
        $checkHealth($this->odoo);
    }
}
