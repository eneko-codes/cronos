<?php

declare(strict_types=1);

namespace App\Jobs\Sync\SystemPin;

use App\Actions\SystemPin\CheckSystemPinHealthAction;
use App\Clients\SystemPinApiClient;
use App\Jobs\Sync\BaseSyncJob;

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

    protected SystemPinApiClient $systempin;

    /**
     * SyncSystempinAttendances constructor.
     *
     * @return void
     */
    public function __construct(SystemPinApiClient $systempin)
    {
        $this->systempin = $systempin;
    }

    /**
     * Executes the synchronization process.
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
