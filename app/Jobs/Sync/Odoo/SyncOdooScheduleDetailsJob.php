<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooScheduleDetailAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDetailDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\ScheduleDetail;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo schedule detail data (resource.calendar.attendance) with the local schedule_details table.
 *
 * This job fetches all schedule details from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all schedule details from Odoo
 * - Create or update local schedule details
 * - Deactivate schedule details no longer present in Odoo
 */
class SyncOdooScheduleDetailsJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooScheduleDetailsJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching schedule details.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches schedule details from Odoo and processes each one, then deactivates
     * schedule details no longer present in Odoo.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $scheduleDetails = $this->odoo->getScheduleDetails();

        // Extract Odoo IDs before processing
        $apiIds = $scheduleDetails->pluck('id')->filter();

        // Process each schedule detail DTO
        $scheduleDetails->each(function (OdooScheduleDetailDTO $scheduleDetailDTO): void {
            (new ProcessOdooScheduleDetailAction)->execute($scheduleDetailDTO);
        });

        // Cleanup: deactivate schedule details no longer in Odoo
        $this->cleanupMissingScheduleDetails($apiIds);
    }

    /**
     * Deactivate schedule details that are no longer present in the Odoo API response.
     *
     * @param  Collection  $apiIds  Collection of Odoo schedule detail IDs from the API response.
     */
    private function cleanupMissingScheduleDetails(Collection $apiIds): void
    {
        $deactivatedCount = ScheduleDetail::active()
            ->whereNotIn('odoo_detail_id', $apiIds)
            ->update(['active' => false]);

        if ($deactivatedCount > 0) {
            Log::debug('SyncOdooScheduleDetailsJob: Deactivated schedule details no longer in Odoo', [
                'deactivated_count' => $deactivatedCount,
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
