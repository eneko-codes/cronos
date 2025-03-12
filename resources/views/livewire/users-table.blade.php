<div class="flex w-full flex-col gap-5 overflow-hidden">
  <!-- Header Section: Filters and Search -->
  <div
    class="flex flex-col items-start justify-start gap-4 border-b-2 pb-3 md:flex-row md:items-stretch dark:border-gray-800"
  >
    <div class="flex flex-1 flex-col items-start gap-4 md:flex-row">
      <!-- Search Input -->
      <input
        type="text"
        wire:model.live.debounce.200ms="search"
        placeholder="Search users..."
        autofocus
        class="h-9 w-full max-w-48 rounded-md border border-gray-300 bg-gray-50 px-3 py-1 text-sm text-gray-900 outline-none placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500"
      />

      <!-- Filter Tabs - Only show if there are users -->
      @if ($counts['all'] > 0)
        <div
          class="inline-flex w-fit gap-1 whitespace-nowrap rounded-lg border border-gray-200 bg-gray-100 p-1 text-xs font-semibold dark:border-gray-700 dark:bg-gray-800"
        >
          <!-- All Users Filter -->
          <button
            wire:click="setFilter('all')"
            class="{{ $active === 'all' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
          >
            All Users
            <span class="font-light">{{ $counts['all'] }}</span>
          </button>

          <!-- Only show employee filter if there are employees -->
          @if ($counts['employees'] > 0)
            <button
              wire:click="setFilter('employees')"
              class="{{ $active === 'employees' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Employees
              <span class="font-light">{{ $counts['employees'] }}</span>
            </button>
          @endif

          <!-- Only show admin filter if there are admins -->
          @if ($counts['admins'] > 0)
            <button
              wire:click="setFilter('admins')"
              class="{{ $active === 'admins' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Admins
              <span class="font-light">{{ $counts['admins'] }}</span>
            </button>
          @endif

          <!-- Only show not tracked filter if there are not tracked users -->
          @if ($counts['not_tracked'] > 0)
            <button
              wire:click="setFilter('not-tracked')"
              class="{{ $active === 'not-tracked' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Not Tracked
              <span class="font-light">{{ $counts['not_tracked'] }}</span>
            </button>
          @endif

          <!-- Only show muted filter if there are muted users -->
          @if ($counts['muted'] > 0)
            <button
              wire:click="setFilter('muted')"
              class="{{ $active === 'muted' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Muted
              <span class="font-light">{{ $counts['muted'] }}</span>
            </button>
          @endif
        </div>
      @endif
    </div>

    <!-- Action Buttons -->
    <div class="flex flex-row items-center gap-2">
      <!-- Sync Users Button -->
      <x-tooltip
        text="Synchronize all users from Odoo and their Proofhub, Desktime, and SystemPin IDs"
      >
        <livewire:sync-button :key="'users-table-sync'" sync-type="users" />
      </x-tooltip>
    </div>
  </div>

  <!-- Users Table -->
  @if ($users->isNotEmpty())
    <table class="w-full border-collapse">
      <tbody class="text-sm" wire:poll.5s>
        @foreach ($users as $user)
          <tr
            wire:key="user-{{ $user->id }}"
            class="flex flex-row items-center justify-between gap-4 border-b border-gray-200 p-2 text-gray-800 hover:cursor-pointer dark:border-gray-800 dark:text-gray-200 dark:hover:bg-gray-800/50"
          >
            <!-- User Info Column -->
            <td
              wire:click="redirectToUserPage({{ $user->id }})"
              class="flex w-full flex-1 flex-col gap-1 md:w-auto md:flex-row md:items-center"
            >
              <div class="flex items-center gap-2">
                <!-- Online Status Indicator with Tooltip -->
                <x-tooltip text="{{ $user->is_online ? 'Online' : 'Offline' }}">
                  <div
                    class="{{ $user->is_online ? 'bg-green-500' : 'bg-gray-300 dark:bg-gray-700' }} h-2 w-2 rounded-full"
                  ></div>
                </x-tooltip>

                <!-- User Name -->
                <p class="text-md font-bold capitalize">
                  {{ $user->name }}
                </p>
              </div>

              <!-- User Badges for Roles and Linked Accounts -->
              <div class="flex flex-wrap gap-1">
                @if ($user->is_admin)
                  <x-tooltip text="User can see all employee data">
                    <x-badge size="sm" variant="primary">Admin</x-badge>
                  </x-tooltip>
                @endif

                @if ($user->do_not_track)
                  <x-tooltip text="The data of this user will not be fetched">
                    <x-badge size="sm" variant="alert">Not tracking</x-badge>
                  </x-tooltip>
                @endif

                @if ($user->muted_notifications)
                  <x-tooltip text="User notifications are currently muted">
                    <x-badge size="sm" variant="alert">Muted</x-badge>
                  </x-tooltip>
                @endif

                @if ($user->odoo_id)
                  <x-tooltip text="User has an Odoo account linked">
                    <x-badge size="sm" variant="info">Odoo</x-badge>
                  </x-tooltip>
                @endif

                @if ($user->proofhub_id)
                  <x-tooltip text="User has a Proofhub account linked">
                    <x-badge size="sm" variant="info">ProofHub</x-badge>
                  </x-tooltip>
                @endif

                @if ($user->desktime_id)
                  <x-tooltip text="User has a Desktime account linked">
                    <x-badge size="sm" variant="info">Desktime</x-badge>
                  </x-tooltip>
                @endif

                @if ($user->systempin_id)
                  <x-tooltip text="User has a SystemPin account linked">
                    <x-badge size="sm" variant="info">SystemPin</x-badge>
                  </x-tooltip>
                @endif
              </div>
            </td>

            <!-- Action Column: Details Button -->
            <td
              class="flex-none items-center justify-center gap-2 whitespace-nowrap"
            >
              <x-button
                wire:click="$dispatch('openUserDetailsModal', { userId: {{ $user->id }} })"
                variant="default"
                size="xs"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  class="size-4"
                >
                  <path
                    fill-rule="evenodd"
                    d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z"
                    clip-rule="evenodd"
                  />
                </svg>
                Details
              </x-button>
            </td>
          </tr>
        @endforeach
      </tbody>
    </table>

    <!-- Pagination Links -->
    <div class="w-full">
      {{ $users->links() }}
    </div>
  @else
    <!-- No Users Found Message -->
    <span class="text-sm font-semibold">No users found!</span>
  @endif
</div>
