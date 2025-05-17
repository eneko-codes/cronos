<x-layouts.app>
  <div class="flex flex-col gap-5">
    <!-- Back button -->
    @if ($isAdmin && $isViewingSpecificUser)
      <a
        href="{{ route('users.list') }}"
        wire:navigate
        class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-white px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-800 shadow-sm hover:bg-gray-50 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-100"
      >
        <svg
          class="size-4"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M12.79 5.23a.75.75 0 01-.02 1.06L8.832 10l3.938 3.71a.75.75 0 11-1.04 1.08l-4.5-4.25a.75.75 0 010-1.08l4.5-4.25a.75.75 0 011.06.02z"
            clip-rule="evenodd"
          />
        </svg>
        Back to Users
      </a>
    @endif

    <!-- User Profile Header Component -->
    <livewire:user-profile-header
      :user="$user"
      key="user-profile-header-{{ $user->id }}"
    />

    {{-- Widgets Section --}}
    @if (! $user->do_not_track)
      <livewire:user-dashboard-widgets
        :user="$user"
        key="widgets-{{ $user->id }}"
      />
    @endif

    {{-- User data table --}}
    @if ($user->do_not_track)
      {{-- User is set to do not track --}}
      <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
        <p class="text-md text-gray-600 dark:text-gray-300">
          {{ $user->name }} is currently set to do not track. Data is not
          stored for this user.
        </p>
      </div>
    @else
      {{-- Tracked User: Render the new UserTimeSheetTable component --}}
      <livewire:user-time-sheet-table
        :user="$user"
        lazy
        key="user-timesheet-table-{{ $user->id }}"
      />
    @endif
  </div>
</x-layouts.app>
