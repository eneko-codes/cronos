<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use App\Enums\Platform;
use App\Models\User;
use App\Models\UserExternalIdentity;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Livewire component for managing platform identity links.
 *
 * Shows information about external platform connections used for sync:
 * - Platform name and connection status
 * - External ID and email (for sync matching purposes)
 *
 * Admins and maintenance users can create and delete manual links.
 */
class ManagePlatformEmails extends Component
{
    /**
     * The target user ID. If null, shows the current user's platform links.
     */
    #[Locked]
    public ?int $targetUserId = null;

    /**
     * The platform being edited (null when not editing).
     */
    public ?string $editingPlatform = null;

    /**
     * The external email for the link being created/edited.
     */
    #[Validate('required|email:rfc,filter|max:255', onUpdate: false)]
    public string $externalEmail = '';

    /**
     * The external ID for the link being created/edited.
     */
    #[Validate('nullable|string|max:255', onUpdate: false)]
    public string $externalId = '';

    /**
     * Mount the component, optionally for a specific user.
     */
    public function mount(?int $userId = null): void
    {
        $this->targetUserId = $userId;

        $targetUser = $this->targetUser;
        if ($targetUser) {
            // Uses UserExternalIdentityPolicy::viewAnyForUser
            $this->authorize('viewAnyForUser', [UserExternalIdentity::class, $targetUser]);
        }
    }

    /**
     * Get the target user (either specified user or current user).
     */
    #[Computed]
    public function targetUser(): ?User
    {
        if ($this->targetUserId) {
            return User::with('externalIdentities')->find($this->targetUserId);
        }

        return Auth::user()?->load('externalIdentities');
    }

    /**
     * Get all platform link information for the target user.
     *
     * @return array<string, array{
     *   platform: string,
     *   label: string,
     *   isConnected: bool,
     *   isManualLink: bool,
     *   externalId: string|null,
     *   externalEmail: string|null,
     *   identityId: int|null
     * }>
     */
    #[Computed]
    public function platformLinks(): array
    {
        $user = $this->targetUser;

        if (! $user) {
            return [];
        }

        $platforms = [];

        foreach (Platform::cases() as $platform) {
            /** @var UserExternalIdentity|null $identity */
            $identity = $user->externalIdentities->firstWhere('platform', $platform);

            $isManualLink = $identity !== null ? $identity->is_manual_link : false;
            $isConnected = $identity !== null && ! $isManualLink;

            $platforms[$platform->value] = [
                'platform' => $platform->value,
                'label' => $platform->label(),
                'isConnected' => $isConnected,
                'isManualLink' => $isManualLink,
                'externalId' => $identity?->external_id,
                'externalEmail' => $identity?->external_email,
                'identityId' => $identity?->id,
            ];
        }

        return $platforms;
    }

    /**
     * Start editing a platform link.
     */
    public function startEditing(string $platform, ?string $email = null, ?string $externalId = null): void
    {
        // Uses UserExternalIdentityPolicy::create
        $this->authorize('create', UserExternalIdentity::class);

        $this->editingPlatform = $platform;
        $this->externalEmail = $email ?? '';
        $this->externalId = $externalId ?? '';
        $this->resetValidation();
    }

    /**
     * Cancel editing.
     */
    public function cancelEditing(): void
    {
        $this->editingPlatform = null;
        $this->externalEmail = '';
        $this->externalId = '';
        $this->resetValidation();
    }

    /**
     * Save a manual platform link.
     */
    public function saveLink(): void
    {
        // Uses UserExternalIdentityPolicy::create
        $this->authorize('create', UserExternalIdentity::class);

        $user = $this->targetUser;
        if (! $user || ! $this->editingPlatform) {
            return;
        }

        // Validate input (validation rules are defined on properties with #[Validate])
        $this->validate();

        $platform = Platform::from($this->editingPlatform);

        // Check if a link already exists for this platform
        $existingIdentity = $user->externalIdentities()
            ->where('platform', $platform)
            ->first();

        if ($existingIdentity) {
            // Update existing manual link
            $existingIdentity->update([
                'external_email' => strtolower(trim($this->externalEmail)),
                'external_id' => trim($this->externalId) ?: null,
                'is_manual_link' => true,
            ]);

            $this->dispatch('add-toast', message: "Platform link for {$platform->label()} updated.", variant: 'success');
        } else {
            // Create new manual link
            UserExternalIdentity::create([
                'user_id' => $user->id,
                'platform' => $platform,
                'external_email' => strtolower(trim($this->externalEmail)),
                'external_id' => trim($this->externalId) ?: null,
                'is_manual_link' => true,
            ]);

            $this->dispatch('add-toast', message: "Platform link for {$platform->label()} created.", variant: 'success');
        }

        $this->cancelEditing();

        // Refresh computed properties
        unset($this->targetUser, $this->platformLinks);
    }

    /**
     * Delete a platform identity (admin/maintenance, for manual links).
     */
    public function deleteLink(int $identityId): void
    {
        $identity = UserExternalIdentity::find($identityId);

        if (! $identity) {
            return;
        }

        // Uses UserExternalIdentityPolicy::delete
        $this->authorize('delete', $identity);

        $platformLabel = $identity->platform->label();

        $identity->delete();

        $this->dispatch('add-toast', message: "Platform link for {$platformLabel} deleted.", variant: 'success');

        // Refresh computed properties
        unset($this->targetUser, $this->platformLinks);
    }

    public function render()
    {
        return view('livewire.settings.manage-platform-emails');
    }
}
