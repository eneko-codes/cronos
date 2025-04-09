<div class="flex flex-col gap-4">
  <!-- Header: Search and Per Page -->
  <div
    class="flex flex-col items-start justify-between gap-2 border-b pb-3 md:flex-row md:items-center dark:border-gray-700"
  >
    <h1 class="text-xl font-bold">Browse Projects, Tasks & Entries</h1>
    <div
      class="flex w-full flex-col gap-2 md:w-auto md:flex-row md:items-center"
    >
      <!-- Search Input -->
      <input
        type="text"
        wire:model.live.debounce.300ms="search"
        placeholder="Search projects..."
        autofocus
        class="h-9 w-full max-w-xs rounded-md border border-gray-300 bg-gray-50 px-3 py-1 text-sm text-gray-900 outline-none placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500"
      />
      <!-- Per Page Selector -->
      <div class="flex w-full items-center gap-2">
        <span
          class="whitespace-nowrap text-sm text-gray-600 dark:text-gray-400"
        >
          Per page:
        </span>
        <x-input.select wire:model.live="perPage">
          <option value="15">15</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </x-input.select>
      </div>
    </div>
  </div>

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
          class="{{ $project->tasks_count > 0 ? 'rounded-t-md hover:cursor-pointer dark:hover:bg-gray-700/80' : 'rounded-md' }} flex flex-row items-center justify-between gap-4 p-2"
          @if ($project->tasks_count > 0) wire:click="toggleProject('{{ $project->proofhub_project_id }}')" @endif
        >
          <!-- Project Info -->
          <div class="flex flex-1 flex-col gap-1">
            <p class="text-sm font-semibold text-gray-800 dark:text-gray-100">
              {{ $project->name }}
            </p>
            <span class="text-xs text-gray-500 dark:text-gray-400">
              Tasks: {{ $project->tasks_count }}
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
          @if ($project->tasks_count > 0)
            <div class="flex-none">
              <svg
                class="{{ in_array($project->proofhub_project_id, $expandedProjects) ? 'rotate-180' : '' }} size-5 transform text-gray-500 transition-transform duration-200 dark:text-gray-400"
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

        <!-- Expanded Tasks Section (Inside Project Container) -->
        @if (in_array($project->proofhub_project_id, $expandedProjects))
          <div class="flex flex-col gap-2 p-3 pl-6">
            @isset($loadedTasks[$project->proofhub_project_id])
              @forelse ($loadedTasks[$project->proofhub_project_id] as $task)
                <!-- Task Container (Holds Header and Expanded Content) -->
                <div
                  wire:key="task-{{ $task->proofhub_task_id }}"
                  class="mb-2 rounded-md border border-gray-300 bg-white last:mb-0 dark:border-gray-500 dark:bg-gray-700"
                >
                  <!-- Task Header Row -->
                  <div
                    class="{{ $task->time_entries_count > 0 ? 'cursor-pointer rounded-t-md' : 'rounded-md' }} flex flex-row items-center justify-between gap-2 p-2"
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
                          class="{{ in_array($task->proofhub_task_id, $expandedTasks) ? 'rotate-180' : '' }} size-4 transform text-gray-500 transition-transform duration-200 dark:text-gray-400"
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
                      @isset($loadedTimeEntries[$task->proofhub_task_id])
                        @forelse ($loadedTimeEntries[$task->proofhub_task_id] as $entry)
                          <div
                            wire:key="entry-{{ $entry->proofhub_time_entry_id }}"
                            class="mb-1 text-xs last:mb-0"
                          >
                            <div class="flex justify-between">
                              <span class="font-semibold dark:text-gray-300">
                                {{ $entry->user->name ?? 'N/A' }}
                              </span>
                              <span class="text-gray-600 dark:text-gray-400">
                                {{ $entry->date->format('Y-m-d') }} -
                                {{
                                  \Carbon\CarbonInterval::seconds($entry->duration_seconds)
                                    ->cascade()
                                    ->format('%hh %im')
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
                            No time entries.
                          </p>
                        @endforelse
                      @else
                        <p
                          class="text-xs italic text-gray-500 dark:text-gray-400"
                        >
                          Loading entries...
                        </p>
                      @endisset
                    </div>
                  @endif
                </div>
              @empty
                <p class="text-sm italic text-gray-500 dark:text-gray-400">
                  No tasks found for this project.
                </p>
              @endforelse
            @else
              <p class="text-sm italic text-gray-500 dark:text-gray-400">
                Loading tasks...
              </p>
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
