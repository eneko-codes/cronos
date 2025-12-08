<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for displaying user timestamps (created_at, updated_at).
 *
 * Displays formatted timestamps in both human-readable format and full format.
 */
class UserTimestampsSection extends Component
{
    /**
     * The user ID to display timestamps for.
     */
    #[Locked]
    public int $userId;

    /**
     * Mount the component with a user ID.
     */
    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Get the user for this timestamps section.
     */
    #[Computed]
    public function user(): User
    {
        // No relationships needed for timestamps only
        return User::findOrFail($this->userId);
    }

    /**
     * Get the created_at timestamp in human-readable format.
     */
    #[Computed]
    public function createdAtDiff(): string
    {
        return $this->user->created_at ? $this->user->created_at->diffForHumans() : '-';
    }

    /**
     * Get the created_at timestamp in full format.
     */
    #[Computed]
    public function createdAtFormatted(): string
    {
        return $this->user->created_at ? $this->user->created_at->format('M d, Y H:i:s T') : '-';
    }

    /**
     * Get the updated_at timestamp in human-readable format.
     */
    #[Computed]
    public function updatedAtDiff(): string
    {
        return $this->user->updated_at ? $this->user->updated_at->diffForHumans() : '-';
    }

    /**
     * Get the updated_at timestamp in full format.
     */
    #[Computed]
    public function updatedAtFormatted(): string
    {
        return $this->user->updated_at ? $this->user->updated_at->format('M d, Y H:i:s T') : '-';
    }

    public function render()
    {
        return view('livewire.users.user-timestamps-section');
    }
}
