<?php

namespace App\Livewire;

use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Exception;

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

  // Holds the UserNotificationPreference model instance for the logged-in user
  public ?UserNotificationPreference $preferences = null;

  // Define the available user-specific notification keys and their labels
  #[Computed]
  public function preferenceKeys(): array
  {
    return [
      'schedule_change' => 'Schedule Changes',
      'weekly_user_report' => 'Weekly Personal Report',
      'leave_reminder' => 'Leave Reminders',
      // Add new keys/labels here
    ];
  }

  public function mount(): void
  {
    $this->loadPreferences();
  }

  /**
   * Load (or reload) the user's notification preferences.
   */
  public function loadPreferences(): void
  {
    /** @var User $user */
    $user = Auth::user();
    $this->preferences = $user->notificationPreferences; // Load the relationship

    // Ensure preferences exist
    if (!$this->preferences?->exists) {
      $this->preferences = $user->notificationPreferences()->create();
    }
  }

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
    if (!$this->isOpen) {
      $this->loadPreferences(); // Reload preferences when opening
    }
    $this->isOpen = !$this->isOpen;
  }

  /**
   * Lifecycle hook that runs when the 'preferences.mute_all' property is updated.
   *
   * @param bool $value The new value of the property.
   */
  public function updatedPreferencesMuteAll(bool $value): void
  {
    if (!$this->preferences) {
      return;
    }

    try {
      // The property is already updated by wire:model, just save it.
      $this->preferences->save();

      $this->dispatch(
        'add-toast',
        message: $value
          ? 'All personal notifications muted.'
          : 'Personal notifications enabled.',
        variant: 'success'
      );
    } catch (Exception $e) {
      Log::error('Failed to update mute_all preference', [
        'user_id' => Auth::id(),
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to update preference.',
        variant: 'error'
      );
      // Reload to revert UI if save fails
      $this->loadPreferences();
    }
  }

  /**
   * Lifecycle hook that runs when any property within 'preferences' object is updated.
   *
   * @param mixed $value The new value of the property.
   * @param string $key The specific key within 'preferences' that was updated.
   */
  public function updatedPreferences(mixed $value, string $key): void
  {
    // Ignore updates to 'mute_all' as it's handled by its specific hook
    if ($key === 'mute_all' || !$this->preferences) {
      return;
    }

    // Check if the updated key is a valid preference key
    if (!array_key_exists($key, $this->preferenceKeys)) {
      Log::warning('Attempted to update invalid preference key.', [
        'user_id' => Auth::id(),
        'key' => $key,
      ]);
      return; // Or handle as an error
    }

    try {
      // The property is already updated by wire:model, just save it.
      $this->preferences->save();

      // Optional: Add a toast for individual preference changes
      // $this->dispatch('add-toast', message: 'Preference updated.', variant: 'success');
    } catch (Exception $e) {
      Log::error('Failed to update notification preference', [
        'user_id' => Auth::id(),
        'key' => $key,
        'error' => $e->getMessage(),
      ]);
      $this->dispatch(
        'add-toast',
        message: 'Failed to update preference.',
        variant: 'error'
      );
      // Reload to revert UI if save fails
      $this->loadPreferences();
    }
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
