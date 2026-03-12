<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
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
        // UserObserver will automatically send verification email when email changes
        $user->update([
            'email' => $normalizedEmail,
            'email_verified_at' => null,
        ]);

        $this->dispatch('add-toast', message: "Email updated to {$normalizedEmail}. Verification email sent.", variant: 'success');

        $this->cancelEditing();

        // Refresh computed properties
        unset($this->targetUser);
    }

    /**
     * Resend the verification email.
     *
     * Sends a verification email to the user regardless of their current verification status.
     * Rate limited to 1 per minute per user to prevent spam.
     * The email is queued for asynchronous processing.
     *
     * Uses Laravel 12 native email verification system with MustVerifyEmail interface.
     */
    public function resendVerification(): void
    {
        $user = $this->targetUser;
        if (! $user) {
            $this->dispatch('add-toast', message: 'User not found.', variant: 'error');

            return;
        }

        $this->authorize('update', $user);

        // Check rate limit (1 per minute per user)
        $key = 'resend-verification:'.$user->id;
        if (RateLimiter::tooManyAttempts($key, 1)) {
            $seconds = RateLimiter::availableIn($key);
            $this->dispatch('add-toast', message: "Please wait {$seconds} second(s) before requesting another verification email.", variant: 'warning');

            return;
        }

        try {
            // Send verification email (always, even if already verified)
            $user->sendEmailVerificationNotification();

            // Hit the rate limiter (1 minute = 60 seconds)
            RateLimiter::hit($key, 60);

            $verificationStatus = $user->hasVerifiedEmail() ? ' (already verified)' : '';
            $this->dispatch('add-toast', message: "Verification email sent to {$user->email}{$verificationStatus}.", variant: 'success');
        } catch (\Exception $e) {
            // Log the error for debugging
            \Log::error('Failed to send verification email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);

            $this->dispatch('add-toast', message: 'Failed to send verification email. Please try again later.', variant: 'error');
        }
    }

    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.settings.manage-primary-email');
    }
}
