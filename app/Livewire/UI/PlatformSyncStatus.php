<?php

declare(strict_types=1);

namespace App\Livewire\UI;

use App\Enums\Platform;
use App\Models\User;
use App\Services\SyncStatusService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Livewire component for displaying per-platform sync status.
 *
 * Shows the sync status for each external platform (Odoo, DeskTime, ProofHub, SystemPin)
 * including success/failure indicators, last sync time, and error messages when applicable.
 *
 * Implements a global cooldown mechanism to prevent spam syncing by any user.
 */
#[Lazy]
class PlatformSyncStatus extends Component
{
    /**
     * Cooldown duration in seconds (2 minutes).
     */
    private const SYNC_COOLDOWN_SECONDS = 120;

    /**
     * Cache key for sync cooldown.
     */
    private const SYNC_COOLDOWN_CACHE_KEY = 'platform_sync_cooldown';

    /**
     * Array of platform sync statuses.
     *
     * @var array<string, array{status: string, last_job: string|null, synced_at: string|null, error_message: string|null, platform: Platform, relative_time: string}>
     */
    public array $platformStatuses = [];

    /**
     * Initialize the component by loading all platform statuses.
     */
    public function mount(): void
    {
        $this->loadStatuses();
    }

    /**
     * Load sync statuses for all platforms.
     *
     * Called on mount and via polling to keep the display updated.
     */
    public function loadStatuses(): void
    {
        $syncStatusService = app(SyncStatusService::class);
        $statuses = $syncStatusService->getAllStatuses();

        // Add relative time to each status
        foreach ($statuses as $key => $status) {
            $statuses[$key]['relative_time'] = $syncStatusService->getRelativeTime($status['platform']);
        }

        $this->platformStatuses = $statuses;
    }

    /**
     * Get the CSS classes for a status indicator dot.
     */
    public function getStatusDotClasses(string $status): string
    {
        return match ($status) {
            'success' => 'bg-green-500',
            'failed' => 'bg-red-500',
            'in_progress' => 'bg-blue-500 animate-pulse',
            default => 'bg-gray-400',
        };
    }

    /**
     * Get a human-readable label for a status.
     */
    public function getStatusLabel(string $status): string
    {
        return match ($status) {
            'success' => 'Synced',
            'failed' => 'Failed',
            'in_progress' => 'Syncing...',
            default => 'Not synced',
        };
    }

    /**
     * Check if sync is currently on cooldown.
     *
     * Returns true if the cooldown is active (sync was triggered within the last minute).
     */
    #[Computed]
    public function isSyncOnCooldown(): bool
    {
        return Cache::has(self::SYNC_COOLDOWN_CACHE_KEY);
    }

    /**
     * Get remaining cooldown time in seconds.
     *
     * Returns the number of seconds remaining until sync can be triggered again.
     */
    #[Computed]
    public function cooldownRemainingSeconds(): int
    {
        $cooldownUntil = Cache::get(self::SYNC_COOLDOWN_CACHE_KEY);

        if (! $cooldownUntil) {
            return 0;
        }

        $remaining = $cooldownUntil - now()->timestamp;

        return max(0, $remaining);
    }

    /**
     * Run the sync command to synchronize all platforms.
     *
     * Only administrators and maintenance users are authorized to trigger manual syncs.
     * Implements a global cooldown to prevent spam syncing.
     */
    public function runSync(): void
    {
        // Authorization check - only admins and maintenance users can trigger manual syncs
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user instanceof User || (! $user->isAdmin() && ! $user->isMaintenance())) {
            $this->dispatch('add-toast', message: 'You are not authorized to perform this action.', variant: 'error');

            return;
        }

        // Check cooldown - prevent spam syncing
        if ($this->isSyncOnCooldown) {
            $remainingSeconds = $this->cooldownRemainingSeconds;

            // Format message based on time remaining
            if ($remainingSeconds >= 60) {
                $minutes = floor($remainingSeconds / 60);
                $seconds = $remainingSeconds % 60;
                $timeMessage = $seconds > 0
                    ? "{$minutes} minute(s) and {$seconds} second(s)"
                    : "{$minutes} minute(s)";
            } else {
                $timeMessage = "{$remainingSeconds} second(s)";
            }

            $this->dispatch('add-toast', message: "Manual sync cooldown active. Please wait {$timeMessage} before syncing again.", variant: 'warning');

            return;
        }

        try {
            // Set cooldown timestamp
            Cache::put(
                self::SYNC_COOLDOWN_CACHE_KEY,
                now()->addSeconds(self::SYNC_COOLDOWN_SECONDS)->timestamp,
                self::SYNC_COOLDOWN_SECONDS
            );

            // Run the sync command
            \Illuminate\Support\Facades\Artisan::call('sync');

            // Dispatch success notification
            $this->dispatch('add-toast', message: 'Manual sync started successfully.', variant: 'success');

            // Reload statuses to reflect the new sync
            $this->loadStatuses();
        } catch (\Exception $e) {
            // Clear cooldown on failure so user can retry immediately
            Cache::forget(self::SYNC_COOLDOWN_CACHE_KEY);

            // Dispatch error notification
            $this->dispatch('add-toast', message: 'Failed to start sync: '.$e->getMessage(), variant: 'error');
        }
    }

    public function render()
    {
        return view('livewire.ui.platform-sync-status');
    }
}
