<?php

namespace App\Livewire;

use App\Models\Setting;
use App\Models\User;
use App\Models\UserNotificationPreference;
use Illuminate\Support\Facades\Auth;
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

  /**
   * Bound property for the 'Mute All' toggle.
   */
  public bool $muteAll = false;

  /**
   * Bound property for individual notification toggles.
   * Key: preference key (e.g., 'schedule_change'), Value: boolean state.
   */
  public array $individualPreferences = [];

  /**
   * Indicates if notifications are enabled globally.
   */
  public bool $isGloballyEnabled = true;

  // Define the available user-specific notification keys and their labels
  #[Computed]
  public function preferenceKeys(): array
  {
    // Base preferences
    $keys = [
      'schedule_change' => 'Schedule Changes',
      'weekly_user_report' => 'Weekly Personal Report',
      'leave_reminder' => 'Leave Reminders',
      // Add new keys/labels here
    ];

    // Initialize individualPreferences array with default false values
    // if it hasn't been populated yet by loadPreferences
    foreach (array_keys($keys) as $key) {
      if (!isset($this->individualPreferences[$key])) {
        $this->individualPreferences[$key] = false;
      }
    }
    return $keys;
  }

  public function mount(): void
  {
    $this->loadPreferences();
  }

  /**
   * Load the user's notification preferences from the database
   * and populate the component's public properties.
   * This does NOT store the model instance in a property.
   */
  public function loadPreferences(): void
  {
    // Fetch global setting FIRST
    $this->isGloballyEnabled = (bool) Setting::getValue(
      'notifications.global_enabled',
      true
    ); // Fetch global setting

    /** @var User|null $user */
    $user = Auth::user();
    if (!$user) {
      $this->muteAll = true; // Default to muted on error
      $this->individualPreferences = [];
      return;
    }

    $preferencesModel = $user->notificationPreferences;

    // Ensure preferences exist in the DB, create if not
    if (!$preferencesModel) {
      try {
        $preferencesModel = $user->notificationPreferences()->create();
        // Reload the model fresh from the DB after creation
        $preferencesModel = $preferencesModel->fresh();
      } catch (Exception $e) {
        $preferencesModel = null; // Ensure it's null if creation failed
      }

      if (!$preferencesModel) {
        $this->dispatch(
          'add-toast',
          message: 'Error loading notification preferences.',
          variant: 'error'
        );
        $this->muteAll = true; // Default to muted state on error
        $this->individualPreferences = [];
        return;
      }
    }

    // Populate public properties from the fetched model
    $this->muteAll = (bool) $preferencesModel->mute_all;
    $this->individualPreferences = []; // Reset before loading
    foreach (array_keys($this->preferenceKeys) as $key) {
      // Ensure the key exists on the model before accessing
      $this->individualPreferences[$key] = property_exists(
        $preferencesModel,
        $key
      )
        ? (bool) $preferencesModel->$key
        : false; // Default to false if key somehow doesn't exist on model
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
   * Lifecycle hook that runs when the 'muteAll' public property is updated.
   *
   * @param bool $value The new value of the property.
   */
  public function updatedMuteAll(bool $value): void
  {
    // Check if globally disabled
    if (!$this->isGloballyEnabled) {
      $this->dispatch(
        'add-toast',
        message: 'Notifications are globally disabled by an administrator.',
        variant: 'warning'
      );
      $this->loadPreferences(); // Revert UI
      return; // Prevent saving
    }

    /** @var User|null $user */
    $user = Auth::user();
    if (!$user) {
      $this->dispatch(
        'add-toast',
        message: 'Error saving preference (not authenticated).',
        variant: 'error'
      );
      return;
    }

    // Fetch the model *within* the update method
    $preferencesModel = $user->notificationPreferences;

    if (!$preferencesModel) {
      // This case should ideally not happen if loadPreferences worked,
      // but handle it defensively.
      $this->dispatch(
        'add-toast',
        message: 'Error saving preference (model not found).',
        variant: 'error'
      );
      // Revert public property by reloading
      $this->loadPreferences();
      return;
    }

    try {
      // Update the model attribute
      $preferencesModel->mute_all = $value;
      $preferencesModel->save();

      // Public property $this->muteAll is already updated by Livewire binding

      $this->dispatch(
        'add-toast',
        message: $value
          ? 'All personal notifications muted.'
          : 'Personal notifications enabled.',
        variant: 'success'
      );
    } catch (Exception $e) {
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
   * Lifecycle hook that runs when a value in the 'individualPreferences' array is updated.
   *
   * @param bool $value The new value of the specific preference.
   * @param string $key The specific key within 'individualPreferences' that was updated (e.g., 'schedule_change').
   */
  public function updatedIndividualPreferences(bool $value, string $key): void
  {
    // Check if globally disabled
    if (!$this->isGloballyEnabled) {
      $this->dispatch(
        'add-toast',
        message: 'Notifications are globally disabled by an administrator.',
        variant: 'warning'
      );
      $this->loadPreferences(); // Revert UI
      return; // Prevent saving
    }

    /** @var User|null $user */
    $user = Auth::user();
    if (!$user) {
      $this->dispatch(
        'add-toast',
        message: 'Error saving preference (not authenticated).',
        variant: 'error'
      );
      return;
    }

    // Fetch the model *within* the update method
    $preferencesModel = $user->notificationPreferences;

    if (!$preferencesModel) {
      $this->dispatch(
        'add-toast',
        message: 'Error saving preference (model not found).',
        variant: 'error'
      );
      $this->loadPreferences(); // Revert public property
      return;
    }

    // Check if the updated key is valid according to our computed property
    if (!array_key_exists($key, $this->preferenceKeys)) {
      $this->dispatch(
        'add-toast',
        message: 'Invalid preference key.',
        variant: 'warning'
      );
      // Revert the specific key in the public property by reloading all preferences
      $this->loadPreferences();
      return;
    }

    try {
      // Update the corresponding model attribute
      $preferencesModel->{$key} = $value;
      $preferencesModel->save();

      // Public property $this->individualPreferences[$key] is already updated by Livewire binding

      $this->dispatch(
        'add-toast',
        message: $this->preferenceKeys[$key] . ' preference updated.',
        variant: 'success'
      );
    } catch (Exception $e) {
      $this->dispatch(
        'add-toast',
        message: 'Failed to update ' .
          ($this->preferenceKeys[$key] ?? $key) .
          '.',
        variant: 'error'
      );
      // Reload to revert UI if save fails
      $this->loadPreferences();
    }
  }

  /**
   * Listen for global notification updates from Settings component.
   */
  #[On('global-notifications-updated')]
  public function handleGlobalNotificationUpdate(bool $enabled): void
  {
    if ($this->isGloballyEnabled !== $enabled) {
      $this->isGloballyEnabled = $enabled;
      // Reload preferences to ensure UI consistency,
      // especially if a user interacted while globally disabled.
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
    // Pass the preference keys for the view loop,
    // the view will use the public $muteAll, $individualPreferences, and $isGloballyEnabled properties
    return view('livewire.sidebar', [
      'preferenceKeys' => $this->preferenceKeys,
    ]);
  }
}
