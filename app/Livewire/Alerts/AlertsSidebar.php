<?php

namespace App\Livewire\Alerts;

use App\Models\Alert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class AlertsSidebar extends Component
{
    /**
     * Whether the sidebar is currently visible.
     */
    public bool $isOpen = false;

    /**
     * The currently active tab: 'active' or 'resolved'
     */
    public string $activeTab = 'active';

    /**
     * Mount the component and perform initial setup.
     */
    public function mount()
    {
        // Sync unread notifications to alerts
        Alert::syncUnreadNotifications();
    }

    /**
     * Toggle the sidebar visibility
     */
    public function toggle()
    {
        $this->isOpen = !$this->isOpen;
    }

    /**
     * Switch between tabs
     */
    public function switchTab(string $tab)
    {
        if (in_array($tab, ['active', 'resolved'])) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Listen for the toggle-alerts-sidebar event and toggle the sidebar
     */
    #[On('toggle-alerts-sidebar')]
    public function toggleAlertsSidebar()
    {
        // Sync unread notifications to alerts whenever the sidebar is opened
        Alert::syncUnreadNotifications();
        $this->isOpen = !$this->isOpen;
    }

    /**
     * Computed property that returns all active alerts appropriate for the current user.
     * Returns a flat list of alerts without grouping by type.
     *
     * @return Collection
     */
    #[Computed]
    public function alerts(): Collection
    {
        // Sync with notifications
        Alert::syncUnreadNotifications();
        
        // Get active alerts visible to the current user
        $alerts = Alert::active()
            ->visibleTo(Auth::user())
            ->latest() // Most recent first
            ->get();
            
        // Make sure we have a collection to work with
        if (!$alerts instanceof Collection) {
            $alerts = collect($alerts);
        }
        
        return $alerts;
    }

    /**
     * Computed property that returns resolved alerts for the current user.
     *
     * @return Collection
     */
    #[Computed]
    public function resolvedAlerts(): Collection
    {
        // Get resolved alerts visible to the current user
        $alerts = Alert::where('resolved', true)
            ->visibleTo(Auth::user())
            ->latest() // Most recent first
            ->limit(50) // Limit to prevent overwhelming the UI
            ->get();
            
        // Make sure we have a collection to work with
        if (!$alerts instanceof Collection) {
            $alerts = collect($alerts);
        }
        
        return $alerts;
    }

    /**
     * Determine if there are any active alerts to display.
     *
     * @return bool
     */
    #[Computed]
    public function hasAlerts(): bool
    {
        // Sync with notifications
        Alert::syncUnreadNotifications();
        
        return Alert::active()
            ->visibleTo(Auth::user())
            ->exists();
    }

    /**
     * Determine if there are any resolved alerts to display.
     *
     * @return bool
     */
    #[Computed]
    public function hasResolvedAlerts(): bool
    {
        return Alert::where('resolved', true)
            ->visibleTo(Auth::user())
            ->exists();
    }

    /**
     * Get the total count of active alerts for the user.
     *
     * @return int
     */
    #[Computed]
    public function alertCount(): int
    {
        // Sync with notifications
        Alert::syncUnreadNotifications();
        
        return Alert::active()
            ->visibleTo(Auth::user())
            ->count();
    }

    /**
     * Get the total count of resolved alerts for the user.
     *
     * @return int
     */
    #[Computed]
    public function resolvedAlertCount(): int
    {
        return Alert::where('resolved', true)
            ->visibleTo(Auth::user())
            ->count();
    }

    /**
     * Resolve all alerts.
     */
    public function resolveAllAlerts()
    {
        // Get all matching alerts as models
        $alerts = Alert::active()
            ->visibleTo(Auth::user())
            ->get();
            
        // Handle each alert individually to avoid collection operations on models
        foreach ($alerts as $alert) {
            $alert->resolve(Auth::user());
        }
            
        // Refresh the component
        $this->dispatch('alerts-updated');
    }

    /**
     * Resolve a specific alert by ID.
     *
     * @param int $alertId
     */
    public function resolveAlert(int $alertId)
    {
        // Find the single alert model by ID
        $alert = Alert::find($alertId);
        
        if ($alert && Alert::visibleTo(Auth::user())->where('id', $alertId)->exists()) {
            $alert->resolve(Auth::user());
            
            // Refresh the component
            $this->dispatch('alerts-updated');
        }
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.alerts.alerts-sidebar');
    }
}
