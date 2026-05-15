<?php

declare(strict_types=1);

namespace App\Livewire\Notifications;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class NotificationDetailsModal extends Component
{
    public bool $isOpen = false;

    #[Locked]
    public ?string $notificationId = null;

    // Properties to hold notification details
    public string $notificationSubject = '';

    public string $notificationType = '';

    public string $notificationMessage = '';

    public string $notificationCreatedAtDiff = '';

    public string $notificationCreatedAtFormatted = '';

    public ?string $notificationReadAtDiff = null;

    public ?string $notificationReadAtFormatted = null;

    public array $notificationData = [];

    #[On('openNotificationDetailsModal')]
    public function openModal(string $notificationId): void
    {
        $this->notificationId = $notificationId;
        $this->isOpen = true;
        $this->loadNotificationDetails();
    }

    public function closeModal(): void
    {
        $this->isOpen = false;
        $this->resetData();
    }

    public function loadNotificationDetails(): void
    {
        if (! $this->notificationId) {
            return;
        }

        $user = Auth::user();
        if (! $user) {
            $this->dispatch('add-toast', message: 'Authentication error.', variant: 'error');
            $this->closeModal();

            return;
        }

        try {
            /** @var DatabaseNotification|null $notification */
            $notification = $user->notifications()->findOrFail($this->notificationId);
            $this->prepareDetails($notification);
        } catch (ModelNotFoundException $e) {
            $this->dispatch('add-toast', message: 'Notification not found.', variant: 'error');
            $this->closeModal();
        } catch (\Exception $e) {
            $this->dispatch('add-toast', message: 'Error loading notification details.', variant: 'error');
            $this->closeModal();
        }
    }

    protected function prepareDetails(DatabaseNotification $notification): void
    {
        $this->notificationType = Str::headline(Str::snake(class_basename($notification->type)));
        $this->notificationSubject = $notification->data['subject'] ?? $this->notificationType;

        // Get raw message and convert newlines to <br> tags safely
        $rawMessage = $notification->data['message'] ?? 'No specific message.';
        $this->notificationMessage = nl2br(e($rawMessage)); // nl2br converts \n to <br>, e() escapes HTML

        // Prepare Created At dates
        $this->notificationCreatedAtDiff = $notification->created_at->diffForHumans();
        $this->notificationCreatedAtFormatted = $notification->created_at->format('M d, Y H:i:s T'); // Precise format

        // Prepare Read At dates (if applicable)
        $this->notificationReadAtDiff = $notification->read_at ? $notification->read_at->diffForHumans() : null;
        $this->notificationReadAtFormatted = $notification->read_at ? $notification->read_at->format('M d, Y H:i:s T') : null; // Precise format

        $this->notificationData = $notification->data;
    }

    // Method to delete directly from the modal
    public function deleteNotification(): void
    {
        if ($this->notificationId) {
            $user = Auth::user();
            $notification = $user?->notifications()->find($this->notificationId);
            if ($notification) {
                $notification->delete();
                $this->dispatch('add-toast', message: 'Notification deleted.', variant: 'success');
                $this->dispatch('notification-updated'); // Notify sidebar to refresh
                $this->closeModal(); // Close modal after deletion
            }
        }
    }

    protected function resetData(): void
    {
        $this->notificationId = null;
        $this->notificationSubject = '';
        $this->notificationType = '';
        $this->notificationMessage = '';
        $this->notificationCreatedAtDiff = '';
        $this->notificationCreatedAtFormatted = '';
        $this->notificationReadAtDiff = null;
        $this->notificationReadAtFormatted = null;
        $this->notificationData = [];
    }

    public function render()
    {
        return view('livewire.notifications.notification-details-modal');
    }
}
