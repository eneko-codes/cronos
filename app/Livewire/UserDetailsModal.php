<?php

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

  public $createdAt = ''; // User creation date

  public $updatedAt = ''; // User update date

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
    // Fetch user details
    $user = User::findOrFail($this->userId);

    // Prepare user details for display
    $this->prepareDetails($user);

    // Check permissions for actions
    $this->canPromoteToAdmin = Gate::allows('promoteToAdmin', $user);
    $this->canDemoteAdmin =
      $user->is_admin && Gate::allows('demoteAdmin', $user);
    $this->canNotTrack =
      !$user->do_not_track && Gate::allows('disableTracking', $user);
    $this->canEnableTracking =
      $user->do_not_track && Gate::allows('enableTracking', $user);
    $this->canMuteNotifications =
      !$user->muted_notifications && Gate::allows('muteNotifications', $user);
    $this->canUnmuteNotifications =
      $user->muted_notifications && Gate::allows('unmuteNotifications', $user);
  }

  protected function prepareDetails($user): void
  {
    // Retrieve the timezone from the session or default to 'Europe/Madrid'
    $timezone = session('timezone', 'Europe/Madrid');

    $this->name = $user->name ?? '-';
    $this->email = $user->email ?? '-';
    $this->odooId = $user->odoo_id ?? '-';
    $this->proofhubId = $user->proofhub_id ?? '-';
    $this->desktimeId = $user->desktime_id ?? '-';
    $this->systempinId = $user->systempin_id ?? '-';
    $this->createdAt = $user->created_at
      ? $user->created_at->setTimezone($timezone)->diffForHumans()
      : '-';
    $this->updatedAt = $user->updated_at
      ? $user->updated_at->setTimezone($timezone)->diffForHumans()
      : '-';
    $this->isAdmin = $user->is_admin;
    $this->isDoNotTrack = $user->do_not_track;
    $this->isMuted = $user->muted_notifications;

    $this->details = [
      'Email' => $this->email,
      'Timezone' => $user->timezone ?? 'Europe/Madrid',
      'Odoo ID' => $this->odooId,
      'Proofhub ID' => $this->proofhubId,
      'Desktime ID' => $this->desktimeId,
      'SystemPin ID' => $this->systempinId,
      'Created at' => $this->createdAt,
      'Updated at' => $this->updatedAt,
    ];
  }

  public function promoteToAdmin(): void
  {
    $user = User::findOrFail($this->userId);
    $this->authorize('promoteToAdmin', $user);

    if (!$user->is_admin) {
      $user->is_admin = true;
      $user->save();

      // Update local state and permissions
      $this->isAdmin = true;
      $this->canPromoteToAdmin = false;
      $this->canDemoteAdmin = Gate::allows('demoteAdmin', $user);

      $this->dispatch(
        'add-toast',
        message: 'User ' . $this->name . ' promoted to admin successfully.',
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

      // Update local state and permissions
      $this->isAdmin = false;
      $this->canDemoteAdmin = false;
      $this->canPromoteToAdmin = Gate::allows('promoteToAdmin', $user);

      $this->dispatch(
        'add-toast',
        message: 'Admin rights removed from ' . $this->name . ' successfully.',
        variant: 'success'
      );
    }
  }

  public function doNotTrackUser(): void
  {
    $user = User::findOrFail($this->userId);
    $this->authorize('disableTracking', $user);

    if (!$user->do_not_track) {
      $user->do_not_track = true;
      $user->save();

      // Update local state and permissions
      $this->isDoNotTrack = true;
      $this->canNotTrack = false;
      $this->canEnableTracking = Gate::allows('enableTracking', $user);

      $this->dispatch(
        'add-toast',
        message: 'User ' .
          $this->name .
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

      // Update local state and permissions
      $this->isDoNotTrack = false;
      $this->canEnableTracking = false;
      $this->canNotTrack = Gate::allows('disableTracking', $user);

      $this->dispatch(
        'add-toast',
        message: 'Tracking enabled for ' . $this->name . ' successfully.',
        variant: 'success'
      );
    }
  }

  public function muteNotifications(): void
  {
    $user = User::findOrFail($this->userId);
    $this->authorize('muteNotifications', $user);

    if (!$user->muted_notifications) {
      $user->muted_notifications = true;
      $user->save();

      // Update local state and permissions
      $this->isMuted = true;
      $this->canMuteNotifications = false;
      $this->canUnmuteNotifications = Gate::allows(
        'unmuteNotifications',
        $user
      );

      $this->dispatch(
        'add-toast',
        message: 'Notifications muted for ' . $this->name . ' successfully.',
        variant: 'success'
      );
    }
  }

  public function unmuteNotifications(): void
  {
    $user = User::findOrFail($this->userId);
    $this->authorize('unmuteNotifications', $user);

    if ($user->muted_notifications) {
      $user->muted_notifications = false;
      $user->save();

      // Update local state and permissions
      $this->isMuted = false;
      $this->canUnmuteNotifications = false;
      $this->canMuteNotifications = Gate::allows('muteNotifications', $user);

      $this->dispatch(
        'add-toast',
        message: 'Notifications enabled for ' . $this->name . ' successfully.',
        variant: 'success'
      );
    }
  }

  public function render(): \Illuminate\Foundation\Application|\Illuminate\Contracts\View\Factory|\Illuminate\Contracts\View\View|\Illuminate\View\View
  {
    return view('livewire.user-details-modal');
  }
}
