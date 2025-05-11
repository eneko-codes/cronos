<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class UserProfileHeader extends Component
{
    public User $user;

    public function mount(User $user): void
    {
        $this->user = $user;
    }

    #[Computed]
    public function allBadges(): array
    {
        // UserProfileHeader shows all badges including missing platform links.
        return $this->user->getDisplayBadges();
    }

    public function placeholder(array $params = []) // $params will contain the dehydrated props
    {
        // The placeholder view will render a generic skeleton.
        // We don't need to pass specific counts here for a simple skeleton.
        return view('livewire.placeholders.user-profile-header-skeleton');
    }

    public function render()
    {
        return view('livewire.user-profile-header');
    }
}
