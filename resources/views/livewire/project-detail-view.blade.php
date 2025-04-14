<div class="flex flex-col gap-6 p-4 md:p-6">
  <!-- Back Link -->
  <div class="mb-2">
    <a
      href="{{ route('projects.list') }}"
      wire:navigate
      class="flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300"
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
      Back to Projects
    </a>
  </div>

  <!-- Project Header -->
  <div class="border-b-2 pb-4 dark:border-gray-700">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
      {{ $project->name }}
    </h1>
    <div
      class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400"
    >
      <span>ID: {{ $project->proofhub_project_id }}</span>
      <span>|</span>
      <span>Created:</span>
      <x-tooltip text="{{ $project->created_at->format('Y-m-d H:i') }}">
        <span>{{ $project->created_at->diffForHumans() }}</span>
      </x-tooltip>
      <span>|</span>
      <span>Updated:</span>
      <x-tooltip text="{{ $project->updated_at->format('Y-m-d H:i') }}">
        <span>{{ $project->updated_at->diffForHumans() }}</span>
      </x-tooltip>
    </div>
    @if ($project->users->isNotEmpty())
      <div class="mt-3 flex flex-wrap gap-1">
        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">
          Members:
        </span>
        @foreach ($project->users as $user)
          <x-badge size="sm" variant="primary">{{ $user->name }}</x-badge>
        @endforeach
      </div>
    @endif
  </div>

  <!-- Section: Project-Level Time Entries -->
  <div class="flex flex-col gap-3">
    <h2 class="text-lg font-medium text-gray-800 dark:text-gray-200">
      Project Time Entries
    </h2>
    @if ($projectTimeEntries->isNotEmpty())
      <div class="space-y-2">
        @foreach ($projectTimeEntries as $entry)
          <div
            wire:key="project-entry-{{ $entry->proofhub_time_entry_id }}"
            class="rounded-md border border-gray-300 bg-white p-2 dark:border-gray-600 dark:bg-gray-800"
          >
            <div class="flex flex-row items-start justify-between gap-2">
              <div class="flex flex-1 flex-col gap-0.5">
                <span
                  class="text-sm font-medium text-gray-700 dark:text-gray-100"
                >
                  {{ $entry->user->name ?? 'N/A' }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ $entry->date->format('Y-m-d') }} -
                  {{ \Carbon\CarbonInterval::seconds($entry->duration_seconds)->cascade()->format('%hh %im') }}
                </span>
                @if ($entry->description)
                  <p
                    class="mt-1 text-xs italic text-gray-600 dark:text-gray-400"
                  >
                    {{ Str::limit($entry->description, 200) }}
                  </p>
                @endif
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @else
      <p class="text-sm italic text-gray-500 dark:text-gray-400">
        No time entries directly associated with this project.
      </p>
    @endif
  </div>

  <!-- Section: Tasks -->
  <div class="flex flex-col gap-3">
    <h2 class="text-lg font-medium text-gray-800 dark:text-gray-200">
      Tasks ({{ $tasks->count() }})
    </h2>
    @if ($tasks->isNotEmpty())
      <div class="flex flex-col gap-2">
        @foreach ($tasks as $task)
          <!-- Task Container -->
          <div
            wire:key="task-{{ $task->proofhub_task_id }}"
            class="rounded-md border border-gray-300 bg-white dark:border-gray-500 dark:bg-gray-700"
          >
            <!-- Task Header Row -->
            <div
              class="{{ $task->time_entries_count > 0 ? 'cursor-pointer rounded-t-md' : 'rounded-md' }} flex flex-row items-center justify-between gap-2 p-2 hover:bg-gray-50 dark:hover:bg-gray-600/50"
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
                wire:key="task-entries-{{ $task->proofhub_task_id }}"
                class="rounded-b-md border-t border-gray-300 bg-gray-50 p-2 pl-4 dark:border-gray-500 dark:bg-gray-600/50"
              >
                @isset($this->taskTimeEntries[$task->proofhub_task_id])
                  @forelse ($this->taskTimeEntries[$task->proofhub_task_id] as $entry)
                    <div
                      wire:key="entry-{{ $entry->proofhub_time_entry_id }}"
                      class="mb-1 border-b border-gray-200 pb-1 text-xs last:mb-0 last:border-b-0 last:pb-0 dark:border-gray-600"
                    >
                      <div class="flex justify-between">
                        <span class="font-semibold dark:text-gray-300">
                          {{ $entry->user->name ?? 'N/A' }}
                        </span>
                        <span class="text-gray-600 dark:text-gray-400">
                          {{ $entry->date->format('Y-m-d') }} -
                          {{ \Carbon\CarbonInterval::seconds($entry->duration_seconds)->cascade()->format('%hh %im') }}
                        </span>
                      </div>
                      @if ($entry->description)
                        <p class="italic text-gray-600 dark:text-gray-400">
                          {{ Str::limit($entry->description, 150) }}
                        </p>
                      @endif
                    </div>
                  @empty
                    <p class="text-xs italic text-gray-500 dark:text-gray-400">
                      No time entries loaded for this task.
                    </p>
                  @endforelse
                @else
                  {{-- Should not happen if toggle logic is correct, but good fallback --}}
                  <p class="text-xs italic text-gray-500 dark:text-gray-400">
                    Loading time entries...
                  </p>
                @endisset
              </div>
            @endif
          </div>
        @endforeach
      </div>
    @else
      <p class="text-sm italic text-gray-500 dark:text-gray-400">
        This project has no associated tasks.
      </p>
    @endif
  </div>
</div>
