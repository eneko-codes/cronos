<div class="flex w-full flex-col gap-5 overflow-hidden">
  <!-- Header Section: Filters and Search -->
  <div
    class="flex flex-col items-start justify-start gap-4 rounded-lg bg-white p-3 shadow-sm md:flex-row md:items-stretch dark:bg-gray-800"
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
      @if ($counts["all"] > 0)
        <x-tabs
          :active="$active"
          :counts="$counts"
          :filters="[
              'all' => 'All Users',
              'admins' => 'Admins',
              'maintenance' => 'Maintenance',
              'not_tracked' => 'Not Tracked',
              'muted' => 'Muted'
          ]"
          onFilterChange="setFilter"
        />
      @endif
    </div>
  </div>

  <!-- Users Table -->
  <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-800">
    @if ($users->isNotEmpty())
      <table class="w-full border-collapse">
        <tbody class="text-sm" wire:poll.10s>
          @foreach ($users as $user)
            @php
              $canViewDashboard = $dashboardAccess[$user->id] ?? false;
            @endphp

            <tr
              wire:key="user-{{ $user->id }}"
              class="{{ $canViewDashboard ? "hover:cursor-pointer hover:bg-gray-50" : "" }} {{ $canViewDashboard ? "dark:hover:bg-gray-700/50" : "" }} flex flex-row items-center justify-between gap-4 border-b border-gray-200 p-2 text-gray-800 dark:border-gray-700 dark:text-gray-200"
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
                  <p class="text-md font-bold capitalize">
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
      <span class="text-sm font-semibold">No users found!</span>
    @endif
  </div>
</div>
