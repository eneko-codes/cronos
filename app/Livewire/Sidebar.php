<?php

namespace App\Livewire;

use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use App\Models\User;

class Sidebar extends Component
{
  /**
   * Whether the sidebar is currently visible.
   */
  public bool $isOpen = false;

  /**
   * The currently active tab: 'settings'
   */
  public string $activeTab = 'settings';

  /**
   * Toggle the sidebar visibility
   */
  public function toggle()
  {
    $this->isOpen = !$this->isOpen;
  }

  /**
   * Listen for the toggle-sidebar event and toggle the sidebar
   */
  #[On('toggle-sidebar')]
  public function toggleSidebar()
  {
    $this->isOpen = !$this->isOpen;
  }

  /**
   * Compute property to check if notifications are muted for the current user.
   *
   * @return bool
   */
  #[Computed]
  public function isNotificationsMuted(): bool
  {
    return Auth::user()->muted_notifications;
  }

  /**
   * Toggle the mute state of notifications for the current user.
   */
  public function toggleNotifications(): void
  {
    /** @var User $user */
    $user = Auth::user();
    $user->muted_notifications = !$user->muted_notifications;
    $user->save();

    // Show a toast notification
    $this->dispatch(
      'add-toast',
      message: $user->muted_notifications
        ? 'Notifications muted successfully.'
        : 'Notifications enabled successfully.',
      variant: 'success'
    );
  }

  /**
   * Render the component.
   *
   * @return \Illuminate\View\View
   */
  public function render()
  {
    return view('livewire.sidebar');
  }
}
