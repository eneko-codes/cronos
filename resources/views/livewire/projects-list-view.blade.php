<div class="flex flex-col gap-4">
  <!-- Header Section -->
  <div
    class="flex flex-col items-start justify-start gap-4 border-b-2 pb-3 md:flex-row md:items-stretch dark:border-gray-800"
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
            placeholder="Search projects..."
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
            {{-- Native HTML select element --}}
            <select
              wire:model.change="sortBy"
              {{-- Classes matching the select in Settings --}}
              class="block h-8 w-40 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm dark:border-gray-700 dark:bg-gray-700"
            >
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
            </select>
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
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.change="filters.has_tasks"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has Tasks
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.change="filters.has_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has Time Entries
                  </span>
                </label>
                <hr class="!my-2 border-gray-200 dark:border-gray-700" />
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.change="filters.has_no_tasks"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has No Tasks
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.change="filters.has_no_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has No Time Entries
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-3 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.change="filters.has_direct_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
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

  <!-- Project Count -->
  @if ($projects->total() > 0)
    <div class="text-sm text-gray-600 dark:text-gray-400">
      Showing {{ $projects->total() }} projects
    </div>
  @endif

  <!-- Project List Container -->
  <div class="flex flex-col gap-2">
    @forelse ($projects as $project)
      <!-- Project List Item -->
      <a
        wire:key="project-{{ $project->proofhub_project_id }}"
        href="{{ route('projects.show', ['project' => $project->proofhub_project_id]) }}"
        wire:navigate
        class="block rounded-md border border-gray-300 bg-white p-3 transition-colors duration-150 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700/80"
      >
        <div class="flex flex-row items-center justify-between gap-4">
          <!-- Project Info -->
          <div class="flex flex-1 flex-col gap-1">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
              {{ $project->name }}
            </p>
            <div
              class="flex flex-row gap-4 text-xs text-gray-500 dark:text-gray-400"
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
        class="rounded-md border border-gray-300 bg-white p-4 text-center text-gray-500 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-400"
      >
        No projects found matching your search.
      </div>
    @endforelse
  </div>

  <!-- Pagination Links -->
  <div class="mt-4">
    {{ $projects->links() }}
  </div>
</div>
