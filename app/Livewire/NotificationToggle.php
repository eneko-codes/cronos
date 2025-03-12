<?php

namespace App\Livewire;

use Livewire\Component;
use Livewire\Attributes\Computed;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class NotificationToggle extends Component
{
  #[Computed]
  public function isMuted(): bool
  {
    return Auth::user()->muted_notifications;
  }

  public function toggleMute(): void
  {
    /** @var User $user */
    $user = Auth::user();
    $user->muted_notifications = !$user->muted_notifications;
    $user->save();
  }

  public function render()
  {
    return view('livewire.notification-toggle', [
      'muted' => $this->isMuted(),
    ]);
  }
}
