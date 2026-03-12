<?php

declare(strict_types=1);

namespace App\Livewire\UI;

use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class AppTime extends Component
{
    // Current app time
    public string $appTime = '';

    public function mount()
    {
        $this->updateAppTime();
    }

    /**
     * Update the current app time
     */
    public function updateAppTime()
    {
        $this->appTime = now()->format('H:i:s');
    }

    public function render()
    {
        return view('livewire.ui.app-time');
    }
}
