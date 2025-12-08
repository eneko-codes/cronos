<?php

declare(strict_types=1);

namespace App\Livewire\UI;

use App\Enums\Platform;
use App\Services\SyncStatusService;
use Livewire\Attributes\Lazy;
use Livewire\Component;

/**
 * Livewire component for displaying per-platform sync status.
 *
 * Shows the sync status for each external platform (Odoo, DeskTime, ProofHub, SystemPin)
 * including success/failure indicators, last sync time, and error messages when applicable.
 */
#[Lazy]
class PlatformSyncStatus extends Component
{
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

    public function render()
    {
        return view('livewire.ui.platform-sync-status');
    }
}
