<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

#[Lazy]
class UserDetailsModal extends Component
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
     * Open the user details modal for a specific user.
     */
    #[On('openUserDetailsModal')]
    public function openUserDetailsModal(int $userId): void
    {
        $this->userId = $userId;
        $this->isOpen = true;
    }

    /**
     * Get the user for the modal.
     */
    #[Computed]
    public function user(): User
    {
        return User::with(['externalIdentities', 'department'])->findOrFail($this->userId);
    }

    public function render(): \Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        return view('livewire.users.user-details-modal');
    }
}
