<?php

declare(strict_types=1);

namespace App\Jobs\Sync;

use App\Actions\CheckApisHealthAction;
use App\Clients\DesktimeApiClient;
use App\Clients\OdooApiClient;
use App\Clients\ProofhubApiClient;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Abstract base class for all sync jobs.
 *
 * Provides standardized logging, database transaction handling, error handling, and API health checks for all sync jobs.
 * Implements the Template Method pattern: the handle() method defines the job lifecycle, while child classes implement the execute() method for specific sync logic.
 *
 * Benefits:
 * - Ensures correct integration with Laravel's queue system for all sync jobs.
 * - Centralizes common functionality (logging, transactions, error handling, etc.).
 * - Allows child classes to focus solely on business logic.
 */
abstract class BaseSyncJob implements ShouldBeEncrypted, ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Odoo API client instance (optional, for jobs that sync Odoo data).
     */
    protected ?OdooApiClient $odoo = null;

    /**
     * DeskTime API client instance (optional, for jobs that sync DeskTime data).
     */
    protected ?DesktimeApiClient $desktime = null;

    /**
     * ProofHub API client instance (optional, for jobs that sync ProofHub data).
     */
    protected ?ProofhubApiClient $proofhub = null;

    /**
     * Maximum number of job attempts before failing.
     */
    public int $tries = 3;

    /**
     * Maximum number of exceptions allowed before failing.
     */
    public int $maxExceptions = 3;

    /**
     * Job timeout in seconds.
     */
    public int $timeout = 120;

    /**
     * Backoff times (in seconds) between retries.
     */
    public array $backoff = [10, 30, 60];

    /**
     * Main entry point for the job.
     *
     * Wraps the job's execution logic in a database transaction, logs the process, and handles errors.
     * Calls the abstract execute() method, which must be implemented by child classes.
     *
     * @throws Exception If the job fails during execution.
     */
    public function handle(): void
    {
        $jobName = class_basename($this);

        Log::info("{$jobName}: Starting database transaction.");

        try {
            // Wrap the execution logic in a database transaction
            DB::transaction(function (): void {
                $this->execute();
            });

            Log::info("{$jobName}: Database transaction committed successfully.");
        } catch (Throwable $e) {
            // Catch any throwable error/exception
            Log::error("{$jobName}: Database transaction rolled back due to error.", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw the exception to ensure Laravel's queue worker handles the failure
            throw $e;
        }
    }

    /**
     * The main sync logic, to be defined by child classes.
     *
     * Child classes must implement this method to provide their specific synchronization logic.
     *
     * @throws Exception If the sync logic fails.
     */
    abstract protected function execute(): void;

    /**
     * Called when all retries are exhausted and the job is marked as failed.
     *
     * Triggers API health checks and sends notifications if any API is down.
     *
     * @param  Throwable  $exception  The exception that caused the job to fail.
     */
    public function failed(Throwable $exception): void
    {
        // Use the new action to check API health and send notifications
        (new CheckApisHealthAction)->execute([
            'Odoo' => $this->odoo,
            'DeskTime' => $this->desktime,
            'ProofHub' => $this->proofhub,
        ]);
    }
}
