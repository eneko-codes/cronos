<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooScheduleAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooScheduleDTO;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\Schedule;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo schedule data (resource.calendar) with the local schedules table.
 *
 * This job fetches all schedules from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all schedules from Odoo
 * - Create or update local schedules
 * - Deactivate schedules no longer present in Odoo
 */
class SyncOdooSchedulesJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 2;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooSchedulesJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching schedules.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches schedules from Odoo and processes each one, then deactivates
     * schedules no longer present in Odoo.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $schedules = $this->odoo->getSchedules();

        // Extract Odoo IDs before processing
        $apiIds = $schedules->pluck('id')->filter();

        // Process each schedule DTO
        $schedules->each(function (OdooScheduleDTO $schedulesDto): void {
            (new ProcessOdooScheduleAction)->execute($schedulesDto);
        });

        // Cleanup: deactivate schedules no longer in Odoo
        $this->cleanupMissingSchedules($apiIds);
    }

    /**
     * Deactivate schedules that are no longer present in the Odoo API response.
     *
     * @param  Collection  $apiIds  Collection of Odoo schedule IDs from the API response.
     */
    private function cleanupMissingSchedules(Collection $apiIds): void
    {
        $deactivatedCount = Schedule::where('active', true)
            ->whereNotIn('odoo_schedule_id', $apiIds)
            ->update(['active' => false]);

        if ($deactivatedCount > 0) {
            Log::debug('SyncOdooSchedulesJob: Deactivated schedules no longer in Odoo', [
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
