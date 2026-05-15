<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Platform;
use App\Jobs\Sync\BaseSyncJob;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Service for tracking synchronization status per platform.
 *
 * Uses Laravel's Cache facade to store sync status information for each platform.
 * This enables real-time tracking of when syncs complete successfully or fail,
 * providing per-platform visibility into the sync health.
 *
 * Example usage:
 * ```php
 * $service = app(SyncStatusService::class);
 * $service->recordSuccess(Platform::Odoo, 'SyncOdooUsersJob');
 * $status = $service->getStatus(Platform::Odoo);
 * ```
 */
class SyncStatusService
{
    /**
     * Cache key prefix for sync status entries.
     */
    private const CACHE_KEY_PREFIX = 'sync_status';

    /**
     * Cache TTL in seconds (2 days - long enough to survive between syncs).
     */
    private const CACHE_TTL_SECONDS = 172800;

    /**
     * Record a successful sync for a platform.
     *
     * @param  Platform  $platform  The platform that was synced
     * @param  string  $jobName  The name of the sync job that completed
     */
    public function recordSuccess(Platform $platform, string $jobName): void
    {
        $this->updateStatus($platform, [
            'status' => 'success',
            'last_job' => $jobName,
            'synced_at' => now()->toIso8601String(),
            'error_message' => null,
        ]);
    }

    /**
     * Record a failed sync for a platform.
     *
     * @param  Platform  $platform  The platform that failed to sync
     * @param  string  $jobName  The name of the sync job that failed
     * @param  string|null  $errorMessage  Optional error message
     */
    public function recordFailure(Platform $platform, string $jobName, ?string $errorMessage = null): void
    {
        $this->updateStatus($platform, [
            'status' => 'failed',
            'last_job' => $jobName,
            'synced_at' => now()->toIso8601String(),
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Record that a sync is in progress for a platform.
     *
     * @param  Platform  $platform  The platform being synced
     * @param  string  $jobName  The name of the sync job that started
     */
    public function recordInProgress(Platform $platform, string $jobName): void
    {
        $this->updateStatus($platform, [
            'status' => 'in_progress',
            'last_job' => $jobName,
            'started_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get the sync status for a specific platform.
     *
     * @param  Platform  $platform  The platform to get status for
     * @return array{status: string, last_job: string|null, synced_at: string|null, error_message: string|null}
     */
    public function getStatus(Platform $platform): array
    {
        $cacheKey = $this->getCacheKey($platform);

        return Cache::get($cacheKey, [
            'status' => 'unknown',
            'last_job' => null,
            'synced_at' => null,
            'error_message' => null,
        ]);
    }

    /**
     * Get the sync status for all platforms.
     *
     * @return array<string, array{status: string, last_job: string|null, synced_at: string|null, error_message: string|null, platform: Platform}>
     */
    public function getAllStatuses(): array
    {
        $statuses = [];

        foreach (Platform::cases() as $platform) {
            $status = $this->getStatus($platform);
            $status['platform'] = $platform;
            $statuses[$platform->value] = $status;
        }

        return $statuses;
    }

    /**
     * Get formatted relative time for a platform's last sync.
     *
     * @param  Platform  $platform  The platform to get time for
     * @return string Human-readable relative time (e.g., "5m ago", "2h ago", "Never synced")
     */
    public function getRelativeTime(Platform $platform): string
    {
        $status = $this->getStatus($platform);

        if (empty($status['synced_at'])) {
            return 'Never synced';
        }

        return $this->formatRelativeTime(Carbon::parse($status['synced_at']));
    }

    /**
     * Determine the platform from a sync job class.
     *
     * @param  object|string  $job  The job instance or class name
     * @return Platform|null The platform enum, or null if not determinable
     */
    public static function getPlatformFromJob(object|string $job): ?Platform
    {
        $className = is_object($job) ? get_class($job) : $job;

        // Check if it extends BaseSyncJob
        if (is_object($job) && ! ($job instanceof BaseSyncJob)) {
            return null;
        }

        // Determine platform from namespace
        // e.g., App\Jobs\Sync\Odoo\SyncOdooUsersJob -> Odoo
        if (str_contains($className, '\\Odoo\\')) {
            return Platform::Odoo;
        }

        if (str_contains($className, '\\Desktime\\')) {
            return Platform::DeskTime;
        }

        if (str_contains($className, '\\Proofhub\\')) {
            return Platform::ProofHub;
        }

        if (str_contains($className, '\\SystemPin\\')) {
            return Platform::SystemPin;
        }

        return null;
    }

    /**
     * Get a short job name from the full class name.
     *
     * @param  object|string  $job  The job instance or class name
     * @return string The short job name (e.g., "SyncOdooUsersJob")
     */
    public static function getShortJobName(object|string $job): string
    {
        $className = is_object($job) ? get_class($job) : $job;

        return class_basename($className);
    }

    /**
     * Update the status cache for a platform.
     *
     * @param  Platform  $platform  The platform to update
     * @param  array<string, mixed>  $data  The status data to merge
     */
    private function updateStatus(Platform $platform, array $data): void
    {
        $cacheKey = $this->getCacheKey($platform);
        $currentStatus = Cache::get($cacheKey, []);

        Cache::put(
            $cacheKey,
            array_merge($currentStatus, $data),
            self::CACHE_TTL_SECONDS
        );
    }

    /**
     * Get the cache key for a platform's sync status.
     *
     * @param  Platform  $platform  The platform
     * @return string The cache key
     */
    private function getCacheKey(Platform $platform): string
    {
        return self::CACHE_KEY_PREFIX.':'.$platform->value;
    }

    /**
     * Format a Carbon date as a simplified relative time string.
     *
     * @param  Carbon  $date  The date to format
     * @return string Formatted relative time (e.g., "5m ago", "2h ago", "just now")
     */
    private function formatRelativeTime(Carbon $date): string
    {
        $diff = $date->diff(now());

        if ($diff->y > 0) {
            return $diff->y.'y ago';
        } elseif ($diff->m > 0) {
            return $diff->m.'mo ago';
        } elseif ($diff->d > 0) {
            return $diff->d.'d ago';
        } elseif ($diff->h > 0) {
            return $diff->h.'h ago';
        } elseif ($diff->i > 0) {
            return $diff->i.'m ago';
        } else {
            return 'just now';
        }
    }
}
