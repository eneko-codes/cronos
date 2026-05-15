<div class="flex flex-col gap-4">
  <!-- Header Section -->
  <div
    class="flex flex-col items-start justify-start gap-4 rounded-xl border border-gray-200 bg-white p-4 shadow-sm md:flex-row md:items-stretch dark:border-gray-700 dark:bg-gray-800"
  >
    <div class="flex flex-1 flex-col items-start gap-4 md:flex-row">
      <div
        class="flex w-full flex-col items-stretch gap-3 md:w-auto md:flex-row md:items-center"
      >
        <!-- Search Input -->
        <div class="flex-grow md:flex-grow-0">
          <x-input
            type="text"
            wire:model.live.debounce.300ms="search"
            placeholder="Search projects..."
            autofocus
            class="max-w-xs"
          />
        </div>

        <!-- Control Group (Sort, Filter) -->
        <div class="flex items-center gap-2">
          <!-- Sort Controls -->
          <div class="flex items-center gap-2">
            <span
              class="hidden text-sm font-medium text-gray-700 sm:inline dark:text-gray-300"
            >
              Sort by:
            </span>
            <x-select wire:model.change="sortBy" size="sm" class="w-44">
              <option value="name_asc">Name (A-Z)</option>
              <option value="name_desc">Name (Z-A)</option>
              <option value="created_at_desc">Created (Newest)</option>
              <option value="created_at_asc">Created (Oldest)</option>
              <option value="updated_at_desc">Updated (Newest)</option>
              <option value="updated_at_asc">Updated (Oldest)</option>
              <option value="tasks_count_desc">Tasks (Most)</option>
              <option value="tasks_count_asc">Tasks (Fewest)</option>
              <option value="project_time_entries_count_desc">
                Direct Entries (Most)
              </option>
              <option value="project_time_entries_count_asc">
                Direct Entries (Fewest)
              </option>
            </x-select>
          </div>

          <!-- Filter Dropdown -->
          <div class="relative">
            {{-- Use the reusable dropdown component --}}
            <x-dropdown-menu>
              {{-- Trigger Slot (Content Only) --}}
              <x-slot name="trigger">
                <span>Filters</span>
                @php
                  $activeFilterCount = collect($filters)
                    ->filter()
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
                <label
                  class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_tasks"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-500 dark:focus:ring-blue-400"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">
                    Has Tasks
                  </span>
                </label>
                <label
                  class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-500 dark:focus:ring-blue-400"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">
                    Has Time Entries
                  </span>
                </label>
                <hr class="!my-2 border-gray-200 dark:border-gray-700" />
                <label
                  class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_no_tasks"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-500 dark:focus:ring-blue-400"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">
                    Has No Tasks
                  </span>
                </label>
                <label
                  class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_no_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-500 dark:focus:ring-blue-400"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">
                    Has No Time Entries
                  </span>
                </label>
                <label
                  class="flex items-center gap-2 rounded-lg px-3 py-2 hover:bg-gray-50 dark:hover:bg-gray-700/50"
                >
                  <input
                    type="checkbox"
                    wire:model.change="filters.has_direct_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-500 dark:focus:ring-blue-400"
                  />
                  <span class="text-sm text-gray-700 dark:text-gray-300">
                    Has Direct Entries
                  </span>
                </label>
              </div>
            </x-dropdown-menu>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Project List Container -->
  <div
    class="flex flex-col gap-2 rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
  >
    @if ($loading ?? false)
      @include('livewire.placeholders.projects-list-view-skeleton')
    @else
      @forelse ($projects as $project)
        <!-- Project List Item -->
        <a
          wire:key="project-{{ $project->proofhub_project_id }}"
          href="{{ route('projects.show', ['project' => $project->proofhub_project_id]) }}"
          wire:navigate
          class="block rounded-lg border border-gray-200 bg-gray-50 p-3 transition-colors duration-150 hover:bg-gray-100 dark:border-gray-700 dark:bg-gray-700/50 dark:hover:bg-gray-700"
        >
          <div class="flex flex-row items-center justify-between gap-4">
            <!-- Project Info -->
            <div class="flex flex-1 flex-col gap-1">
              <p class="text-sm font-semibold text-gray-900 dark:text-gray-100">
                {{ $project->title }}
              </p>
              <div
                class="flex flex-row gap-4 text-xs text-gray-600 dark:text-gray-400"
              >
                <span>Tasks: {{ $project->tasks_count }}</span>
                <span>
                  Time Entries: {{ $project->project_time_entries_count }}
                </span>
                <span>Assigned Users: {{ $project->users_count }}</span>
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
          class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-center text-gray-600 dark:border-gray-700 dark:bg-gray-700/50 dark:text-gray-400"
        >
          No projects found matching your search.
        </div>
      @endforelse
      <!-- Pagination Links -->
      <div class="mt-4">
        {{ $projects->links() }}
      </div>
    @endif
  </div>
</div>
