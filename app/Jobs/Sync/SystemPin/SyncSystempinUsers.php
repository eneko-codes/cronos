<?php

declare(strict_types=1);

namespace App\Jobs\Sync\SystemPin;

use App\Actions\SystemPin\CheckSystemPinHealthAction;
use App\Clients\SystemPinApiClient;
use App\Jobs\Sync\BaseSyncJob;

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

    protected SystemPinApiClient $systempin;

    /**
     * Constructs a new SyncSystempinUsers job instance.
     */
    public function __construct(SystemPinApiClient $systempin)
    {
        $this->systempin = $systempin;
    }

    /**
     * Main entry point for the job's sync logic.
     *
     * Synchronizes SystemPin users with the local database.
     */
    public function handle(): void
    {
        // Implement the synchronization logic here.
    }

    public function failed(): void
    {
        (new CheckSystemPinHealthAction)($this->systempin);
    }
}
