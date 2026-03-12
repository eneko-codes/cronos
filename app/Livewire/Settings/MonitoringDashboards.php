<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use Laravel\Telescope\Contracts\EntriesRepository;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Livewire component for displaying monitoring dashboard links.
 *
 * Shows links to Pulse and Telescope dashboards when they are enabled.
 * Only visible to authenticated users with access to the settings page.
 */
class MonitoringDashboards extends Component
{
    /**
     * Check if Telescope is enabled.
     */
    #[Computed]
    public function telescopeEnabled(): bool
    {
        return app()->bound(EntriesRepository::class) && config('telescope.enabled');
    }

    /**
     * Check if Pulse is enabled.
     */
    #[Computed]
    public function pulseEnabled(): bool
    {
        return (bool) config('pulse.enabled', false);
    }

    /**
     * Check if any monitoring dashboard is available.
     */
    #[Computed]
    public function hasAnyDashboard(): bool
    {
        return $this->telescopeEnabled || $this->pulseEnabled;
    }

    public function render()
    {
        return view('livewire.settings.monitoring-dashboards');
    }
}
