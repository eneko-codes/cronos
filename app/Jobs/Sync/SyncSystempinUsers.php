<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

/**
 * Job to synchronize SystemPin user information with the local database.
 *
 * Updates local users with their SystemPin IDs and clears SystemPin IDs for users no longer present in SystemPin.
 */
class SyncSystempinUsers extends BaseSyncJob
{
    /**
     * The priority of the job in the queue. Lower numbers indicate higher priority.
     */
    public int $priority = 1;

    /**
     * Constructs a new SyncSystempinUsers job instance.
     */
    public function __construct()
    {
        // No dependencies required for this job.
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Synchronizes SystemPin users with the local database.
     */
    protected function execute(): void
    {
        // Implement the synchronization logic here.
    }
}
