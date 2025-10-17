@props([
  'worked',
  'futureTextClass' => 'text-gray-700 dark:text-gray-300',
])

@if ($worked->hasData())
  <div class="flex flex-col gap-1">
    <x-tooltip>
      <x-slot name="text">
        <div class="flex flex-col gap-2">
          @if (! empty($worked->detailedEntries))
            @foreach ($worked->detailedEntries as $entry)
              <div class="{{ ! $loop->last ? 'mb-1 ' : '' }} flex flex-col">
                <div class="mb-1 flex items-start justify-between gap-2">
                  <span
                    class="flex-1 text-xs font-medium break-words text-gray-800 dark:text-gray-100"
                  >
                    {{ $entry['project'] ?? '' }}
                  </span>
                  <span
                    class="flex-shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-xs whitespace-nowrap text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                  >
                    {{ $entry['duration'] ?? '' }}
                  </span>
                </div>
                @if ($entry['task'])
                  <span
                    class="mb-0.5 text-xs break-words text-gray-600 dark:text-gray-300"
                  >
                    {{ $entry['task'] }}
                  </span>
                @endif

                @if ($entry['description'])
                  <span
                    class="text-xs break-words text-gray-500 italic dark:text-gray-400"
                  >
                    {{ Illuminate\Support\Str::limit($entry['description'], 100) }}
                  </span>
                @endif
              </div>
            @endforeach
          @elseif (! empty($worked->projects))
            <div class="flex flex-col">
              @foreach ($worked->projects as $project)
                <div class="{{ ! $loop->last ? 'mb-2' : '' }}">
                  <span
                    class="text-xs font-medium break-words text-gray-800 dark:text-gray-100"
                  >
                    {{ $project['title'] ?? '' }}
                  </span>
                  @if (! empty($project['tasks']))
                    <div
                      class="mt-1 text-xs break-words text-gray-600 dark:text-gray-300"
                    >
                      {{ collect($project['tasks'])->join(', ') }}
                    </div>
                  @endif
                </div>
              @endforeach
            </div>
          @else
            <span class="text-xs text-gray-500 dark:text-gray-400">
              No data
            </span>
          @endif
        </div>
      </x-slot>
      <span class="{{ $futureTextClass }}">
        {{ $worked->duration }}
      </span>
    </x-tooltip>
  </div>
@endif
