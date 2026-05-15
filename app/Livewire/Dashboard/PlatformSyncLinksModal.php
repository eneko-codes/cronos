<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard;

use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class PlatformSyncLinksModal extends Component
{
    /**
     * Modal open state.
     */
    public bool $isOpen = false;

    /**
     * The user ID for the modal.
     */
    #[Locked]
    public int $userId = 0;

    /**
     * Open the platform sync links modal for a specific user.
     */
    #[On('openPlatformSyncLinksModal')]
    public function openModal(int $userId): void
    {
        $this->userId = $userId;
        $this->isOpen = true;
    }

    /**
     * Close the modal.
     */
    public function closeModal(): void
    {
        $this->isOpen = false;
    }

    public function render()
    {
        return view('livewire.dashboard.platform-sync-links-modal');
    }
}
