<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Setting;
use App\Models\User;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
#[Title('Users')]
class UsersList extends Component
{
    use WithPagination;

    public int $itemsPerPage = 30;

    public string $search = '';

    public string $filter = 'all';

    public string $active = 'all';

    public bool $isLoading = false;

    protected $queryString = [
        'search' => ['except' => ''],
        'filter' => ['except' => 'all'],
        'page' => ['except' => 1],
    ];

    #[On('updated:search')]
    public function resetPageWhenSearchIsUpdated(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = $filter;
        $this->active = $filter;
        $this->resetPage();
    }

    public function redirectToUserDashboard(int $userId)
    {
        return redirect()->route('user.dashboard', ['id' => $userId]);
    }

    /**
     * Render a skeleton placeholder while the users list is loading.
     * This provides a visual indication that the users data is being fetched and processed.
     */
    /*
    public function placeholder()
    {
        return view('livewire.placeholders.users-list');
    }*/

    public function render()
    {
        $globalNotificationsEnabled = (bool) Setting::getValue(
            'notifications.global_enabled',
            true
        );

        $users = User::query()
            ->with('notificationPreferences')
            ->when($this->search, function ($query): void {
                $query->whereRaw('LOWER(name) LIKE ?', [
                    '%'.strtolower($this->search).'%',
                ]);
            })
            ->when(
                $this->filter === 'admins',
                fn ($query) => $query->where('is_admin', true)
            )
            ->when(
                $this->filter === 'not_tracked',
                fn ($query) => $query->notTrackable()
            )
            ->when(
                $this->filter === 'muted',
                fn ($query) => $query->whereHas(
                    'notificationPreferences',
                    fn ($prefQuery) => $prefQuery->where('mute_all', true)
                )
            )
            ->orderBy('name')
            ->paginate($this->itemsPerPage);

        $counts = [
            'all' => User::count(),
            'admins' => User::where('is_admin', true)->count(),
            'not_tracked' => User::notTrackable()->count(),
            'muted' => User::whereHas(
                'notificationPreferences',
                fn ($query) => $query->where('mute_all', true)
            )->count(),
        ];

        return view('livewire.users-list', [
            'users' => $users,
            'counts' => $counts,
            'globalNotificationsEnabled' => $globalNotificationsEnabled,
        ]);
    }
}
