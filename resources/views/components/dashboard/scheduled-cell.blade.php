@props([
  'schedule',
  'futureTextClass' => 'text-gray-700 dark:text-gray-300',
])

<div class="flex flex-col gap-1">
  <x-tooltip>
    <x-slot name="text">
      <div class="flex flex-col">
        @if (! empty($schedule->slots))
          @if ($schedule->scheduleName)
            <div class="mb-1">
              <span
                class="text-sm font-semibold text-gray-800 dark:text-gray-100"
              >
                {{ $schedule->scheduleName }}
              </span>
            </div>
          @endif

          <div class="space-y-1">
            @foreach ($schedule->slots as $slot)
              <div class="text-xs text-gray-600 dark:text-gray-200">
                {{ $slot }}
              </div>
            @endforeach
          </div>
        @else
          <span class="text-xs text-gray-500 dark:text-gray-400">
            No schedule data available
          </span>
        @endif
      </div>
    </x-slot>
    <span class="{{ $futureTextClass }}">
      {{ $schedule->duration }}
    </span>
  </x-tooltip>
</div>
