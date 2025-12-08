<?php

declare(strict_types=1);

namespace App\Livewire\Users;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
#[Title('Users')]
class UsersList extends Component
{
    use WithPagination;

    public int $itemsPerPage = 30;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $filter = 'all';

    public string $active = 'all';

    public bool $isLoading = false;

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

    public function redirectToUserDashboard(int $userId): void
    {
        // Only used for authorization check, no relationships needed
        $targetUser = User::findOrFail($userId);

        // Authorize: check if the authenticated user can view this user's dashboard
        // Uses the 'viewUserDashboard' gate defined in AuthServiceProvider
        $this->authorize('viewUserDashboard', $targetUser);

        $this->redirect(route('user.dashboard', ['user' => $userId]));
    }

    /**
     * Render a skeleton placeholder while the users list is loading.
     * This provides a visual indication that the users data is being fetched and processed.
     */
    public function placeholder()
    {
        return view('livewire.users.users-list-skeleton');
    }

    public function render(): View
    {
        $users = User::query()
            ->with('externalIdentities')
            ->when($this->search, function ($query): void {
                $query->whereRaw('LOWER(name) LIKE ?', [
                    '%'.strtolower($this->search).'%',
                ]);
            })
            ->when(
                $this->filter === 'admins',
                fn ($query) => $query->where('user_type', RoleType::Admin)
            )
            ->when(
                $this->filter === 'not_tracked',
                fn ($query) => $query->notTrackable()
            )
            ->when(
                $this->filter === 'muted',
                fn ($query) => $query->where('muted_notifications', true)
            )
            ->when(
                $this->filter === 'maintenance',
                fn ($query) => $query->where('user_type', RoleType::Maintenance)
            )
            ->orderBy('name')
            ->paginate($this->itemsPerPage);

        $counts = [
            'all' => User::count(),
            'admins' => User::where('user_type', RoleType::Admin)->count(),
            'not_tracked' => User::notTrackable()->count(),
            'muted' => User::where('muted_notifications', true)->count(),
            'maintenance' => User::where('user_type', RoleType::Maintenance)->count(),
        ];

        $authUser = Auth::user();

        // Pre-compute dashboard access for each user to avoid N+1 queries in the view
        // Uses the 'viewUserDashboard' gate defined in AuthServiceProvider
        $dashboardAccess = [];
        foreach ($users as $user) {
            $dashboardAccess[$user->id] = $authUser->can('viewUserDashboard', $user);
        }

        return view('livewire.users.users-list', [
            'users' => $users,
            'counts' => $counts,
            'dashboardAccess' => $dashboardAccess,
        ]);
    }
}
