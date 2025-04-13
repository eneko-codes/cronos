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
              wire:model.live="sortBy"
              {{-- Classes matching the select in Settings --}}
              class="block h-8 w-40 rounded-md border border-gray-300 bg-gray-200 text-sm dark:border-gray-700 dark:bg-gray-700"
            >
              <option value="name_asc">Name (A-Z)</option>
              <option value="name_desc">Name (Z-A)</option>
              <option value="created_at_desc">Newest</option>
              <option value="created_at_asc">Oldest</option>
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
                <label class="flex items-center gap-2 rounded px-2 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.live="filters.has_tasks"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has Tasks
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-2 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.live="filters.has_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has Time Entries
                  </span>
                </label>
                <hr class="!my-2 border-gray-200 dark:border-gray-700" />
                <label class="flex items-center gap-2 rounded px-2 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.live="filters.has_no_tasks"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has No Tasks
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-2 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.live="filters.has_no_time_entries"
                    class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:ring-offset-gray-800 dark:focus:ring-indigo-600"
                  />
                  <span class="text-xs text-gray-700 dark:text-gray-300">
                    Has No Time Entries
                  </span>
                </label>
                <label class="flex items-center gap-2 rounded px-2 py-1.5">
                  <input
                    {{-- Native HTML checkbox --}}
                    type="checkbox"
                    wire:model.live="filters.has_direct_time_entries"
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

  <!-- Accordion List Container -->
  <div class="flex flex-col gap-2 overflow-y-auto">
    @forelse ($projects as $project)
      <!-- Project Container (Holds Header and Expanded Content) -->
      <div
        wire:key="project-{{ $project->proofhub_project_id }}"
        class="rounded-md border border-gray-300 bg-white dark:border-gray-600 dark:bg-gray-800"
      >
        <!-- Project Header Row -->
        <div
          class="{{ $project->tasks_count > 0 || $project->project_time_entries_count > 0 ? "rounded-t-md hover:cursor-pointer dark:hover:bg-gray-700/80" : "rounded-md" }} flex flex-row items-center justify-between gap-4 p-2"
          @if ($project->tasks_count > 0 || $project->project_time_entries_count > 0)
              wire:click="toggleProject('{{ $project->proofhub_project_id }}')"
          @endif
        >
          <!-- Project Info -->
          <div class="flex flex-1 flex-col gap-1">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
              {{ $project->name }}
            </p>
            <span class="text-xs text-gray-500 dark:text-gray-400">
              Tasks: {{ $project->tasks_count }} | Project Entries:
              {{ $project->project_time_entries_count }}
            </span>
            @if ($project->users->isNotEmpty())
              <div class="flex flex-wrap gap-1">
                @foreach ($project->users as $user)
                  <x-badge size="sm" variant="primary">
                    {{ $user->name }}
                  </x-badge>
                @endforeach
              </div>
            @endif
          </div>
          <!-- Toggle Arrow -->
          @if ($project->tasks_count > 0 || $project->project_time_entries_count > 0)
            <div class="flex-none">
              <svg
                class="{{ in_array($project->proofhub_project_id, $expandedProjects) ? "rotate-180" : "" }} size-5 transform text-gray-500 transition-transform duration-200 dark:text-gray-400"
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 20 20"
                fill="currentColor"
              >
                <path
                  fill-rule="evenodd"
                  d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.06z"
                  clip-rule="evenodd"
                />
              </svg>
            </div>
          @endif
        </div>

        <!-- Expanded Content: Project Entries and Tasks -->
        @if (in_array($project->proofhub_project_id, $expandedProjects))
          <div class="flex flex-col gap-2 p-3 pl-6">
            {{-- Loop through Project-Level Time Entries --}}
            @isset($this->projectTimeEntries[$project->proofhub_project_id])
              @forelse ($this->projectTimeEntries[$project->proofhub_project_id] as $entry)
                <div
                  {{-- Individual Entry Container (styled like a task) --}}
                  wire:key="project-entry-{{ $entry->proofhub_time_entry_id }}"
                  class="mb-2 rounded-md border border-gray-300 bg-white p-2 last:mb-0 dark:border-gray-500 dark:bg-gray-700"
                >
                  <div class="flex flex-row items-start justify-between gap-2">
                    <!-- Entry Info -->
                    <div class="flex flex-1 flex-col gap-0.5">
                      <span
                        class="text-sm font-medium text-gray-700 dark:text-gray-100"
                      >
                        {{ $entry->user->name ?? "N/A" }}
                      </span>
                      <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ $entry->date->format("Y-m-d") }} -
                        {{
                            \Carbon\CarbonInterval::seconds($entry->duration_seconds)
                                ->cascade()
                                ->format("%hh %im")
                        }}
                      </span>
                      @if ($entry->description)
                        <p
                          class="mt-1 text-xs italic text-gray-600 dark:text-gray-400"
                        >
                          {{ Str::limit($entry->description, 150) }}
                        </p>
                      @endif
                    </div>
                    <!-- No Toggle Arrow Needed -->
                  </div>
                </div>
              @empty
                @if ($project->tasks_count == 0 && $project->project_time_entries_count > 0)
                  <p class="text-xs italic text-gray-500 dark:text-gray-400">
                    No project-level time entries found (expected
                    {{ $project->project_time_entries_count }}).
                  </p>
                @endif
              @endforelse
            @endisset

            {{-- End Project-Level Time Entries Loop --}}

            {{-- Loop through Tasks --}}
            @isset($this->tasks[$project->proofhub_project_id])
              @forelse ($this->tasks[$project->proofhub_project_id] as $task)
                <!-- Task Container (Holds Header and Expanded Content) -->
                <div
                  wire:key="task-{{ $task->proofhub_task_id }}"
                  class="mb-2 rounded-md border border-gray-300 bg-white last:mb-0 dark:border-gray-500 dark:bg-gray-700"
                >
                  <!-- Task Header Row -->
                  <div
                    class="{{ $task->time_entries_count > 0 ? "cursor-pointer rounded-t-md" : "rounded-md" }} flex flex-row items-center justify-between gap-2 p-2"
                    @if ($task->time_entries_count > 0) wire:click="toggleTask('{{ $task->proofhub_task_id }}')" @endif
                  >
                    <!-- Task Info -->
                    <div class="flex flex-1 flex-col gap-0.5">
                      <span
                        class="text-sm font-medium text-gray-700 dark:text-gray-100"
                      >
                        {{ $task->name }}
                      </span>
                      <span class="text-xs text-gray-500 dark:text-gray-400">
                        Entries: {{ $task->time_entries_count }}
                      </span>
                      @if ($task->users->isNotEmpty())
                        <div class="mt-1 flex flex-wrap gap-1">
                          @foreach ($task->users as $user)
                            <x-badge size="sm" variant="info">
                              {{ $user->name }}
                            </x-badge>
                          @endforeach
                        </div>
                      @endif
                    </div>
                    <!-- Task Toggle Arrow -->
                    @if ($task->time_entries_count > 0)
                      <div class="flex-none">
                        <svg
                          class="{{ in_array($task->proofhub_task_id, $expandedTasks) ? "rotate-180" : "" }} size-4 transform text-gray-500 transition-transform duration-200 dark:text-gray-400"
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.06z"
                            clip-rule="evenodd"
                          />
                        </svg>
                      </div>
                    @endif
                  </div>

                  <!-- Expanded Time Entries Section (Inside Task Container) -->
                  @if (in_array($task->proofhub_task_id, $expandedTasks))
                    <div
                      class="rounded-b-md border-t border-gray-300 bg-gray-50 p-2 pl-4 dark:border-gray-500 dark:bg-gray-600/50"
                    >
                      @isset($this->taskTimeEntries[$task->proofhub_task_id])
                        @forelse ($this->taskTimeEntries[$task->proofhub_task_id] as $entry)
                          <div
                            wire:key="entry-{{ $entry->proofhub_time_entry_id }}"
                            class="mb-1 text-xs last:mb-0"
                          >
                            <div class="flex justify-between">
                              <span class="font-semibold dark:text-gray-300">
                                {{ $entry->user->name ?? "N/A" }}
                              </span>
                              <span class="text-gray-600 dark:text-gray-400">
                                {{ $entry->date->format("Y-m-d") }} -
                                {{
                                    \Carbon\CarbonInterval::seconds($entry->duration_seconds)
                                        ->cascade()
                                        ->format("%hh %im")
                                }}
                              </span>
                            </div>
                            @if ($entry->description)
                              <p
                                class="italic text-gray-600 dark:text-gray-400"
                              >
                                {{ Str::limit($entry->description, 150) }}
                              </p>
                            @endif
                          </div>
                        @empty
                          <p
                            class="text-xs italic text-gray-500 dark:text-gray-400"
                          >
                            No time entries for this task.
                          </p>
                        @endforelse
                      @endisset
                    </div>
                  @endif
                </div>
              @empty
                <p class="text-sm italic text-gray-500 dark:text-gray-400">
                  No tasks found for this project.
                </p>
              @endforelse
            @endisset
          </div>
        @endif
      </div>
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
