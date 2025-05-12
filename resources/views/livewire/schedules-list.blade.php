<div class="flex w-full flex-col gap-4">
  <!-- Header Section: Search and Filters -->
  <div
    class="flex flex-col items-start justify-start gap-4 rounded-lg bg-white p-3 shadow-sm md:flex-row md:items-stretch dark:bg-gray-800"
  >
    <div class="flex flex-1 flex-col items-start gap-4 md:flex-row">
      <div
        class="flex w-full flex-col items-stretch gap-3 md:w-auto md:flex-row md:items-center"
      >
        <!-- Search Input -->
        <div class="flex-grow md:flex-grow-0">
          <input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search schedules..."
            autofocus
            class="h-9 w-full max-w-xs rounded-md border border-gray-300 bg-gray-50 px-3 py-1 text-sm text-gray-900 outline-none placeholder:text-gray-400 focus:border-indigo-500 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500 dark:focus:border-indigo-600 dark:focus:ring-indigo-600"
          />
        </div>

        <!-- Control Group (Sort, Filter) -->
        <div class="flex items-center gap-2">
          <!-- Sort Controls -->
          <div class="flex items-center gap-1">
            <span
              class="hidden text-sm text-gray-600 sm:inline dark:text-gray-400"
            >
              Sort by:
            </span>
            <select
              wire:model.change="sortBy"
              class="block h-8 w-40 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm dark:border-gray-700 dark:bg-gray-700"
            >
              <option value="description_asc">Description (A-Z)</option>
              <option value="description_desc">Description (Z-A)</option>
              <option value="users_count_desc">Assigned Users (Most)</option>
              <option value="users_count_asc">Assigned Users (Fewest)</option>
              <option value="created_at_desc">Created (Newest)</option>
              <option value="created_at_asc">Created (Oldest)</option>
              <option value="updated_at_desc">Updated (Newest)</option>
              <option value="updated_at_asc">Updated (Oldest)</option>
            </select>
          </div>

          <!-- Filter Dropdown -->
          <div class="relative z-10">
            <x-dropdown-menu>
              {{-- Trigger Slot --}}
              <x-slot name="trigger">
                <span>Filters</span>
                @php
                  $activeFilterCount = collect($filters)
                    ->filter() // Count only filters set to true
                    ->count();
                @endphp

                @if ($activeFilterCount > 0)
                  <span
                    class="ml-1 inline-flex items-center rounded-full bg-gray-200 px-2 py-0.5 text-xs font-medium text-gray-800 dark:bg-gray-600 dark:text-gray-100"
                  >
                    {{ $activeFilterCount }}
                  </span>
                @endif
              </x-slot>

              {{-- Dropdown Content Slot --}}
              <div class="space-y-1" role="none">
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_details"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has Details
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_assigned_users"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has Assigned Users
                  </span>
                </label>
                <hr class="!my-2 border-gray-200 dark:border-gray-700" />
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_no_details"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has No Details
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_no_assigned_users"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has No Assigned Users
                  </span>
                </label>
              </div>
            </x-dropdown-menu>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Schedule List Container -->
  <div
    class="flex flex-col gap-2 rounded-lg bg-white p-3 shadow-sm dark:bg-gray-800"
  >
    @forelse ($schedules as $schedule)
      <!-- Schedule List Item -->
      <a
        wire:key="schedule-{{ $schedule->odoo_schedule_id }}"
        {{-- Link to the detail route (will be defined next) --}}
        href="{{ route('schedules.show', ['schedule' => $schedule->odoo_schedule_id]) }}"
        wire:navigate
        class="block rounded-md border border-gray-300 bg-gray-100 p-3 transition-colors duration-150 hover:bg-gray-200 dark:border-gray-600 dark:bg-gray-700 dark:hover:bg-gray-700/80"
      >
        <div class="flex flex-row items-center justify-between gap-4">
          <!-- Schedule Info -->
          <div class="flex flex-1 flex-col gap-1">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
              {{ $schedule->description ?? 'No Description' }}
            </p>
            <div
              class="flex flex-row gap-4 text-xs text-gray-500 dark:text-gray-400"
            >
              <span>Assigned Users: {{ $schedule->current_users_count }}</span>
              <span>
                Average hours per day:
                {{ $schedule->average_hours_day ?? 'N/A' }}
              </span>
            </div>
          </div>
          <!-- Right Arrow Icon -->
          <div class="flex-none">
            <svg
              class="size-5 text-gray-400 dark:text-gray-500"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                fill-rule="evenodd"
                d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z"
                clip-rule="evenodd"
              />
            </svg>
          </div>
        </div>
      </a>
    @empty
      <div
        class="rounded-md border border-gray-300 bg-white p-4 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
      >
        No schedules found matching your filters.
      </div>
    @endforelse
    <!-- Pagination Links -->
    @if ($schedules->hasPages())
      <div class="mt-4">
        {{ $schedules->links() }}
      </div>
    @endif
  </div>
</div>
