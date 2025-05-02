<?php

declare(strict_types=1);

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

class SidebarToggle extends Component
{
    public int $unreadCount = 0;

    /**
     * Fetch initial count when component mounts.
     */
    public function mount(): void
    {
        $this->updateCount();
    }

    /**
     * Toggle the sidebar visibility.
     */
    public function toggleSidebar(): void
    {
        $this->dispatch('toggle-sidebar');
    }

    /**
     * Update the unread notification count.
     */
    #[On('unread-count-changed')]
    public function updateCount(?int $count = null): void
    {
        // If count is passed directly, use it. Otherwise, query the DB.
        if ($count !== null) {
            $this->unreadCount = $count;
        } else {
            /** @var \App\Models\User|null $user */
            $user = Auth::user();
            $this->unreadCount = $user ? $user->unreadNotifications()->count() : 0;
        }
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.sidebar-toggle');
    }
}
