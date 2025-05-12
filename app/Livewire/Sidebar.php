<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Actions\User\UpdateIndividualNotificationPreference;
use App\Actions\User\UpdateMuteAllPreference;
use App\Models\User;
use App\Services\ApplicationSettingsService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class Sidebar extends Component
{
    use WithPagination;

    /**
     * Whether the sidebar is currently visible.
     */
    public bool $isOpen = false;

    /**
     * The currently active tab: 'notifications' or 'settings'.
     */
    public string $activeTab = 'notifications';

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

    /**
     * The current filter for the notification list: 'all', 'unread', 'read'.
     */
    public string $notificationFilter = 'all';

    /**
     * Stores the global enabled state for each specific notification type.
     * Key: preference key, Value: boolean state.
     */
    public array $globalPreferenceStates = [];

    public function mount(): void
    {
        // Initialize individualPreferences with a base state.
        // loadPreferences will populate them with actual values.
        foreach (array_keys($this->preferenceKeys()) as $key) {
            // Default to false initially; loadPreferences will set the true state from DB or defaults for new users.
            $this->individualPreferences[$key] = false;
        }
        $this->loadPreferences();
        $this->dispatchUnreadCountChanged();
    }

    // Using #[On] attributes for listeners below

    // Define the available user-specific notification keys and their labels
    #[Computed]
    public function preferenceKeys(): array
    {
        // Defines the known preference keys and their display labels
        return [
            'schedule_change' => 'Schedule Changes',
            'weekly_user_report' => 'Weekly Personal Report',
            'leave_reminder' => 'Leave Reminders',
            'api_down_warning' => 'API Down Warnings',
            'admin_promotion_email' => 'Admin Promotion Email',
        ];
    }

    /**
     * Get the user's notifications, paginated.
     */
    #[Computed]
    public function notifications()
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            // Return an empty paginator instance if no user
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 10);
        }

        // Start building the query
        $query = $user->notifications();

        // Apply filter based on state
        match ($this->notificationFilter) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null, // 'all' or default doesn't need an extra condition
        };

        // Fetch notifications sorted by creation date, 15 per page
        return $query->paginate(15);
    }

    /**
     * Calculate counts for different notification filters.
     */
    #[Computed]
    public function notificationCounts(): array
    {
        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return ['all' => 0, 'unread' => 0, 'read' => 0];
        }

        $unreadCount = $user->unreadNotifications()->count();
        $readCount = $user->readNotifications()->count();
        $allCount = $unreadCount + $readCount; // Or $user->notifications()->count() if preferred

        return [
            'all' => $allCount,
            'unread' => $unreadCount,
            'read' => $readCount,
        ];
    }

    /**
     * Dispatch event to update the toggle button's indicator.
     */
    private function dispatchUnreadCountChanged(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        $count = $user ? $user->unreadNotifications()->count() : 0;
        // Dispatch globally so SidebarToggle can hear it
        $this->dispatch('unread-count-changed', count: $count);
    }

    /**
     * Load the user's notification preferences from the database
     * and populate the component's public properties.
     * This does NOT store the model instance in a property.
     */
    public function loadPreferences(): void
    {
        // Resolve service directly inside the method
        $settingsService = app(ApplicationSettingsService::class);

        $this->isGloballyEnabled = $settingsService->isGlobalNotificationsEnabled();

        // Fetch global states for specific notification types
        $this->globalPreferenceStates = [
            'schedule_change' => $settingsService->isNotificationTypeGloballyEnabled('schedule_change'),
            'weekly_user_report' => $settingsService->isNotificationTypeGloballyEnabled('weekly_user_report'),
            'leave_reminder' => $settingsService->isNotificationTypeGloballyEnabled('leave_reminder'),
            'api_down_warning' => $settingsService->isNotificationTypeGloballyEnabled('api_down_warning'),
            'admin_promotion_email' => $settingsService->isNotificationTypeGloballyEnabled('admin_promotion_email'),
        ];

        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            $this->muteAll = true;
            // Ensure individualPreferences are all false if no user
            foreach (array_keys($this->preferenceKeys()) as $key) {
                $this->individualPreferences[$key] = false;
            }

            return;
        }

        // Use firstOrNew to get existing or instantiate a new preferences model.
        // The first array argument are attributes to find by, the second is for values if creating.
        $preferencesModel = $user->notificationPreferences()->firstOrNew(
            ['user_id' => $user->id] // Attributes to find existing record by
        );

        /** @var \App\Models\UserNotificationPreference $preferencesModel */
        if (! $preferencesModel->exists) {
            // The model is new and doesn't exist in the database yet.
            // Set our application-defined defaults on the model.
            $preferencesModel->fill([ // Use fill for mass assignment
                'user_id' => $user->id,
                'mute_all' => false, // Default 'mute_all' to false
            ]);

            foreach (array_keys($this->preferenceKeys()) as $key) {
                // Default all individual, specific notification types to true (enabled) for new records
                $preferencesModel->{$key} = true;
            }

            try {
                $preferencesModel->save(); // Save the new record with these defaults.
            } catch (Exception $e) {
                $this->dispatch(
                    'add-toast',
                    message: 'Error initializing notification preferences: '.$e->getMessage(),
                    variant: 'error'
                );
                $this->muteAll = true; // Default to muted state on error
                foreach (array_keys($this->preferenceKeys()) as $key) {
                    $this->individualPreferences[$key] = false; // Fallback component state
                }

                return;
            }
        }
        // At this point, $preferencesModel is either an existing record from the DB
        // or a newly created and saved one. It should accurately reflect the current state.
        // Populate component properties from this model.

        $this->muteAll = (bool) $preferencesModel->mute_all;

        $loadedIndividualPreferences = [];
        foreach ($this->preferenceKeys() as $key => $label) {
            // Get the value from the model; Eloquent's $casts should handle boolean conversion.
            // If a preference key exists in our code but not as an attribute on the model
            // (e.g., new preference added, old user record), default it to true (enabled).
            $loadedIndividualPreferences[$key] = $preferencesModel->{$key} ?? true;
        }
        $this->individualPreferences = $loadedIndividualPreferences;
    }

    /**
     * Toggle the sidebar visibility
     */
    public function toggle()
    {
        $this->isOpen = ! $this->isOpen;
    }

    /**
     * Listen for the toggle-sidebar event and toggle the sidebar
     */
    #[On('toggle-sidebar')]
    public function toggleSidebar()
    {
        if (! $this->isOpen) {
            $this->loadPreferences(); // Reload preferences when opening
            // Refresh count when opening, in case it changed while closed
            $this->dispatchUnreadCountChanged();
        }
        $this->isOpen = ! $this->isOpen;
    }

    /**
     * Lifecycle hook that runs when the 'muteAll' public property is updated.
     *
     * @param  bool  $value  The new value of the property.
     */
    public function updatedMuteAll(bool $value): void
    {
        // Check if globally disabled
        if (! $this->isGloballyEnabled) {
            $this->dispatch(
                'add-toast',
                message: 'Notifications are globally disabled by an administrator.',
                variant: 'warning'
            );
            $this->loadPreferences(); // Revert UI

            return; // Prevent saving
        }

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if (! $currentUser) {
            $this->dispatch(
                'add-toast',
                message: 'Error saving preference (not authenticated).',
                variant: 'error'
            );
            $this->loadPreferences(); // Revert UI

            return;
        }

        try {
            app(UpdateMuteAllPreference::class)->execute($currentUser->id, $value);

            $this->dispatch(
                'add-toast',
                message: $value
                  ? 'All personal notifications muted.'
                  : 'Personal notifications enabled.',
                variant: 'success'
            );
            // $this->muteAll is already updated by Livewire
        } catch (Exception $e) {
            $this->dispatch(
                'add-toast',
                message: 'Failed to update preference: '.$e->getMessage(),
                variant: 'error'
            );
            // Reload to revert UI if save fails
            $this->loadPreferences();
        }
    }

    /**
     * Lifecycle hook that runs when a value in the 'individualPreferences' array is updated.
     *
     * @param  bool  $value  The new value of the specific preference.
     * @param  string  $key  The specific key within 'individualPreferences' that was updated (e.g., 'schedule_change').
     */
    public function updatedIndividualPreferences(bool $value, string $key): void
    {
        // Check if globally disabled by master switch
        if (! $this->isGloballyEnabled) {
            $this->dispatch(
                'add-toast',
                message: 'Notifications are globally disabled by an administrator.',
                variant: 'warning'
            );
            $this->loadPreferences(); // Revert UI

            return; // Prevent saving
        }

        // Check if this specific notification type is globally disabled
        if (isset($this->globalPreferenceStates[$key]) && $this->globalPreferenceStates[$key] === false) {
            $preferenceLabel = $this->preferenceKeys()[$key] ?? 'This notification type';
            $this->dispatch(
                'add-toast',
                message: "{$preferenceLabel} notifications are currently disabled by an administrator.",
                variant: 'warning'
            );
            $this->loadPreferences(); // Revert UI

            return; // Prevent saving
        }

        /** @var User|null $currentUser */
        $currentUser = Auth::user();
        if (! $currentUser) {
            $this->dispatch(
                'add-toast',
                message: 'Error saving preference (not authenticated).',
                variant: 'error'
            );
            $this->loadPreferences(); // Revert UI

            return;
        }

        // Validate the key against known preferenceKeys to prevent arbitrary updates
        if (! array_key_exists($key, $this->preferenceKeys())) {
            $this->dispatch(
                'add-toast',
                message: 'Invalid preference key.',
                variant: 'warning'
            );
            $this->loadPreferences(); // Revert UI

            return;
        }

        try {
            app(UpdateIndividualNotificationPreference::class)->execute($currentUser->id, $key, $value);

            $action = $value ? 'enabled' : 'disabled';
            $variant = $value ? 'success' : 'info'; // Or 'warning' if you prefer for disabled

            $this->dispatch(
                'add-toast',
                message: ($this->preferenceKeys()[$key] ?? $key)." {$action}.",
                variant: $variant
            );
            // $this->individualPreferences[$key] is already updated by Livewire
        } catch (Exception $e) {
            $this->dispatch(
                'add-toast',
                message: 'Failed to update '.
                  ($this->preferenceKeys()[$key] ?? $key).': '.$e->getMessage(),
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
        // Reload all preferences and global states from service to ensure UI consistency
        // This ensures $this->isGloballyEnabled and $this->globalPreferenceStates are fresh
        $this->loadPreferences();
    }

    /**
     * Change the active tab in the sidebar.
     */
    public function changeTab(string $tab): void
    {
        if (in_array($tab, ['notifications', 'settings'])) {
            $this->activeTab = $tab;
            $this->resetPage(); // Reset pagination when changing tabs
        }
    }

    /**
     * Mark a specific notification as read.
     * Observer handles the count update dispatch.
     */
    public function markAsRead(string $notificationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            $notification = $user->notifications()->find($notificationId);
            if ($notification && $notification->unread()) {
                $notification->markAsRead(); // Observer will handle dispatch
                $this->dispatch(
                    'add-toast',
                    message: 'Notification marked as read.',
                    variant: 'success'
                );
                unset($this->notifications); // Refresh computed property
            }
        }
    }

    /**
     * Mark all unread notifications as read.
     * Manual dispatch required as mass updates don't trigger observers.
     */
    public function markAllAsRead(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            $count = $user->unreadNotifications()->count();
            if ($count > 0) {
                $user->unreadNotifications()->update(['read_at' => now()]);
                $this->dispatch(
                    'add-toast',
                    message: 'All notifications marked as read.',
                    variant: 'success'
                );
                unset($this->notifications); // Refresh
                $this->dispatchUnreadCountChanged(); // Manually dispatch after mass update
            } else {
                $this->dispatch(
                    'add-toast',
                    message: 'No unread notifications.',
                    variant: 'info'
                );
            }
        }
    }

    /**
     * Delete a specific notification.
     * Observer handles the count update dispatch.
     */
    public function deleteNotification(string $notificationId): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            $notification = $user->notifications()->find($notificationId);
            if ($notification) {
                // Observer will handle dispatch based on original read_at state
                $notification->delete();
                $this->dispatch(
                    'add-toast',
                    message: 'Notification deleted.',
                    variant: 'success'
                );
                unset($this->notifications); // Refresh
            }
        }
    }

    /**
     * Delete all notifications for the user.
     * Manual dispatch required as mass deletes don't trigger observers.
     */
    public function deleteAllNotifications(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user) {
            $unreadCountBefore = $user->unreadNotifications()->count(); // Check before deleting
            $count = $user->notifications()->count();
            if ($count > 0) {
                $user->notifications()->delete();
                $this->dispatch(
                    'add-toast',
                    message: 'All notifications deleted.',
                    variant: 'success'
                );
                unset($this->notifications); // Refresh
                if ($unreadCountBefore > 0) { // Only dispatch if unread notifications were deleted
                    $this->dispatchUnreadCountChanged(); // Manually dispatch after mass delete
                }
            } else {
                $this->dispatch(
                    'add-toast',
                    message: 'No notifications to delete.',
                    variant: 'info'
                );
            }
        }
    }

    /**
     * Set the filter for the notification list and reset pagination.
     */
    public function setNotificationFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'unread', 'read'])) {
            $this->notificationFilter = $filter;
            $this->resetPage(); // Reset pagination when filter changes
        }
    }

    /**
     * Open the notification details modal.
     */
    public function showNotificationDetails(string $notificationId): void
    {
        $this->dispatch('openNotificationDetailsModal', notificationId: $notificationId);
    }

    /**
     * Refresh the notification list and the count.
     * Triggered by external events (e.g., new notification via Pusher/Echo).
     */
    #[On('notification-updated')]
    public function refreshNotifications(): void
    {
        unset($this->notifications); // Unset computed property to force refresh
        $this->dispatchUnreadCountChanged(); // Refresh count as well
    }

    // Specific handlers for global preference updates, will also trigger loadPreferences
    // to refresh all global states from the service.
    #[On('schedule-change-global-setting-updated')]
    public function handleScheduleChangeGlobalUpdate(bool $enabled): void
    {
        // $this->globalPreferenceStates['schedule_change'] = $enabled; // Direct update
        $this->loadPreferences(); // Reload to get all states from service
    }

    #[On('weekly-user-report-global-setting-updated')]
    public function handleWeeklyUserReportGlobalUpdate(bool $enabled): void
    {
        // $this->globalPreferenceStates['weekly_user_report'] = $enabled;
        $this->loadPreferences();
    }

    #[On('leave-reminder-global-setting-updated')]
    public function handleLeaveReminderGlobalUpdate(bool $enabled): void
    {
        // $this->globalPreferenceStates['leave_reminder'] = $enabled;
        $this->loadPreferences();
    }

    #[On('api-down-warning-global-setting-updated')]
    public function handleApiDownWarningGlobalUpdate(bool $enabled): void
    {
        // $this->globalPreferenceStates['api_down_warning'] = $enabled;
        $this->loadPreferences();
    }

    #[On('admin-promotion-email-global-setting-updated')]
    public function handleAdminPromotionEmailGlobalUpdate(bool $enabled): void
    {
        $this->loadPreferences(); // Reload to get all states from service
    }

    /**
     * Render the component.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        // Pass the preference keys for the view loop,
        // the view will use the public $muteAll, $individualPreferences, $isGloballyEnabled
        // and the new $globalPreferenceStates properties
        return view('livewire.sidebar', [
            'preferenceKeys' => $this->preferenceKeys(),
            'globalPreferenceStates' => $this->globalPreferenceStates, // Pass global states to view
        ]);
    }

    /**
     * Render a skeleton placeholder while the sidebar component is loading.
     * This provides a visual indication that the notification preferences and user data are being fetched.
     *
     * @return \Illuminate\View\View
     */
    /*
    public function placeholder()
    {
        return view('livewire.placeholders.sidebar');
    }*/
}
