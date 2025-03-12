<?php

namespace App\Livewire;

use Livewire\Attributes\Locked;
use Livewire\Component;
use Illuminate\Support\Facades\Auth;

class TimezoneSelector extends Component
{
  #[Locked]
  public $userId;

  public $timezone;

  public function mount()
  {
    // Initialize timezone from session or user's data; default to Madrid if none
    $this->timezone = session(
      'timezone',
      Auth::user()->timezone ?? 'Europe/Madrid'
    );
    $this->userId = Auth::user()->id;

    // Store default timezone in session if not already set
    if (!session()->has('timezone')) {
      session(['timezone' => $this->timezone]);
    }
  }

  public function updatedTimezone($value)
  {
    // Validate the selected timezone
    if (!in_array($value, timezone_identifiers_list())) {
      return;
    }

    // Update session with the new timezone
    session(['timezone' => $value]);

    // Dispatch the timezone-changed event
    $this->dispatch('timezone-changed', timezone: $value);

    // Dispatch a toast notification
    $this->dispatch(
      'add-toast',
      message: "Timezone updated to {$value}.",
      variant: 'success'
    );
  }

  public function render()
  {
    return view('livewire.timezone-selector');
  }
}
