<?php

declare(strict_types=1);

namespace App\Livewire\Settings;

use Livewire\Attributes\Lazy;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Main Settings page component.
 *
 * This is a container component that composes the individual settings
 * sections as child Livewire components.
 */
#[Title('Settings')]
#[Lazy]
class Settings extends Component
{
    public function mount(): void
    {
        $this->authorize('accessSettingsPage');
    }

    public function placeholder(array $params = [])
    {
        return view('livewire.settings.settings-skeleton', $params);
    }

    public function render()
    {
        return view('livewire.settings.settings');
    }
}
