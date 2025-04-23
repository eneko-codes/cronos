<?php

namespace App\Livewire;

use Livewire\Component;

class SidebarToggle extends Component
{
    /**
     * Toggle the sidebar visibility.
     */
    public function toggleSidebar(): void
    {
        $this->dispatch('toggle-sidebar');
    }

    /**
     * Render the component.
     */
    public function render()
    {
        return view('livewire.sidebar-toggle');
    }
}
