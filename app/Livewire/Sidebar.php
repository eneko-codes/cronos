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

use App\Actions\UpdateNotificationPreferencesAction;
use App\Enums\NotificationType;
use App\Models\User;
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
     * The authenticated user ID (nullable for Livewire safety).
     */
    #[Locked]
    public ?int $userId = null;

    /**
     * Mount the component and load user preferences.
     */
    public function mount(): void
    {
        $this->userId = $this->userId ?? Auth::id();
        $user = $this->user;
        if (! $user) {
            $user = Auth::user();
            if (! $user) {
                throw new \RuntimeException('Authenticated user not found.');
            }
            $this->userId = $user->id;
        }
        $this->authorize('accessPreferencesSidebar');
        $prefs = app(\App\Actions\GetNotificationPreferencesAction::class)->execute($user, $user->id);
        $this->userNotificationsMuted = $prefs['user_mute_all'];
        $this->userNotificationStates = $prefs['user_individual'];
        $this->isGloballyEnabled = $prefs['global_enabled'];
        $this->globalPreferenceStates = $prefs['global_types'];
        $this->dispatchUnreadCountChanged();
    }

    /**
     * Get the authenticated user model.
     */
    #[Computed]
    public function user(): ?User
    {
        return $this->userId ? User::find($this->userId) : null;
    }

    /**
     * Get the notification preference keys and their labels/admin status.
     */
    #[Computed]
    public function preferenceKeys(): array
    {
        $user = $this->user;
        if (! $user) {
            return [];
        }
        $keys = [];
        foreach (NotificationType::cases() as $type) {
            // Skip non-admin toggles for non-admins
            if ($type->isAdminOnly() && ! $user->isAdmin()) {
                continue;
            }
            $isSpecificTypeGloballyOff = isset($this->globalPreferenceStates[$type->value]) && ! $this->globalPreferenceStates[$type->value];
            // Determine disabled state
            $isDisabled = ! $this->isGloballyEnabled || $isSpecificTypeGloballyOff || $this->userNotificationsMuted;
            // Determine tooltip text (always normal description for label)
            $tooltipText = 'Enable or disable '.strtolower($type->label()).' for your account.';
            $keys[$type->value] = [
                'label' => $type->label(),
                'description' => $type->description(),
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
        $user = $this->user;
        if (! $user) {
            return new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15);
        }
        $query = $user->notifications();
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
        $user = $this->user;
        if (! $user) {
            return ['all' => 0, 'unread' => 0, 'read' => 0];
        }
        $unreadCount = $user->unreadNotifications()->count();
        $readCount = $user->readNotifications()->count();
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
    public function updatedUserNotificationsMuted(bool $value, UpdateNotificationPreferencesAction $updatePreferences): void
    {
        $user = $this->user;
        if (! $user) {
            return;
        }
        $updatePreferences->muteAll($user, $user->id, $value);
        $this->dispatchToast('Notification mute setting updated.', 'success');
    }

    /**
     * Handle updates to individual notification preferences.
     */
    public function updatedUserNotificationStates(bool $value, string $key, UpdateNotificationPreferencesAction $updatePreferences): void
    {
        $user = $this->user;
        if (! $user) {
            return;
        }
        $type = NotificationType::from($key);
        $updatePreferences->toggleType($user, $user->id, $type, $value);
        $action = $value ? 'enabled' : 'disabled';
        $this->dispatchToast(($this->preferenceKeys()[$key]['label'] ?? $key)." $action.", 'success');
    }

    /**
     * Toggle the sidebar open/closed and reload preferences if opening.
     */
    #[On('toggle-sidebar')]
    public function toggleSidebar(): void
    {
        $user = $this->user;
        if (! $user) {
            $user = Auth::user();
            if (! $user) {
                throw new \RuntimeException('Authenticated user not found.');
            }
            $this->userId = $user->id;
        }
        if (! $this->isOpen) {
            $prefs = app(\App\Actions\GetNotificationPreferencesAction::class)->execute($user, $user->id);
            $this->userNotificationsMuted = $prefs['user_mute_all'];
            $this->userNotificationStates = $prefs['user_individual'];
            $this->isGloballyEnabled = $prefs['global_enabled'];
            $this->globalPreferenceStates = $prefs['global_types'];
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
        $user = $this->user;
        $count = $user ? $user->unreadNotifications()->count() : 0;
        $this->dispatch('unread-count-changed', count: $count);
    }

    /**
     * Mark a single notification as read.
     */
    public function markAsRead(string $notificationId): void
    {
        $user = $this->user;
        if (! $user) {
            return;
        }
        $notification = $user->notifications()->find($notificationId);
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
        $user = $this->user;
        if (! $user) {
            return;
        }
        $count = $user->unreadNotifications()->count();
        if ($count > 0) {
            $user->unreadNotifications()->update(['read_at' => now()]);
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
        $user = $this->user;
        if (! $user) {
            return;
        }
        $notification = $user->notifications()->find($notificationId);
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
        $user = $this->user;
        if (! $user) {
            return;
        }
        $count = $user->notifications()->count();
        if ($count > 0) {
            $user->notifications()->delete();
            $this->dispatchToast('All notifications deleted.', 'success');
            unset($this->notifications);
            $this->dispatchUnreadCountChanged();
        }
    }

    /**
     * Show the details modal for a notification.
     * Automatically marks unread notifications as read when opened.
     */
    public function showNotificationDetails(string $notificationId): void
    {
        $user = $this->user;
        if (! $user) {
            return;
        }
        $notification = $user->notifications()->find($notificationId);
        if ($notification) {
            // Automatically mark as read if unread
            if ($notification->unread()) {
                $notification->markAsRead();
                // Refresh the notifications list to update the UI
                unset($this->notifications);
                $this->dispatchUnreadCountChanged();
            }
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
