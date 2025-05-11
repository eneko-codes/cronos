<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Component;

#[Lazy]
class UserProfileHeader extends Component
{
    public User $user;

    public function mount(User $user)
    {
        $this->user = $user;
    }

    #[Computed]
    public function allBadges(): array
    {
        $badges = [];

        // Admin Badge
        if ($this->user->is_admin) {
            $badges[] = [
                'text' => 'Admin',
                'variant' => 'primary',
                'tooltip' => 'User can see all employee data',
                'isMissing' => false,
            ];
        }

        // Not Tracking Badge
        if ($this->user->do_not_track) {
            $badges[] = [
                'text' => 'Not tracking',
                'variant' => 'warning',
                'tooltip' => 'The data of this user will not be fetched',
                'isMissing' => false,
            ];
        }

        // Odoo Badge
        if ($this->user->odoo_id) {
            $badges[] = [
                'text' => 'Odoo',
                'variant' => 'info',
                'tooltip' => 'User has an Odoo account linked',
                'isMissing' => false,
            ];
        } else {
            $badges[] = [
                'text' => 'Odoo Missing',
                'variant' => 'alert',
                'tooltip' => 'User is not linked with Odoo.',
                'isMissing' => true,
            ];
        }

        // Desktime Badge
        if ($this->user->desktime_id) {
            $badges[] = [
                'text' => 'Desktime',
                'variant' => 'info',
                'tooltip' => 'User has a Desktime account linked',
                'isMissing' => false,
            ];
        } else {
            $badges[] = [
                'text' => 'DeskTime Missing',
                'variant' => 'alert',
                'tooltip' => 'User is not linked with DeskTime.',
                'isMissing' => true,
            ];
        }

        // Proofhub Badge
        if ($this->user->proofhub_id) {
            $badges[] = [
                'text' => 'Proofhub',
                'variant' => 'info',
                'tooltip' => 'User has a Proofhub account linked',
                'isMissing' => false,
            ];
        } else {
            $badges[] = [
                'text' => 'ProofHub Missing',
                'variant' => 'alert',
                'tooltip' => 'User is not linked with ProofHub.',
                'isMissing' => true,
            ];
        }

        // SystemPin Badge
        if ($this->user->systempin_id) {
            $badges[] = [
                'text' => 'SystemPin',
                'variant' => 'info',
                'tooltip' => 'User has a SystemPin account linked',
                'isMissing' => false,
            ];
        } else {
            $badges[] = [
                'text' => 'Systempin Missing',
                'variant' => 'alert',
                'tooltip' => 'User does not have a System PIN.',
                'isMissing' => true,
            ];
        }

        return $badges;
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
