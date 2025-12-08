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
 * Displays basic user information including name, email, verification status,
 * job title, and department.
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
     * Get the user's name.
     */
    #[Computed]
    public function name(): string
    {
        return $this->user->name;
    }

    /**
     * Get the user's email.
     */
    #[Computed]
    public function email(): string
    {
        return $this->user->email;
    }

    /**
     * Check if the user's email is verified.
     */
    #[Computed]
    public function isEmailVerified(): bool
    {
        return $this->user->hasVerifiedEmail();
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
     * Get details for display.
     */
    #[Computed]
    public function details(): array
    {
        return [
            'Job Title' => $this->jobTitle,
            'Department' => $this->departmentName,
        ];
    }

    public function render()
    {
        return view('livewire.users.user-profile-section');
    }
}
