<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Abstract base class for all sync jobs.
 *
 * Provides API health checks for all sync jobs.
 * Implements the Template Method pattern: the handle() method defines the job lifecycle, while child classes implement the execute() method for specific sync logic.
 *
 * Benefits:
 * - Ensures correct integration with Laravel\'s queue system for all sync jobs.
 * - Allows child classes to focus solely on business logic.
 */
abstract class BaseSyncJob implements ShouldBeEncrypted, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum number of job attempts before failing.
     */
    public int $tries = 2;

    /**
     * Maximum number of exceptions allowed before failing.
     */
    public int $maxExceptions = 1;

    /**
     * Job timeout in seconds.
     */
    public int $timeout = 120;

    /**
     * Backoff times (in seconds) between retries.
     */
    public array $backoff = [10, 30];
}
