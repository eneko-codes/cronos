<?php

/**
 * Sidebar (Livewire Component)
 *
 * This component manages the user sidebar, including:
 * - User notification preferences (mute all, per-type)
 * - Notification list and actions (mark as read, delete, etc.)
 * - Syncs with global notification settings
 *
 * All business logic is delegated to the NotificationPreferenceService and enums.
 */

declare(strict_types=1);

namespace App\Livewire;

use App\Enums\NotificationType;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Class Sidebar
 *
 *
 * @property bool $isOpen Whether the sidebar is currently visible
 * @property string $activeTab The currently active tab: 'notifications' or 'settings'
 * @property string $notificationFilter The current filter for the notification list: 'all', 'unread', 'read'
 * @property bool $userNotificationsMuted Bound property for the 'Mute Notifications' toggle (user-level)
 * @property array $userNotificationStates Bound property for individual notification toggles (user-level)
 * @property bool $isGloballyEnabled Indicates if notifications are enabled globally (system-wide master switch)
 * @property array $globalPreferenceStates Stores the global enabled state for each specific notification type
 */
#[Lazy]
class Sidebar extends Component
{
    use WithPagination;

    /** @var bool Whether the sidebar is currently visible. */
    public bool $isOpen = false;

    /** @var string The currently active tab: 'notifications' or 'settings'. */
    public string $activeTab = 'notifications';

    /** @var string The current filter for the notification list: 'all', 'unread', 'read'. */
    public string $notificationFilter = 'all';

    /** @var bool Bound property for the 'Mute Notifications' toggle (user-level). */
    public bool $userNotificationsMuted = false;

    /** @var array Bound property for individual notification toggles (user-level). */
    public array $userNotificationStates = [];

    /** @var bool Indicates if notifications are enabled globally (system-wide master switch). */
    public bool $isGloballyEnabled = true;

    /** @var array Stores the global enabled state for each specific notification type. */
    public array $globalPreferenceStates = [];

    /**
     * The authenticated user ID.
     */
    #[Locked]
    public int $userId;

    /**
     * Mount the component and load user preferences.
     */
    public function mount(NotificationPreferenceService $notificationService): void
    {
        $this->authorize('accessPreferencesSidebar');
        $this->userId = Auth::id();
        $user = $this->user;
        $prefs = $notificationService->getFormattedUserPreferences($user, $user->id);
        $this->userNotificationsMuted = $prefs['user_notifications_muted'];
        $this->userNotificationStates = $prefs['user_notification_states'];
        $this->isGloballyEnabled = $prefs['global_notifications_enabled'];
        $this->globalPreferenceStates = $prefs['global_notification_type_states'];
        $this->dispatchUnreadCountChanged();
    }

    /**
     * Get the authenticated user model.
     */
    #[Computed]
    public function user(): User
    {
        return User::findOrFail($this->userId);
    }

    /**
     * Get the notification preference keys and their labels/admin status.
     */
    #[Computed]
    public function preferenceKeys(): array
    {
        $keys = [];
        foreach (NotificationType::cases() as $type) {
            // Skip non-admin toggles for non-admins
            if ($type->isAdminOnly() && ! $this->user->isAdmin()) {
                continue;
            }

            $isSpecificTypeGloballyOff = isset($this->globalPreferenceStates[$type->value]) && ! $this->globalPreferenceStates[$type->value];

            // Determine disabled state
            if (! $this->isGloballyEnabled) {
                $isDisabled = true;
            } elseif ($isSpecificTypeGloballyOff) {
                $isDisabled = true;
            } elseif ($this->userNotificationsMuted) {
                $isDisabled = true;
            } else {
                $isDisabled = false;
            }

            // Determine tooltip text
            $tooltipText = $isSpecificTypeGloballyOff
                ? 'This notification type is currently disabled by an administrator.'
                : 'Enable or disable '.strtolower($type->label()).' for your account.';

            $keys[$type->value] = [
                'label' => $type->label(),
                'isAdminOnly' => $type->isAdminOnly(),
                'isDisabled' => $isDisabled,
                'isSpecificTypeGloballyOff' => $isSpecificTypeGloballyOff,
                'tooltipText' => $tooltipText,
            ];
        }

        return $keys;
    }

    /**
     * Get the paginated notifications for the authenticated user, filtered by the current filter.
     */
    #[Computed]
    public function notifications(): \Illuminate\Pagination\LengthAwarePaginator
    {
        $query = $this->user->notifications();
        match ($this->notificationFilter) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => null,
        };

        return $query->paginate(15);
    }

    /**
     * Get the notification counts for the authenticated user.
     */
    #[Computed]
    public function notificationCounts(): array
    {
        $unreadCount = $this->user->unreadNotifications()->count();
        $readCount = $this->user->readNotifications()->count();
        $allCount = $unreadCount + $readCount;

        return [
            'all' => $allCount,
            'unread' => $unreadCount,
            'read' => $readCount,
        ];
    }

    /**
     * Handle updates to the 'Mute Notifications' toggle.
     */
    public function updatedUserNotificationsMuted(bool $value, NotificationPreferenceService $notificationService): void
    {
        try {
            $notificationService->toggleUserMuteAll($this->user, $this->user->id, $value);
            $this->dispatchToast($value ? 'All personal notifications muted.' : 'Personal notifications enabled.', 'success');
        } catch (\Exception $e) {
            $this->dispatchToast('Failed to update preference: '.$e->getMessage(), 'error');
        }
    }

    /**
     * Handle updates to individual notification preferences.
     */
    public function updatedUserNotificationStates(bool $value, string $key, NotificationPreferenceService $notificationService): void
    {
        try {
            $type = NotificationType::from($key);

            if (isset($this->globalPreferenceStates[$key]) && $this->globalPreferenceStates[$key] === false) {
                $this->dispatchToast(($this->preferenceKeys()[$key]['label'] ?? 'This notification type').' notifications are currently disabled by an administrator.', 'error');

                return;
            }
            if (! array_key_exists($key, $this->preferenceKeys())) {
                $this->dispatchToast('Invalid preference key.', 'error');

                return;
            }

            $notificationService->toggleUserNotificationType($this->user, $this->user->id, $type, $value);
            $action = $value ? 'enabled' : 'disabled';
            $this->dispatchToast(($this->preferenceKeys()[$key]['label'] ?? $key)." $action.", 'success');
        } catch (\Throwable $e) {
            $this->dispatchToast('Failed to update preference: '.$e->getMessage(), 'error');
        }
    }

    /**
     * Toggle the sidebar open/closed and reload preferences if opening.
     */
    #[On('toggle-sidebar')]
    public function toggleSidebar(NotificationPreferenceService $notificationService): void
    {
        if (! $this->isOpen) {
            $prefs = $notificationService->getFormattedUserPreferences($this->user, $this->user->id);
            $this->userNotificationsMuted = $prefs['user_notifications_muted'];
            $this->userNotificationStates = $prefs['user_notification_states'];
            $this->isGloballyEnabled = $prefs['global_notifications_enabled'];
            $this->globalPreferenceStates = $prefs['global_notification_type_states'];
            $this->dispatchUnreadCountChanged();
        }
        $this->isOpen = ! $this->isOpen;
    }

    /**
     * Change the active tab in the sidebar.
     */
    public function changeTab(string $tab): void
    {
        if (in_array($tab, ['notifications', 'settings'])) {
            $this->activeTab = $tab;
            $this->resetPage();
        }
    }

    /**
     * Set the notification filter for the list.
     */
    public function setNotificationFilter(string $filter): void
    {
        if (in_array($filter, ['all', 'unread', 'read'])) {
            $this->notificationFilter = $filter;
            $this->resetPage();
        }
    }

    /**
     * Dispatch an event to update the unread notification count.
     */
    private function dispatchUnreadCountChanged(): void
    {
        $count = $this->user->unreadNotifications()->count();
        $this->dispatch('unread-count-changed', count: $count);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $notificationId): void
    {
        // Find notification that belongs to the current user (ownership check built-in)
        $notification = $this->user->notifications()->find($notificationId);
        if ($notification && $notification->unread()) {
            $notification->markAsRead();
            $this->dispatchToast('Notification marked as read.', 'success');
            unset($this->notifications);
        }
    }

    /**
     * Mark all notifications as read for the user.
     */
    public function markAllAsRead(): void
    {
        $count = $this->user->unreadNotifications()->count();
        if ($count > 0) {
            $this->user->unreadNotifications()->update(['read_at' => now()]);
            $this->dispatchToast('All notifications marked as read.', 'success');
            unset($this->notifications);
            $this->dispatchUnreadCountChanged();
        }
    }

    /**
     * Delete a single notification.
     */
    public function deleteNotification(string $notificationId): void
    {
        // Find notification that belongs to the current user (ownership check built-in)
        $notification = $this->user->notifications()->find($notificationId);
        if ($notification) {
            $notification->delete();
            $this->dispatchToast('Notification deleted.', 'success');
            unset($this->notifications);
        }
    }

    /**
     * Delete all notifications for the user.
     */
    public function deleteAllNotifications(): void
    {
        $count = $this->user->notifications()->count();
        if ($count > 0) {
            $this->user->notifications()->delete();
            $this->dispatchToast('All notifications deleted.', 'success');
            unset($this->notifications);
            $this->dispatchUnreadCountChanged();
        }
    }

    /**
     * Show the details modal for a notification.
     */
    public function showNotificationDetails(string $notificationId): void
    {
        // Find notification that belongs to the current user (ownership check built-in)
        $notification = $this->user->notifications()->find($notificationId);
        if ($notification) {
            $this->dispatch('openNotificationDetailsModal', notificationId: $notificationId);
        }
    }

    /**
     * Refresh notifications and unread count when notified by event.
     */
    #[On('notification-updated')]
    public function refreshNotifications(): void
    {
        unset($this->notifications);
        $this->dispatchUnreadCountChanged();
    }

    /**
     * Handle global notification update event.
     */
    #[On('global-notifications-updated')]
    public function handleGlobalNotificationUpdate(NotificationPreferenceService $notificationService): void
    {
        $prefs = $notificationService->getFormattedUserPreferences($this->user, $this->user->id);
        $this->userNotificationsMuted = $prefs['user_notifications_muted'];
        $this->userNotificationStates = $prefs['user_notification_states'];
        $this->isGloballyEnabled = $prefs['global_notifications_enabled'];
        $this->globalPreferenceStates = $prefs['global_notification_type_states'];
    }

    /**
     * Handle any global notification type update event.
     */
    #[On('schedule-change-global-setting-updated')]
    #[On('weekly-user-report-global-setting-updated')]
    #[On('leave-reminder-global-setting-updated')]
    #[On('api-down-warning-global-setting-updated')]
    #[On('admin-promotion-email-global-setting-updated')]
    #[On('duplicate-schedule-warning-global-setting-updated')]
    public function handleAnyGlobalUpdate(NotificationPreferenceService $notificationService): void
    {
        $prefs = $notificationService->getFormattedUserPreferences($this->user, $this->user->id);
        $this->userNotificationsMuted = $prefs['user_notifications_muted'];
        $this->userNotificationStates = $prefs['user_notification_states'];
        $this->isGloballyEnabled = $prefs['global_notifications_enabled'];
        $this->globalPreferenceStates = $prefs['global_notification_type_states'];
    }

    /**
     * Dispatch a toast event with a message and variant.
     */
    private function dispatchToast(string $message, string $variant): void
    {
        $this->dispatch('add-toast', message: $message, variant: $variant);
    }

    /**
     * Render the sidebar view.
     *
     * @return \Illuminate\View\View
     */
    public function render()
    {
        return view('livewire.sidebar');
    }
}
