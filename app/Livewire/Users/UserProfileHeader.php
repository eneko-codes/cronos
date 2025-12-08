<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Lazy]
class UserProfileHeader extends Component
{
    #[Locked]
    public int $userId;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    #[Computed]
    public function user(): User
    {
        return User::with('externalIdentities')->findOrFail($this->userId);
    }

    public function placeholder(array $params = []) // $params will contain the dehydrated props
    {
        // The placeholder view will render a generic skeleton.
        // We don't need to pass specific counts here for a simple skeleton.
        return view('livewire.users.user-profile-header-skeleton');
    }

    public function render()
    {
        return view('livewire.users.user-profile-header');
    }
}
