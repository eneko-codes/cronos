<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

/**
 * Class SyncSystempinAttendances
 *
 * Synchronizes Systempin attendances into the local database.
 */
class SyncSystempinAttendances extends BaseSyncJob
{
    /**
     * The priority of the job in the queue.
     */
    public int $priority = 2;

    /**
     * SyncSystempinAttendances constructor.
     *
     * @return void
     */
    public function __construct()
    {
        // Initialize any required properties or dependencies
    }

    /**
     * Executes the synchronization process.
     */
    protected function execute(): void
    {
        // Implement the synchronization logic here.
    }
}
