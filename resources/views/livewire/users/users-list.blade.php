<div class="flex w-full flex-col gap-5 overflow-hidden">
  <!-- Header Section: Filters and Search -->
  <div
    class="flex flex-col items-center justify-between gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:flex-row dark:border-gray-700 dark:bg-gray-800"
    wire:poll.10s.visible
  >
    <div class="flex flex-1 flex-col items-center gap-4 md:flex-row">
      <!-- Search Input -->
      <x-input
        type="text"
        wire:model.live.debounce.200ms="search"
        placeholder="Search users..."
        autofocus
        class="max-w-48"
      />

      <!-- Filter Tabs - Only show if there are users -->
      @if ($counts["all"] > 0)
        <x-tabs
          :active="$active"
          :counts="$counts"
          :filters="[
              'all' => 'All Users',
              'admins' => 'Admins',
              'maintenance' => 'Maintenance',
              'not_tracked' => 'Not Tracked',
              'muted' => 'Muted',
              'without_account' => 'No Account'
          ]"
          onFilterChange="setFilter"
        />
      @endif
    </div>

    <!-- Archived Users Button - Aligned to the right -->
    <x-button
      wire:click="$dispatch('openArchivedUsersModal')"
      variant="default"
      size="sm"
      class="whitespace-nowrap"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        fill="currentColor"
        class="bi bi-person-fill-slash"
        viewBox="0 0 16 16"
      >
        <path
          d="M13.879 10.414a2.501 2.501 0 0 0-3.465 3.465zm.707.707-3.465 3.465a2.501 2.501 0 0 0 3.465-3.465m-4.56-1.096a3.5 3.5 0 1 1 4.949 4.95 3.5 3.5 0 0 1-4.95-4.95ZM11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"
        />
      </svg>
      Archived Users
    </x-button>
  </div>

  <!-- Archived Users Modal -->
  <livewire:users.archived-users-modal />

  <!-- Users Table -->
  <div
    class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
  >
    @if ($users->isNotEmpty())
      <table class="w-full border-collapse">
        <tbody class="text-sm" wire:poll.10s.visible>
          @foreach ($users as $user)
            @php
              $canViewDashboard = $dashboardAccess[$user->id] ?? false;
            @endphp

            <tr
              wire:key="user-{{ $user->id }}"
              class="{{ $canViewDashboard ? "hover:cursor-pointer hover:bg-gray-50" : "" }} {{ $canViewDashboard ? "dark:hover:bg-gray-700/50" : "" }} flex flex-row items-center justify-between gap-4 border-b border-gray-200 p-2 text-gray-900 dark:border-gray-700 dark:text-gray-100"
            >
              <!-- User Info Column -->
              <td
                @if ($canViewDashboard)
                    wire:click="redirectToUserDashboard({{ $user->id }})"
                @endif
                class="{{ $canViewDashboard ? "cursor-pointer" : "" }} flex w-full flex-1 flex-col gap-1 md:w-auto md:flex-row md:items-center"
              >
                <div class="flex items-center gap-2">
                  <!-- Online Status Indicator with Tooltip -->
                  @if (! is_null($user->is_online))
                    <x-tooltip
                      text="{{ $user->is_online ? 'Online' : 'Offline' }}"
                    >
                      <div
                        class="{{ $user->is_online ? "bg-green-500" : "bg-gray-300 dark:bg-gray-700" }} h-2 w-2 rounded-full"
                      ></div>
                    </x-tooltip>
                  @endif

                  <!-- User Name -->
                  <p
                    class="text-base font-semibold text-gray-900 capitalize dark:text-gray-100"
                  >
                    {{ $user->name }}
                  </p>
                </div>

                <!-- User Badges for Roles and Linked Accounts -->
                <x-user-badges :user="$user" />
              </td>

              <!-- Action Column: Settings Button -->
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
                      d="M7.84 1.804A1 1 0 0 1 8.82 1h2.36a1 1 0 0 1 .98.804l.331 1.652a6.993 6.993 0 0 1 1.929 1.115l1.598-.54a1 1 0 0 1 1.186.447l1.18 2.044a1 1 0 0 1-.205 1.251l-1.267 1.113a7.047 7.047 0 0 1 0 2.228l1.267 1.113a1 1 0 0 1 .206 1.25l-1.18 2.045a1 1 0 0 1-1.187.447l-1.598-.54a6.993 6.993 0 0 1-1.93 1.115l-.33 1.652a1 1 0 0 1-.98.804H8.82a1 1 0 0 1-.98-.804l-.331-1.652a6.993 6.993 0 0 1-1.929-1.115l-1.598.54a1 1 0 0 1-1.186-.447l-1.18-2.044a1 1 0 0 1 .205-1.251l1.267-1.114a7.05 7.05 0 0 1 0-2.227L1.821 7.773a1 1 0 0 1-.206-1.25l1.18-2.045a1 1 0 0 1 1.187-.447l1.598.54A6.993 6.993 0 0 1 7.51 3.456l.33-1.652ZM10 13a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  Settings
                </x-button>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>

      <!-- Pagination Links -->
      <div class="mt-4 w-full">
        {{ $users->links() }}
      </div>
    @else
      <!-- No Users Found Message -->
      <span class="text-sm font-medium text-gray-600 dark:text-gray-400">
        No users found!
      </span>
    @endif
  </div>
</div>
