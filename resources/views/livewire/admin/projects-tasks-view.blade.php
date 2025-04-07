<div class="flex h-[calc(100vh-10rem)] flex-col gap-4">
  <div class="flex items-center justify-between">
    <h1 class="text-xl font-bold">Projects, Tasks & Time Entries</h1>

    <div class="flex items-center gap-4">
      <!-- Search Input -->
      <input
        type="text"
        wire:model.live.debounce.200ms="search"
        placeholder="Search projects..."
        autofocus
        class="h-9 w-full max-w-48 rounded-md border border-gray-300 bg-gray-50 px-3 py-1 text-sm text-gray-900 outline-none placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500"
      />

      <!-- Per Page Selector for Projects -->
      <div class="flex items-center gap-2">
        <span class="text-sm text-gray-600 dark:text-gray-400">
          Projects per page:
        </span>
        <x-input.select wire:model.live="perPage" class="w-20">
          <option value="15">15</option>
          <option value="25">25</option>
          <option value="50">50</option>
          <option value="100">100</option>
        </x-input.select>
      </div>
    </div>
  </div>

  <!-- Column Container -->
  <div
    class="flex h-full flex-1 gap-0 overflow-hidden rounded-lg border border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-800"
  >
    <!-- Projects Column -->
    <div
      class="scrollbar-thin h-full w-1/3 min-w-[250px] overflow-y-auto border-r border-gray-300 dark:border-gray-700"
    >
      <ul class="divide-y divide-gray-200 dark:divide-gray-700">
        @forelse ($projects as $project)
          @if (! empty($project->proofhub_project_id))
            <li
              {{-- onclick='$dispatch("project-selected", {{ $project->proofhub_project_id }})' --}}
              wire:click="$dispatch('project-selected', { proofhubProjectId: {{ $project->proofhub_project_id }} })"
              class="{{ $selectedProofhubProjectId === $project->proofhub_project_id ? 'bg-blue-100 dark:bg-blue-900' : '' }} cursor-pointer px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700"
            >
              <div class="flex items-center justify-between">
                <span class="font-medium text-gray-800 dark:text-gray-200">
                  {{ $project->name }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ $project->tasks_count }} tasks
                </span>
              </div>
              <span class="block text-xs text-gray-500 dark:text-gray-400">
                ID: {{ $project->proofhub_project_id }}
              </span>
              @if ($project->users->isNotEmpty())
                <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  Assigned:
                  {{ $project->users->pluck('name')->implode(', ') }}
                </div>
              @endif
            </li>
          @endif
        @empty
          <li class="p-4 text-center text-gray-500 dark:text-gray-400">
            No projects found.
          </li>
        @endforelse
      </ul>
      @if ($projects->hasPages())
        <div
          class="border-t border-gray-200 bg-gray-100 p-2 dark:border-gray-700 dark:bg-gray-800"
        >
          {{ $projects->links('vendor.livewire.simple-tailwind') }}
        </div>
      @endif
    </div>

    <!-- Tasks Column -->
    <div
      class="scrollbar-thin {{ $selectedProofhubProjectId ? 'border-r border-gray-300 dark:border-gray-700' : '' }} h-full w-1/3 min-w-[250px] overflow-y-auto"
    >
      @if ($this->selectedProjectTasks)
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
          @forelse ($this->selectedProjectTasks as $task)
            @if (! empty($task->proofhub_task_id))
              <li
                {{-- onclick='$dispatch("task-selected", {{ $task->proofhub_task_id }})' --}}
                wire:click="$dispatch('task-selected', { proofhubTaskId: {{ $task->proofhub_task_id }} })"
                class="{{ $selectedProofhubTaskId === $task->proofhub_task_id ? 'bg-blue-100 dark:bg-blue-900' : '' }} cursor-pointer px-4 py-3 hover:bg-gray-100 dark:hover:bg-gray-700"
              >
                <div class="flex items-center justify-between">
                  <span class="font-medium text-gray-800 dark:text-gray-200">
                    {{ $task->name }}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $task->time_entries_count }} entries
                  </span>
                </div>
                <span class="block text-xs text-gray-500 dark:text-gray-400">
                  ID: {{ $task->proofhub_task_id }}
                </span>
                @if ($task->users->isNotEmpty())
                  <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Assigned:
                    {{ $task->users->pluck('name')->implode(', ') }}
                  </div>
                @endif
              </li>
            @endif
          @empty
            <li class="p-4 text-center text-gray-500 dark:text-gray-400">
              No tasks for this project.
            </li>
          @endforelse
        </ul>
      @elseif ($selectedProofhubProjectId)
        <div
          class="flex h-full items-center justify-center p-4 text-gray-500 dark:text-gray-400"
        >
          Loading tasks...
        </div>
      @else
        <div
          class="flex h-full items-center justify-center p-4 text-gray-500 dark:text-gray-400"
        >
          Select a project to see tasks.
        </div>
      @endif
    </div>

    <!-- Time Entries Column -->
    <div class="scrollbar-thin h-full w-1/3 min-w-[300px] overflow-y-auto">
      @if ($this->selectedTaskTimeEntries)
        <ul class="divide-y divide-gray-200 dark:divide-gray-700">
          @forelse ($this->selectedTaskTimeEntries as $entry)
            <li class="px-4 py-3">
              <div class="mb-1 flex items-center justify-between">
                <span class="font-medium text-gray-800 dark:text-gray-200">
                  {{ $entry->user->name ?? 'N/A' }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  {{ $entry->date->format('Y-m-d') }}
                </span>
              </div>
              <div class="mb-1 flex items-center justify-between">
                <span class="text-sm text-gray-700 dark:text-gray-300">
                  {{ \Carbon\CarbonInterval::seconds($entry->duration_seconds)->cascade()->format('%hh %im') }}
                </span>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                  ID: {{ $entry->proofhub_time_entry_id }}
                </span>
              </div>
              @if ($entry->description)
                <p class="mt-1 text-xs italic text-gray-600 dark:text-gray-400">
                  {{ Str::limit($entry->description, 100) }}
                </p>
              @endif
            </li>
          @empty
            <li class="p-4 text-center text-gray-500 dark:text-gray-400">
              No time entries for this task.
            </li>
          @endforelse
        </ul>
      @elseif ($selectedProofhubTaskId)
        <div
          class="flex h-full items-center justify-center p-4 text-gray-500 dark:text-gray-400"
        >
          Loading time entries...
        </div>
      @else
        <div
          class="flex h-full items-center justify-center p-4 text-gray-500 dark:text-gray-400"
        >
          Select a task to see time entries.
        </div>
      @endif
    </div>
  </div>
</div>
