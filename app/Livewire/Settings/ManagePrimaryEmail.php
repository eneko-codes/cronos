<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Livewire component for managing a user's primary email address.
 *
 * Allows users to:
 * - View their current email and verification status
 * - Update their email address
 * - Resend verification email
 *
 * Can be used for the current user (sidebar settings) or by admins
 * to manage another user's email (user details modal).
 */
class ManagePrimaryEmail extends Component
{
    /**
     * The target user ID. If null, manages the current user's email.
     */
    #[Locked]
    public ?int $targetUserId = null;

    /**
     * Whether the email editing form is shown.
     */
    public bool $isEditing = false;

    /**
     * The email being entered.
     */
    #[Validate('required|email:rfc,filter|max:255')]
    public string $newEmail = '';

    /**
     * Mount the component, optionally for a specific user.
     */
    public function mount(?int $userId = null): void
    {
        $this->targetUserId = $userId;

        $targetUser = $this->targetUser;
        if ($targetUser) {
            // Uses UserPolicy::update
            $this->authorize('update', $targetUser);
        }
    }

    /**
     * Get the target user (either specified user or current user).
     */
    #[Computed]
    public function targetUser(): ?User
    {
        if ($this->targetUserId) {
            // Only accesses email and verification status, no relationships needed
            return User::find($this->targetUserId);
        }

        return Auth::user();
    }

    /**
     * Start editing the email.
     */
    public function startEditing(): void
    {
        $user = $this->targetUser;
        if (! $user) {
            return;
        }

        $this->authorize('update', $user);

        $this->isEditing = true;
        $this->newEmail = $user->email;
        $this->resetValidation();
    }

    /**
     * Cancel editing.
     */
    public function cancelEditing(): void
    {
        $this->isEditing = false;
        $this->newEmail = '';
        $this->resetValidation();
    }

    /**
     * Save the new email address.
     */
    public function saveEmail(): void
    {
        $user = $this->targetUser;
        if (! $user) {
            return;
        }

        $this->authorize('update', $user);

        // Custom validation to exclude current user's email from unique check
        $this->validate([
            'newEmail' => [
                'required',
                'email:rfc,filter',
                'max:255',
                'unique:users,email,'.$user->id,
            ],
        ]);

        $normalizedEmail = strtolower(trim($this->newEmail));

        // Check if email is actually changing
        if ($user->email === $normalizedEmail) {
            $this->dispatch('add-toast', message: 'Email unchanged.', variant: 'info');
            $this->cancelEditing();

            return;
        }

        // Update email and clear verification
        $user->update([
            'email' => $normalizedEmail,
            'email_verified_at' => null,
        ]);

        // Send verification email using Laravel native method
        $user->sendEmailVerificationNotification();

        $this->dispatch('add-toast', message: 'Email updated. Verification email sent to '.$normalizedEmail, variant: 'success');
        $this->cancelEditing();

        // Refresh computed properties
        unset($this->targetUser);
    }

    /**
     * Resend the verification email.
     */
    public function resendVerification(): void
    {
        $user = $this->targetUser;
        if (! $user) {
            return;
        }

        $this->authorize('update', $user);

        if ($user->hasVerifiedEmail()) {
            $this->dispatch('add-toast', message: 'Email is already verified.', variant: 'info');

            return;
        }

        $user->sendEmailVerificationNotification();

        $this->dispatch('add-toast', message: 'Verification email sent to '.$user->email, variant: 'success');
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.settings.manage-primary-email');
    }
}
