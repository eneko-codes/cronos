<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\NotificationSetting;
use App\Notifications\ApiDownWarning;
use App\Contracts\Pingable;
use App\Services\OdooApiCalls;
use App\Services\DesktimeApiCalls;
use App\Services\ProofhubApiCalls;
use Exception;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Class BaseSyncJob
 *
 * - The base class for all sync jobs
 * - Provides standardized logging, caching, and error handling
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
   */
  public function handle(): void
  {
    try {
      $this->execute();
    } catch (Exception $e) {
      throw $e; // rethrow so Laravel's retry/fail logic triggers
    }
  }

  /**
   * The main sync logic, to be defined by child classes.
   *
   * @throws Exception
   */
  abstract protected function execute(): void;

  /**
   * When all retries are exhausted.
   */
  public function failed(): void
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
    Log::debug('Starting API health check');

    if (!NotificationSetting::isEnabled('api_down_warning_mail')) {
      Log::debug('API down warning notifications are disabled');
      return;
    }

    $apis = [
      'Odoo' => $this->odoo,
      'DeskTime' => $this->desktime,
      'ProofHub' => $this->proofhub,
    ];

    foreach ($apis as $serviceName => $service) {
      Log::debug("Checking {$serviceName} API health");

      if ($service instanceof Pingable) {
        try {
          $pingResult = $service->ping();
          Log::debug("{$serviceName} ping result", $pingResult);

          $isDown = !($pingResult['success'] ?? false);

          if ($isDown) {
            $errorMessage = $pingResult['message'] ?? 'API health check failed';
            Log::error("{$serviceName} API is down", [
              'error' => $errorMessage,
              'job' => static::class,
            ]);

            $adminUsers = User::where('is_admin', true)
              ->where('muted_notifications', false)
              ->get();
            Log::debug("Found {$adminUsers->count()} admin users to notify");

            $adminUsers->each(function ($admin) use (
              $serviceName,
              $errorMessage
            ) {
              Log::debug("Sending notification to admin {$admin->email}");
              $admin->notify(new ApiDownWarning($serviceName, $errorMessage));
            });
          }
        } catch (Exception $e) {
          Log::error("{$serviceName} API health check failed", [
            'error' => $e->getMessage(),
            'job' => static::class,
          ]);

          $adminUsers = User::where('is_admin', true)
            ->where('muted_notifications', false)
            ->get();
          Log::debug(
            "Found {$adminUsers->count()} admin users to notify about exception"
          );

          $adminUsers->each(function ($admin) use ($serviceName, $e) {
            Log::debug(
              "Sending exception notification to admin {$admin->email}"
            );
            $admin->notify(
              new ApiDownWarning(
                $serviceName,
                "Health check failed: {$e->getMessage()}"
              )
            );
          });
        }
      } else {
        Log::debug(
          "{$serviceName} service does not implement Pingable interface"
        );
      }
    }
  }
}
