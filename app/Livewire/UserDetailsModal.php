<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class UserDetailsModal extends Component
{
    public $isOpen = false; // Modal open state

    #[Locked]
    public $userId = ''; // User ID

    public $name = ''; // User name

    public $email = ''; // User email

    public $odooId = ''; // User Odoo ID

    public $proofhubId = ''; // User Proofhub ID

    public $desktimeId = ''; // User Desktime ID

    public $systempinId = ''; // User SystemPin ID

    public $jobTitle = ''; // User Job Title

    public $managerName = ''; // User Manager Name

    public $subordinateNames = ''; // User Subordinates (comma-separated)

    public string $createdAtDiff = ''; // Add diff property

    public string $createdAtFormatted = ''; // Add formatted property

    public string $updatedAtDiff = ''; // Add diff property

    public string $updatedAtFormatted = ''; // Add formatted property

    public $isAdmin = false; // User admin status

    public $isDoNotTrack = false; // User do not track status

    public $details = []; // User details array

    public $canPromoteToAdmin = false; // Permission to promote user to admin

    public $canNotTrack = false; // Permission to set user as do not track

    public $canDemoteAdmin = false; // Permission to demote user from admin

    public $canEnableTracking = false; // Permission to enable tracking for user

    public $isMuted = false; // User muted notifications status

    public $canMuteNotifications = false; // Permission to mute user notifications

    public $canUnmuteNotifications = false; // Permission to unmute user notifications

    #[On('openUserDetailsModal')]
    public function openUserDetailsModal($userId): void
    {
        $this->userId = $userId; // Set user ID
        $this->isOpen = true; // Open modal
        $this->loadUserDetails(); // Fetch user details
    }

    public function loadUserDetails(): void
    {
        // Fetch user details with manager and subordinates relationships
        $user = User::with('manager', 'subordinates', 'notificationPreferences')->findOrFail($this->userId);

        // Prepare user details for display
        $this->prepareDetails($user);

        // Check permissions for actions
        $this->canPromoteToAdmin = Gate::allows('promoteToAdmin', $user);
        $this->canDemoteAdmin =
          $user->is_admin && Gate::allows('demoteAdmin', $user);
        $this->canNotTrack =
          ! $user->do_not_track && Gate::allows('disableTracking', $user);
        $this->canEnableTracking =
          $user->do_not_track && Gate::allows('enableTracking', $user);

        // Use the new preferences relationship and policies
        $this->isMuted = $user->notificationPreferences?->mute_all ?? false;
        $this->canMuteNotifications = Gate::allows('muteNotifications', $user);
        $this->canUnmuteNotifications = Gate::allows('unmuteNotifications', $user);
    }

    protected function prepareDetails($user): void
    {
        $this->name = $user->name ?? '-';
        $this->email = $user->email ?? '-';
        $this->odooId = $user->odoo_id ?? '-';
        $this->proofhubId = $user->proofhub_id ?? '-';
        $this->desktimeId = $user->desktime_id ?? '-';
        $this->systempinId = $user->systempin_id ?? '-';
        $this->jobTitle = $user->job_title ?? '-'; // Populate job title
        $this->managerName = $user->manager?->name ?? '-'; // Populate manager name
        // Populate subordinates names
        $this->subordinateNames = $user->subordinates->isNotEmpty() ? $user->subordinates->pluck('name')->implode(', ') : '-';

        // Prepare Created At dates
        $this->createdAtDiff = $user->created_at ? $user->created_at->diffForHumans() : '-';
        $this->createdAtFormatted = $user->created_at ? $user->created_at->format('M d, Y H:i:s T') : '-';

        // Prepare Updated At dates
        $this->updatedAtDiff = $user->updated_at ? $user->updated_at->diffForHumans() : '-';
        $this->updatedAtFormatted = $user->updated_at ? $user->updated_at->format('M d, Y H:i:s T') : '-';

        $this->isAdmin = $user->is_admin;
        $this->isDoNotTrack = $user->do_not_track;
        // Use the new preferences relationship
        $this->isMuted = $user->notificationPreferences?->mute_all ?? false;

        $this->details = [
            'Email' => $this->email,
            'Odoo ID' => $this->odooId,
            'Proofhub ID' => $this->proofhubId,
            'Desktime ID' => $this->desktimeId,
            'SystemPin ID' => $this->systempinId,
            'Job Title' => $this->jobTitle, // Add Job Title
            'Manager' => $this->managerName, // Add Manager Name
            'Subordinates' => $this->subordinateNames, // Add Subordinates
            // 'Created at' => $this->createdAt,
            // 'Updated at' => $this->updatedAt,
        ];
    }

    public function promoteToAdmin(): void
    {
        $user = User::findOrFail($this->userId);
        $this->authorize('promoteToAdmin', $user);

        if (! $user->is_admin) {
            $user->is_admin = true;
            $user->save();

            // Update local state and permissions by reloading all details
            $this->loadUserDetails();

            $this->dispatch(
                'add-toast',
                message: 'User '.$this->name.' promoted to admin successfully.',
                variant: 'success'
            );
        }
    }

    public function demoteFromAdmin(): void
    {
        $user = User::findOrFail($this->userId);
        $this->authorize('demoteAdmin', $user);

        if ($user->is_admin) {
            $user->is_admin = false;
            $user->save();

            // Update local state and permissions by reloading all details
            $this->loadUserDetails();

            $this->dispatch(
                'add-toast',
                message: 'Admin rights removed from '.$this->name.' successfully.',
                variant: 'success'
            );
        }
    }

    public function doNotTrackUser(): void
    {
        $user = User::findOrFail($this->userId);
        $this->authorize('disableTracking', $user);

        if (! $user->do_not_track) {
            $user->do_not_track = true;
            $user->save();

            // Update local state and permissions by reloading all details
            $this->loadUserDetails();

            $this->dispatch(
                'add-toast',
                message: 'User '.
                  $this->name.
                  ' added to the do not track list successfully.',
                variant: 'success'
            );
        }
    }

    public function enableTracking(): void
    {
        $user = User::findOrFail($this->userId);
        $this->authorize('enableTracking', $user);

        if ($user->do_not_track) {
            $user->do_not_track = false;
            $user->save();

            // Update local state and permissions by reloading all details
            $this->loadUserDetails();

            $this->dispatch(
                'add-toast',
                message: 'Tracking enabled for '.$this->name.' successfully.',
                variant: 'success'
            );
        }
    }

    public function muteNotifications(): void
    {
        $user = User::findOrFail($this->userId);
        $this->authorize('muteNotifications', $user);

        // Update the preference record
        $preferences = $user->notificationPreferences()->firstOrCreate(); // Ensure record exists
        if (! $preferences->mute_all) {
            $preferences->update(['mute_all' => true]);

            // Reload details and permissions to reflect the change immediately
            $this->loadUserDetails();

            $this->dispatch(
                'add-toast',
                message: 'Notifications muted for '.$this->name.' successfully.',
                variant: 'success'
            );
            // Dispatch event to potentially refresh user list/dashboard view
            $this->dispatch('user-preferences-updated', $user->id);
        }
    }

    public function unmuteNotifications(): void
    {
        $user = User::findOrFail($this->userId);
        $this->authorize('unmuteNotifications', $user);

        // Update the preference record
        $preferences = $user->notificationPreferences()->firstOrCreate(); // Ensure record exists
        if ($preferences->mute_all) {
            $preferences->update(['mute_all' => false]);

            // Reload details and permissions to reflect the change immediately
            $this->loadUserDetails();

            $this->dispatch(
                'add-toast',
                message: 'Notifications enabled for '.$this->name.' successfully.',
                variant: 'success'
            );
            // Dispatch event to potentially refresh user list/dashboard view
            $this->dispatch('user-preferences-updated', $user->id);
        }
    }

    public function render(): \Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
    {
        return view('livewire.user-details-modal');
    }
}
