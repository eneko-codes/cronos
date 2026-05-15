<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Enums\Platform;
use App\Models\User;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class UserBadges extends Component
{
    /**
     * The badges to display.
     *
     * @var array<int, array{text: string, variant: string, tooltip: string}>
     */
    public array $badges = [];

    /**
     * Create a new component instance.
     */
    public function __construct(public User $user)
    {
        $this->badges = $this->generateBadges();
    }

    /**
     * Generate all badges for the user.
     *
     * @return array<int, array{text: string, variant: string, tooltip: string}>
     */
    private function generateBadges(): array
    {
        $platformBadges = $this->generatePlatformBadges();
        $specialBadges = $this->generateSpecialBadges();

        return array_merge($platformBadges, $specialBadges);
    }

    /**
     * Generate platform connection badges.
     *
     * @return array<int, array{text: string, variant: string, tooltip: string}>
     */
    private function generatePlatformBadges(): array
    {
        $badges = [];

        foreach (Platform::cases() as $platform) {
            $isLinked = $this->user->externalIdentities->firstWhere('platform', $platform) !== null;

            $tooltip = match ($platform) {
                Platform::Odoo => $isLinked
                    ? 'User has an Odoo account linked'
                    : 'User is not linked with Odoo.',
                Platform::DeskTime => $isLinked
                    ? 'User has a DeskTime account linked'
                    : 'User is not linked with DeskTime.',
                Platform::ProofHub => $isLinked
                    ? 'User has a ProofHub account linked'
                    : 'User is not linked with ProofHub.',
                Platform::SystemPin => $isLinked
                    ? 'User has a SystemPin account linked'
                    : 'User is not linked with SystemPin.',
            };

            $badges[] = [
                'text' => $platform->label(),
                'variant' => $isLinked ? 'success' : 'alert',
                'tooltip' => $tooltip,
            ];
        }

        return $badges;
    }

    /**
     * Generate special role/status badges.
     *
     * @return array<int, array{text: string, variant: string, tooltip: string}>
     */
    private function generateSpecialBadges(): array
    {
        $badges = [];

        if ($this->user->isAdmin()) {
            $badges[] = [
                'text' => 'Admin',
                'variant' => 'primary',
                'tooltip' => 'User can see all employee data',
            ];
        }

        if ($this->user->isMaintenance()) {
            $badges[] = [
                'text' => 'Maintenance',
                'variant' => 'primary',
                'tooltip' => 'User can modify user and system parameters but cannot see employee data',
            ];
        }

        if ($this->user->do_not_track) {
            $badges[] = [
                'text' => 'Not tracking',
                'variant' => 'warning',
                'tooltip' => 'The data of this user will not be fetched',
            ];
        }

        if ($this->user->muted_notifications) {
            $badges[] = [
                'text' => 'Muted',
                'variant' => 'info',
                'tooltip' => 'User notifications muted',
            ];
        }

        if (! $this->user->hasAccount()) {
            $badges[] = [
                'text' => 'No account',
                'variant' => 'warning',
                'tooltip' => 'User has not set up their account password yet',
            ];
        }

        return $badges;
    }

    /**
     * Get the view / contents that represent the component.
     */
    public function render(): View|Closure|string
    {
        return view('components.user-badges');
    }
}
