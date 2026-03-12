<?php

/**
 * NotificationsList (Livewire Component)
 *
 * This component manages the user's notification list in the sidebar, including:
 * - Paginated notification list
 * - Filtering by status (all, unread, read)
 * - Individual and bulk actions (mark as read, delete)
 * - Global/user notification status indicators
 *
 * Receives the userId from the parent Sidebar component.
 */

declare(strict_types=1);

namespace App\Livewire\Sidebar;

use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Class NotificationsList
 *
 * @property LengthAwarePaginator $notifications Paginated notifications for the user
 * @property array $notificationCounts Counts for all/unread/read filters
 */
class NotificationsList extends Component
{
    use WithPagination;

    /**
     * The user ID passed from parent Sidebar component.
     */
    #[Locked]
    public ?int $userId = null;

    /**
     * The current filter for the notification list: 'all', 'unread', 'read'.
     */
    public string $notificationFilter = 'all';

    /**
     * Indicates if notifications are enabled globally (system-wide master switch).
     */
    public bool $isGloballyEnabled = true;

    /**
     * Whether the user has muted their personal notifications.
     */
    public bool $userNotificationsMuted = false;

    /**
     * Mount the component and load notification status.
     */
    public function mount(?int $userId, NotificationService $notificationService): void
    {
        $this->userId = $userId;

        $user = $this->user;
        if (! $user) {
            return;
        }

        $authUser = Auth::user();
        if (! $authUser) {
            return;
        }

        // Load global and user notification states for status indicators
        $prefs = $notificationService->getPreferences($authUser, $user->id);
        $this->isGloballyEnabled = $prefs['global_enabled'];
        $this->userNotificationsMuted = $prefs['user_mute_all'];

        $this->dispatchUnreadCountChanged();
    }

    /**
     * Get the user model from the locked userId.
     */
    #[Computed]
    public function user(): ?User
    {
        // Only accesses notifications relationship (Laravel handles this efficiently)
        return $this->userId ? User::find($this->userId) : null;
    }

    /**
     * Get the paginated notifications for the user, filtered by the current filter.
     */
    #[Computed]
    public function notifications(): LengthAwarePaginator
    {
        $user = $this->user;
        if (! $user) {
            return new LengthAwarePaginator([], 0, 15);
        }

        $query = $user->notifications();

        $query = match ($this->notificationFilter) {
            'unread' => $query->whereNull('read_at'),
            'read' => $query->whereNotNull('read_at'),
            default => $query,
        };

        return $query->paginate(15);
    }

    /**
     * Get the notification counts for the user (all, unread, read).
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
            $this->dispatchUnreadCountChanged();
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
            $this->dispatchUnreadCountChanged();
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
                unset($this->notifications);
                $this->dispatchUnreadCountChanged();
            }
            $this->dispatch('openNotificationDetailsModal', notificationId: $notificationId);
        }
    }

    /**
     * Refresh notifications when notified by event (e.g., from NotificationDetailsModal).
     */
    #[On('notification-updated')]
    public function refreshNotifications(): void
    {
        unset($this->notifications);
        $this->dispatchUnreadCountChanged();
    }

    /**
     * Dispatch an event to update the unread notification count in the navbar.
     */
    private function dispatchUnreadCountChanged(): void
    {
        $user = $this->user;
        $count = $user ? $user->unreadNotifications()->count() : 0;
        $this->dispatch('unread-count-changed', count: $count);
    }

    /**
     * Dispatch a toast notification.
     */
    private function dispatchToast(string $message, string $variant): void
    {
        $this->dispatch('add-toast', message: $message, variant: $variant);
    }

    /**
     * Render the component view.
     */
    public function render(): \Illuminate\Contracts\View\View|\Illuminate\Contracts\View\Factory
    {
        return view('livewire.sidebar.notifications-list');
    }
}
