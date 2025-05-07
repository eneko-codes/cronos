<div class="flex flex-col gap-5">
  <!-- Back button -->
  @if ($isAdmin && $isViewingSpecificUser)
    <a
      href="{{ route('users.list') }}"
      wire:navigate
      class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-gray-200/75 px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-800 shadow-sm hover:bg-gray-200 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-100"
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

  <!-- Header Section -->
  <div class="flex flex-row items-center gap-2">
    <h1 class="text-xl font-bold">{{ $user->name }}</h1>

    <!-- User Badges -->
    <div class="flex flex-row gap-1">
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

      @if ($user->odoo_id)
        <x-tooltip text="User has an Odoo account linked">
          <x-badge size="sm" variant="info">Odoo</x-badge>
        </x-tooltip>
      @endif

      @if ($user->desktime_id)
        <x-tooltip text="User has a Desktime account linked">
          <x-badge size="sm" variant="info">Desktime</x-badge>
        </x-tooltip>
      @endif

      @if ($user->proofhub_id)
        <x-tooltip text="User has a Proofhub account linked">
          <x-badge size="sm" variant="info">Proofhub</x-badge>
        </x-tooltip>
      @endif

      @if ($user->systempin_id)
        <x-tooltip text="User has a SystemPin account linked">
          <x-badge size="sm" variant="info">SystemPin</x-badge>
        </x-tooltip>
      @endif
    </div>
  </div>

  {{-- Missing Account Notification --}}
  @if (! $user->do_not_track && (! $user->proofhub_id || ! $user->desktime_id || ! $user->systempin_id))
    <div
      class="w-fit rounded-md border border-red-300 bg-red-50 p-4 text-sm text-red-800 dark:border-red-600/60 dark:bg-red-900/20 dark:text-red-200"
    >
      <div class="flex flex-col gap-2">
        <div class="flex flex-row items-center gap-1">
          <svg
            class="h-5 w-5 flex-shrink-0 text-red-500 dark:text-red-600"
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
            />
          </svg>
          <h3 class="text-md font-semibold">Missing Account Links</h3>
        </div>

        <p class="text-xs">
          User IDs for external platforms are automatically added via sync,
          using the email set on the Odoo user profile as the primary key.
        </p>
        <p class="text-xs">
          To be able to pull the missing data make sure to use your company
          email in the missing accounts:
          <span class="font-medium">{{ $user->email }}</span>
        </p>

        <div class="flex flex-wrap gap-1">
          @if (! $user->proofhub_id)
            <x-tooltip text="ProofHub account ID is missing">
              <x-badge variant="alert" size="sm">ProofHub</x-badge>
            </x-tooltip>
          @endif

          @if (! $user->desktime_id)
            <x-tooltip text="DeskTime account ID is missing">
              <x-badge variant="alert" size="sm">DeskTime</x-badge>
            </x-tooltip>
          @endif

          @if (! $user->systempin_id)
            <x-tooltip text="SystemPin account ID is missing">
              <x-badge variant="alert" size="sm">SystemPin</x-badge>
            </x-tooltip>
          @endif
        </div>
      </div>
    </div>
  @endif

  {{-- Widgets Section --}}
  <livewire:user-dashboard-widgets
    :user="$user"
    key="widgets-{{ $user->id }}"
  />

  {{-- User data table --}}
  @if ($user->do_not_track)
    {{-- User is set to do not track --}}
    <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
      <p class="text-md text-gray-600 dark:text-gray-300">
        {{ $user->name }} is currently set to do not track. Data is not stored
        for this user.
      </p>
    </div>
  @else
    {{-- Tracked User: Render the new UserTimeSheetTable component --}}
    <livewire:user-time-sheet-table
      :user="$user"
      :currentDate="$currentDate"
      :viewMode="$viewMode"
      :showDeviations="$showDeviations"
      :periodData="$periodData"
      :dashboardTotals="$dashboardTotals"
      {{-- Passed from UserDashboard's render method --}}
      :totalDeviationsDetails="$totalDeviationsDetailsForTable"
      {{-- Passed from UserDashboard's render method --}}
      :isNextPeriodDisabled="$isNextPeriodDisabledForTable"
      {{-- Passed from UserDashboard's render method --}}
      key="user-timesheet-table-{{ $user->id }}-{{ $currentDate }}-{{ $viewMode }}-{{ $showDeviations ? 'true' : 'false' }}"
      {{-- Unique key for reactivity --}}
    />
  @endif
</div>
