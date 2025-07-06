<?php

declare(strict_types=1);

namespace App\Jobs\Sync\SystemPin;

use App\Jobs\Sync\BaseSyncJob;
use Illuminate\Support\Facades\Log;

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
        Log::info(class_basename(static::class).' Started', ['job' => class_basename(static::class)]);
        // Implement the synchronization logic here.
        Log::info(class_basename(static::class).' Finished', ['job' => class_basename(static::class)]);
    }
}
