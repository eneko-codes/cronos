<?php

namespace App\Livewire\Alerts;

use App\Models\Alert;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

class AlertsToggle extends Component
{
    /**
     * List of Livewire events to listen for.
     */
    protected $listeners = [
        'alerts-updated' => '$refresh',
    ];

    /**
     * Get the count of active alerts for the current user.
     *
     * @return int
     */
    #[Computed]
    public function alertCount(): int
    {
        // Sync with notifications first
        Alert::syncUnreadNotifications();
        
        return Alert::active()
            ->visibleTo(Auth::user())
            ->count();
    }

    /**
     * Toggle the alerts sidebar visibility.
     */
    public function toggleAlertsSidebar(): void
    {
        $this->dispatch('toggle-alerts-sidebar');
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.alerts.alerts-toggle');
    }
}
