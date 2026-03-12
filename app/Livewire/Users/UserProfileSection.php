<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Livewire component for displaying user profile information.
 *
 * Displays basic user information including active status, department,
 * timezone, job title, and timestamps.
 */
class UserProfileSection extends Component
{
    /**
     * The user ID to display profile information for.
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
     * Get the user for this profile section.
     */
    #[Computed]
    public function user(): User
    {
        return User::with('department')->findOrFail($this->userId);
    }

    /**
     * Get the user's job title.
     */
    #[Computed]
    public function jobTitle(): string
    {
        return $this->user->job_title ?? '-';
    }

    /**
     * Get the user's department name.
     */
    #[Computed]
    public function departmentName(): string
    {
        return $this->user->department->name ?? '-';
    }

    /**
     * Get the user's timezone.
     */
    #[Computed]
    public function timezone(): string
    {
        return $this->user->timezone ?? '-';
    }

    /**
     * Check if the user is active.
     */
    #[Computed]
    public function isActive(): bool
    {
        return $this->user->is_active ?? false;
    }

    /**
     * Get the user's created at timestamp.
     */
    #[Computed]
    public function createdAt(): \Carbon\Carbon
    {
        return $this->user->created_at;
    }

    /**
     * Get the user's updated at timestamp.
     */
    #[Computed]
    public function updatedAt(): \Carbon\Carbon
    {
        return $this->user->updated_at;
    }

    /**
     * Get details for display (excluding timestamps which are handled separately).
     */
    #[Computed]
    public function details(): array
    {
        return [
            'Department' => $this->departmentName,
            'Timezone' => $this->timezone,
            'Job Title' => $this->jobTitle,
        ];
    }

    public function render()
    {
        return view('livewire.users.user-profile-section');
    }
}
