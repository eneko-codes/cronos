<?php

/**
 * Sidebar (Livewire Component)
 *
 * This component manages the user sidebar container, including:
 * - Open/close state management
 * - Tab navigation (notifications, preferences)
 * - Passing authenticated user ID to child components
 *
 * All notification list logic is delegated to Sidebar\NotificationsList.
 * All preference logic is delegated to Settings\ManageNotificationPreferences.
 */

declare(strict_types=1);

namespace App\Livewire\UI;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Class Sidebar
 *
 * A minimal sidebar container component that orchestrates child components.
 *
 * @property bool $isOpen Whether the sidebar is currently visible
 * @property string $activeTab The currently active tab: 'notifications' or 'preferences'
 */
#[Lazy]
class Sidebar extends Component
{
    /**
     * Whether the sidebar is currently visible.
     */
    public bool $isOpen = false;

    /**
     * The currently active tab: 'notifications' or 'preferences'.
     */
    public string $activeTab = 'notifications';

    /**
     * The authenticated user ID (locked for security).
     */
    #[Locked]
    public ?int $userId = null;

    /**
     * Mount the component and verify authentication.
     */
    public function mount(): void
    {
        $user = Auth::user();
        if (! $user) {
            throw new \RuntimeException('Authenticated user not found.');
        }

        $this->userId = $user->id;
    }

    /**
     * Toggle the sidebar open/closed.
     */
    #[On('toggle-sidebar')]
    public function toggleSidebar(): void
    {
        // Ensure we have a user ID if the component was lazy loaded
        if (! $this->userId) {
            $user = Auth::user();
            if (! $user) {
                throw new \RuntimeException('Authenticated user not found.');
            }
            $this->userId = $user->id;
        }

        $this->isOpen = ! $this->isOpen;
    }

    /**
     * Change the active tab in the sidebar.
     */
    public function changeTab(string $tab): void
    {
        if (in_array($tab, ['notifications', 'preferences'])) {
            $this->activeTab = $tab;
        }
    }

    /**
     * Close the sidebar.
     */
    public function closeSidebar(): void
    {
        $this->isOpen = false;
    }

    /**
     * Render the sidebar view.
     */
    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.ui.sidebar');
    }
}
