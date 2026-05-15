<div class="flex flex-col gap-4">
  <!-- Header Section -->
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
            placeholder="Search leave types..."
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
              <option value="name_asc">Name (A-Z)</option>
              <option value="name_desc">Name (Z-A)</option>
              <option value="created_at_desc">Created (Newest)</option>
              <option value="created_at_asc">Created (Oldest)</option>
              <option value="updated_at_desc">Updated (Newest)</option>
              <option value="updated_at_asc">Updated (Oldest)</option>
              <option value="leaves_count_desc">Usage (Most)</option>
              <option value="leaves_count_asc">Usage (Fewest)</option>
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
                    ->reject(fn ($value) => is_null($value) || $value === false) // Count only true or non-null values (for tri-state)
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
                <div class="px-3 py-1.5">
                  <label
                    class="text-xs font-medium text-gray-700 dark:text-gray-300"
                  >
                    Status
                  </label>
                  <div class="flex flex-col space-y-1">
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.active"
                        name="active_filter"
                        value="{{ null }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        All
                      </span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.active"
                        name="active_filter"
                        value="{{ true }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        Active
                      </span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.active"
                        name="active_filter"
                        value="{{ false }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        Inactive
                      </span>
                    </label>
                  </div>
                </div>
                <hr class="!my-2 border-gray-200 dark:border-gray-700" />
                <div class="px-3 py-1.5">
                  <label
                    class="text-xs font-medium text-gray-700 dark:text-gray-300"
                  >
                    Paid Status
                  </label>
                  <div class="mt-1 flex flex-col space-y-1">
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.is_unpaid"
                        name="unpaid_filter"
                        value="{{ null }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        All
                      </span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.is_unpaid"
                        name="unpaid_filter"
                        value="{{ false }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        Paid
                      </span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.is_unpaid"
                        name="unpaid_filter"
                        value="{{ true }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        Unpaid
                      </span>
                    </label>
                  </div>
                </div>
                <hr class="!my-2 border-gray-200 dark:border-gray-700" />
                <div class="px-3 py-1.5">
                  <label
                    class="text-xs font-medium text-gray-700 dark:text-gray-300"
                  >
                    Allocation
                  </label>
                  <div class="mt-1 flex flex-col space-y-1">
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.requires_allocation"
                        name="allocation_filter"
                        value="{{ null }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        All
                      </span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.requires_allocation"
                        name="allocation_filter"
                        value="{{ true }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        Requires Allocation
                      </span>
                    </label>
                    <label class="flex items-center gap-2">
                      <input
                        type="radio"
                        wire:model.change="filters.requires_allocation"
                        name="allocation_filter"
                        value="{{ false }}"
                        class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                      />
                      <span class="text-xs text-gray-700 dark:text-gray-300">
                        Doesn't Require
                      </span>
                    </label>
                  </div>
                </div>
                <hr class="!my-2 border-gray-200 dark:border-gray-700" />
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_user_leaves"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has Usage
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_no_user_leaves"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has No Usage
                  </span>
                </label>
              </div>
            </x-dropdown-menu>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Leave Type List Container -->
  <div
    class="flex flex-col gap-2 rounded-lg bg-white p-3 shadow-sm dark:bg-gray-800"
  >
    @forelse ($leaveTypes as $leaveType)
      <!-- Leave Type List Item -->
      <div
        wire:key="leave-type-{{ $leaveType->odoo_leave_type_id }}"
        class="block border-b border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-800"
      >
        <div
          class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between"
        >
          <!-- Leave Type Info -->
          <div class="flex flex-1 flex-col gap-1">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
              {{ $leaveType->name }}
            </p>
            <div
              class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
            >
              <span>
                Request Unit: {{ $leaveType->request_unit ?? 'N/A' }}
              </span>
              <span>
                Validation: {{ $leaveType->validation_type ?? 'N/A' }}
              </span>
              <span>Usage Count: {{ $leaveType->leaves_count }}</span>
            </div>
          </div>
          <!-- Status Badges -->
          <div class="flex flex-none flex-wrap items-center gap-2">
            <x-badge
              :variant="$leaveType->active ? 'success' : 'alert'"
              size="sm"
            >
              {{ $leaveType->active ? 'Active' : 'Inactive' }}
            </x-badge>
            <x-badge
              :variant="$leaveType->is_unpaid ? 'warning' : 'info'"
              size="sm"
            >
              {{ $leaveType->is_unpaid ? 'Unpaid' : 'Paid' }}
            </x-badge>
            <x-badge
              :variant="$leaveType->requires_allocation ? 'primary' : 'default'"
              size="sm"
            >
              {{ $leaveType->requires_allocation ? 'Needs Allocation' : 'No Allocation Needed' }}
            </x-badge>
            <x-badge
              :variant="$leaveType->limit ? 'alert' : 'default'"
              size="sm"
            >
              {{ $leaveType->limit ? 'Limited' : 'Unlimited' }}
            </x-badge>
          </div>
        </div>
      </div>
    @empty
      <div
        class="rounded-md border border-gray-300 bg-white p-4 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
      >
        No leave types found matching your search or filters.
      </div>
    @endforelse

    <!-- Pagination Links -->
    @if ($leaveTypes->hasPages())
      <div class="mt-4">
        {{ $leaveTypes->links() }}
      </div>
    @endif
  </div>
</div>
