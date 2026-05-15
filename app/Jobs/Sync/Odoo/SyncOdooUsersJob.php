<?php

declare(strict_types=1);

namespace App\Jobs\Sync\Odoo;

use App\Actions\Odoo\CheckOdooHealthAction;
use App\Actions\Odoo\ProcessOdooUserAction;
use App\Clients\OdooApiClient;
use App\DataTransferObjects\Odoo\OdooUserDTO;
use App\Enums\Platform;
use App\Jobs\Sync\BaseSyncJob;
use App\Models\User;
use App\Services\NotificationService;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Job to synchronize Odoo employee data (hr.employee) with the local users table.
 *
 * This job fetches all users from Odoo using the provided OdooApiClient
 * and processes each one to ensure the local database reflects the current state of Odoo.
 *
 * Responsibilities:
 * - Fetch all users from Odoo
 * - Create or update local users
 */
class SyncOdooUsersJob extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    protected OdooApiClient $odoo;

    /**
     * Constructs a new SyncOdooUsersJob instance.
     *
     * @param  OdooApiClient  $odoo  The Odoo API client to use for fetching users.
     */
    public function __construct(OdooApiClient $odoo)
    {
        $this->odoo = $odoo;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Fetches users from Odoo and processes each one.
     *
     * @throws Exception If any part of the synchronization process fails.
     */
    public function handle(): void
    {
        $users = $this->odoo->getUsers();

        // Collect Odoo IDs before processing (so we know which local users disappeared)
        $apiIds = $users->pluck('id')->filter()->map(fn ($id): string => (string) $id);

        // Process each user DTO
        $users->each(function (OdooUserDTO $employee): void {
            (new ProcessOdooUserAction)->execute($employee);
        });

        // Cleanup: deactivate Odoo-sourced users no longer in the API response.
        // Local-only users (no Odoo external identity) are NOT touched.
        $this->cleanupMissingUsers($apiIds);
    }

    /**
     * Mark users as inactive when their Odoo external identity is missing from the API response.
     *
     * @param  Collection<int, string>  $apiIds  Odoo IDs returned in this sync, as strings.
     */
    private function cleanupMissingUsers(Collection $apiIds): void
    {
        $deactivatedCount = User::query()
            ->where('is_active', true)
            ->whereHas('externalIdentities', function ($query) use ($apiIds): void {
                $query->where('platform', Platform::Odoo)
                    ->whereNotIn('external_id', $apiIds);
            })
            ->update(['is_active' => false]);

        if ($deactivatedCount > 0) {
            Log::debug('SyncOdooUsersJob: Deactivated users no longer in Odoo', [
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
