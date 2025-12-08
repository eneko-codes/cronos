<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Enums\RoleType;
use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Livewire component for admin actions on users.
 *
 * Handles role management, tracking management, and notification management
 * for users. Only visible to admins.
 */
class UserAdminActions extends Component
{
    /**
     * The user ID to perform actions on.
     */
    #[Locked]
    public int $userId;

    /**
     * Get the user model (memoized for the request).
     */
    #[Computed]
    public function user(): User
    {
        return User::findOrFail($this->userId);
    }

    /**
     * User name for display purposes.
     */
    public string $name = '';

    /**
     * Whether the user is an admin.
     */
    public bool $isAdmin = false;

    /**
     * Whether the user is maintenance.
     */
    public bool $isMaintenance = false;

    /**
     * Whether the user is set to do not track.
     */
    public bool $isDoNotTrack = false;

    /**
     * Whether notifications are muted for the user.
     */
    public bool $isMuted = false;

    /**
     * Permission flags for actions.
     */
    public bool $canPromoteToAdmin = false;

    public bool $canDemoteAdmin = false;

    public bool $canPromoteToMaintenance = false;

    public bool $canDemoteFromMaintenance = false;

    public bool $canNotTrack = false;

    public bool $canEnableTracking = false;

    public bool $canMuteNotifications = false;

    public bool $canUnmuteNotifications = false;

    /**
     * Mount the component.
     */
    public function mount(int $userId): void
    {
        $this->userId = $userId;
        $this->loadUserDetails();
    }

    /**
     * Listen for user updates to refresh the component.
     */
    #[On('user-updated')]
    public function refreshUser(int $updatedUserId): void
    {
        if ($updatedUserId === $this->userId) {
            unset($this->user); // Clear memoized user
            $this->loadUserDetails();
        }
    }

    /**
     * Listen for user preference updates to refresh the component.
     */
    #[On('user-preferences-updated')]
    public function refreshUserPreferences(int $updatedUserId): void
    {
        if ($updatedUserId === $this->userId) {
            unset($this->user); // Clear memoized user
            $this->loadUserDetails();
        }
    }

    /**
     * Load user details and permissions.
     */
    public function loadUserDetails(): void
    {
        $user = $this->user;

        $this->name = $user->name;
        $this->isAdmin = $user->isAdmin();
        $this->isMaintenance = $user->isMaintenance();
        $this->isDoNotTrack = $user->do_not_track;
        $this->isMuted = (bool) $user->muted_notifications;

        // Check permissions for actions
        $this->canPromoteToAdmin = \Illuminate\Support\Facades\Gate::allows('promoteToAdmin', $user);
        $this->canDemoteAdmin = $user->isAdmin() && \Illuminate\Support\Facades\Gate::allows('demoteAdmin', $user);
        $this->canPromoteToMaintenance = \Illuminate\Support\Facades\Gate::allows('promoteToMaintenance', $user);
        $this->canDemoteFromMaintenance = $user->isMaintenance() && \Illuminate\Support\Facades\Gate::allows('demoteFromMaintenance', $user);
        $this->canNotTrack = ! $user->do_not_track && \Illuminate\Support\Facades\Gate::allows('disableTracking', $user);
        $this->canEnableTracking = $user->do_not_track && \Illuminate\Support\Facades\Gate::allows('enableTracking', $user);
        $this->canMuteNotifications = \Illuminate\Support\Facades\Gate::allows('muteNotifications', $user);
        $this->canUnmuteNotifications = \Illuminate\Support\Facades\Gate::allows('unmuteNotifications', $user);
    }

    /**
     * Promote user to admin.
     */
    public function promoteToAdmin(): void
    {
        $user = $this->user;
        $this->authorize('promoteToAdmin', $user);

        if (! $user->isAdmin()) {
            $user->user_type = RoleType::Admin;
            $user->save();
            $this->dispatch('user-updated', $user->id);
            $this->dispatch('add-toast', message: 'User promoted to admin.', variant: 'success');
            $this->loadUserDetails();
        }
    }

    /**
     * Demote user from admin.
     */
    public function demoteFromAdmin(): void
    {
        $user = $this->user;
        $this->authorize('demoteAdmin', $user);

        if ($user->isAdmin()) {
            $user->user_type = RoleType::User;
            $user->save();
            $this->dispatch('user-updated', $user->id);
            $this->dispatch('add-toast', message: 'User demoted to regular user.', variant: 'success');
            $this->loadUserDetails();
        }
    }

    /**
     * Promote user to maintenance.
     */
    public function promoteToMaintenance(): void
    {
        $user = $this->user;
        $this->authorize('promoteToMaintenance', $user);

        if (! $user->isMaintenance()) {
            $user->user_type = RoleType::Maintenance;
            $user->save();
            $this->dispatch('user-updated', $user->id);
            $this->dispatch('add-toast', message: 'User promoted to maintenance role.', variant: 'success');
            $this->loadUserDetails();
        }
    }

    /**
     * Demote user from maintenance.
     */
    public function demoteFromMaintenance(): void
    {
        $user = $this->user;
        $this->authorize('demoteFromMaintenance', $user);

        if ($user->isMaintenance()) {
            $user->user_type = RoleType::User;
            $user->save();
            $this->dispatch('user-updated', $user->id);
            $this->dispatch('add-toast', message: 'User removed from maintenance role.', variant: 'success');
            $this->loadUserDetails();
        }
    }

    /**
     * Set user to do not track.
     */
    public function doNotTrackUser(): void
    {
        $user = $this->user;
        $this->authorize('disableTracking', $user);

        if (! $user->do_not_track) {
            $user->do_not_track = true;
            $user->save();
            $this->loadUserDetails();
            $this->dispatch('add-toast', message: 'User '.$this->name.' added to the do not track list successfully.', variant: 'success');
        }
    }

    /**
     * Enable tracking for user.
     */
    public function enableTracking(): void
    {
        $user = $this->user;
        $this->authorize('enableTracking', $user);

        if ($user->do_not_track) {
            $user->do_not_track = false;
            $user->save();
            $this->loadUserDetails();
            $this->dispatch('add-toast', message: 'Tracking enabled for '.$this->name.' successfully.', variant: 'success');
        }
    }

    /**
     * Mute notifications for user.
     */
    public function muteNotifications(): void
    {
        $user = $this->user;
        $this->authorize('muteNotifications', $user);

        if (! $user->muted_notifications) {
            $user->update(['muted_notifications' => true]);
            $this->loadUserDetails();
            $this->dispatch('add-toast', message: 'Notifications muted for '.$this->name.' successfully.', variant: 'success');
            $this->dispatch('user-preferences-updated', $user->id);
        }
    }

    /**
     * Unmute notifications for user.
     */
    public function unmuteNotifications(): void
    {
        $user = $this->user;
        $this->authorize('unmuteNotifications', $user);

        if ($user->muted_notifications) {
            $user->update(['muted_notifications' => false]);
            $this->loadUserDetails();
            $this->dispatch('add-toast', message: 'Notifications enabled for '.$this->name.' successfully.', variant: 'success');
            $this->dispatch('user-preferences-updated', $user->id);
        }
    }

    public function render()
    {
        return view('livewire.users.user-admin-actions');
    }
}
