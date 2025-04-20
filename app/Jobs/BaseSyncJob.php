<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ApiDownWarning;
use App\Contracts\Pingable;
use App\Services\OdooApiCalls;
use App\Services\DesktimeApiCalls;
use App\Services\ProofhubApiCalls;
use Exception;
use Throwable;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\Setting;

/**
 * Class BaseSyncJob
 *
 * - The base class for all sync jobs
 * - Provides standardized logging, caching, and error handling
 *
 * This class implements the Template Method pattern where:
 * - The handle() method defines the skeleton of the algorithm (what Laravel calls)
 * - The execute() method is the specific step that varies between job implementations
 *
 * This separation provides several benefits:
 * 1. Framework Integration: handle() is what Laravel's queue system expects
 * 2. Centralized Control: handle() can be extended to add functionality to all sync jobs at once
 *    (like logging, transactions, timing, etc.) without modifying child classes
 * 3. Separation of Concerns: Child classes only need to implement business logic without
 *    worrying about queue integration details
 */
abstract class BaseSyncJob implements ShouldQueue, ShouldBeEncrypted
{
  use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  /**
   * We explicitly declare these properties so referencing them
   * (in checkApisHealth()) won't cause "Undefined property" errors.
   */
  protected ?OdooApiCalls $odoo = null;
  protected ?DesktimeApiCalls $desktime = null;
  protected ?ProofhubApiCalls $proofhub = null;

  /**
   * Max job tries.
   */
  public int $tries = 3;

  /**
   * Max exceptions allowed.
   */
  public int $maxExceptions = 3;

  /**
   * Timeout (seconds).
   */
  public int $timeout = 120;

  /**
   * Backoff times (seconds).
   */
  public array $backoff = [10, 30, 60];

  /**
   * Handle the job.
   *
   * This is the "template method" that Laravel's queue system calls.
   * By implementing this in the base class, we:
   * 1. Ensure correct integration with Laravel's queue system for all child jobs
   * 2. Provide a single point for adding common functionality to all sync jobs
   * 3. Allow future extensions (like logging, metrics, transactions) without modifying child classes
   *
   * In a future implementation, this method could be enhanced to add functionality
   * such as transaction support, logging, error handling, etc., providing these
   * features to all child classes automatically.
   */
  public function handle(): void
  {
    $jobName = class_basename($this);

    Log::info("{$jobName}: Starting database transaction.");

    try {
      // Wrap the execution logic in a database transaction
      DB::transaction(function () {
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
      // (e.g., moving to failed_jobs table, respecting retries)
      throw $e;
    }
  }

  /**
   * The main sync logic, to be defined by child classes.
   *
   * This abstract method must be implemented by all child classes to provide
   * their specific synchronization logic. By separating this from handle(),
   * child classes can focus solely on business logic without needing to
   * understand Laravel queue integration details.
   *
   * @throws Exception
   */
  abstract protected function execute(): void;

  /**
   * When all retries are exhausted.
   *
   * @param \Throwable $exception The exception that caused the job to fail
   */
  public function failed(Throwable $exception): void
  {
    // Check API health once the job is truly marked as "failed"
    $this->checkApisHealth();
  }

  /**
   * Checks the health of all relevant APIs (Odoo, DeskTime, ProofHub)
   * and sends a notification if any is down.
   */
  protected function checkApisHealth(): void
  {
    $apis = [
      'Odoo' => $this->odoo,
      'DeskTime' => $this->desktime,
      'ProofHub' => $this->proofhub,
    ];

    foreach ($apis as $serviceName => $service) {
      if ($service instanceof Pingable) {
        try {
          $pingResult = $service->ping();
          $isDown = !($pingResult['success'] ?? false);

          if ($isDown) {
            $errorMessage = $pingResult['message'] ?? 'API health check failed';
            // Admins should receive API down warnings unless globally disabled
            $adminUsers = User::where('is_admin', true)->get();

            // Check global setting before notifying
            if (
              (bool) Setting::getValue(
                'notification.api_down_warning_mail.enabled',
                true
              ) &&
              (bool) Setting::getValue('notifications.global_enabled', true)
            ) {
              $adminUsers->each(function ($admin) use (
                $serviceName,
                $errorMessage
              ) {
                // Individual mute check isn't strictly necessary for critical alerts like API down,
                // but could be added: if (!$admin->notificationPreferences->mute_all)
                $admin->notify(new ApiDownWarning($serviceName, $errorMessage));
              });
            }
          }
        } catch (Exception $e) {
          // Admins should receive API down warnings unless globally disabled
          $adminUsers = User::where('is_admin', true)->get();

          // Check global setting before notifying
          if (
            (bool) Setting::getValue(
              'notification.api_down_warning_mail.enabled',
              true
            ) &&
            (bool) Setting::getValue('notifications.global_enabled', true)
          ) {
            $adminUsers->each(function ($admin) use ($serviceName, $e) {
              // Individual mute check isn't strictly necessary here either.
              $admin->notify(
                new ApiDownWarning(
                  $serviceName,
                  "Health check failed: {$e->getMessage()}"
                )
              );
            });
          }
        }
      }
    }
  }
}
