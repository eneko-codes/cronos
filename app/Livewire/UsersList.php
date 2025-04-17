<?php

namespace App\Livewire;

use App\Models\User;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

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

  public function render()
  {
    $users = User::query()
      ->when($this->search, function ($query) {
        $query->whereRaw('LOWER(name) LIKE ?', [
          '%' . strtolower($this->search) . '%',
        ]);
      })
      ->when(
        $this->filter === 'admins',
        fn($query) => $query->where('is_admin', true)
      )
      ->when(
        $this->filter === 'employees',
        fn($query) => $query->where('is_admin', false)
      )
      ->when(
        $this->filter === 'not_tracked',
        fn($query) => $query->notTrackable()
      )
      ->when(
        $this->filter === 'muted',
        fn($query) => $query->where('muted_notifications', true)
      )
      ->orderBy('name')
      ->paginate($this->itemsPerPage);

    $counts = [
      'all' => User::count(),
      'admins' => User::where('is_admin', true)->count(),
      'employees' => User::where('is_admin', false)->count(),
      'not_tracked' => User::notTrackable()->count(),
      'muted' => User::where('muted_notifications', true)->count(),
    ];

    return view('livewire.users-list', [
      'users' => $users,
      'counts' => $counts,
    ]);
  }
}
