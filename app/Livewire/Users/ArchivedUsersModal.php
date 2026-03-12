<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

class ArchivedUsersModal extends Component
{
    use WithPagination;

    /**
     * Modal open state.
     */
    public bool $isOpen = false;

    public int $itemsPerPage = 30;

    /**
     * Open the archived users modal.
     */
    #[On('openArchivedUsersModal')]
    public function openModal(): void
    {
        $this->isOpen = true;
        $this->resetPage();
    }

    /**
     * Close the modal.
     */
    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetPage();
    }

    /**
     * Reactivate an archived user.
     */
    public function reactivateUser(int $userId): void
    {
        $user = User::findOrFail($userId);
        $this->authorize('reactivateUser', $user);

        if (! $user->is_active) {
            $user->is_active = true;
            $user->manually_archived_at = null; // Clear manual archive flag
            $user->save();

            $this->dispatch('add-toast', message: $user->name.' has been reactivated successfully.', variant: 'success');
            $this->resetPage();
        }
    }

    public function render(): \Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        $users = User::query()
            ->inactive()
            ->whereNotNull('manually_archived_at')
            ->orderBy('manually_archived_at', 'desc')
            ->paginate($this->itemsPerPage);

        return view('livewire.users.archived-users-modal', [
            'users' => $users,
        ]);
    }
}
