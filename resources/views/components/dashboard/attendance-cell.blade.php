@props([
  'attendance',
  'dayData',
  'futureTextClass' => 'text-gray-700 dark:text-gray-300',
])

@if ($attendance->hasData())
  <x-tooltip>
    <x-slot name="text">
      <div class="flex flex-col gap-1">
        @if (! empty($attendance->segments))
          @foreach ($attendance->segments as $segment)
            <div class="text-xs text-gray-600 dark:text-gray-200">
              {{ $segment['clock_in'] ?? '-' }} -
              {{ $segment['clock_out'] ?? 'Active' }}
              @if ($segment['duration'] !== '0h 0m')
                <span class="text-gray-500 dark:text-gray-400">
                  ({{ $segment['duration'] }})
                </span>
              @endif
            </div>
          @endforeach
        @else
          <span class="text-xs text-gray-600 dark:text-gray-200">
            Start: {{ $attendance->start ?? '-' }}
          </span>
          <span class="text-xs text-gray-600 dark:text-gray-200">
            End: {{ $attendance->end ?? '-' }}
          </span>
        @endif
      </div>
    </x-slot>
    <div class="flex flex-row items-center gap-2">
      <span class="{{ $futureTextClass }}">
        {{ $attendance->duration }}
      </span>

      @if ($attendance->hasOpenSegment)
        <x-badge
          variant="{{ $dayData->isToday() ? 'warning' : 'alert' }}"
          size="sm"
        >
          Active
        </x-badge>
      @endif

      @if ($attendance->isMixed)
        @if ($attendance->hasOffice)
          <x-badge variant="success" size="sm">In Office</x-badge>
        @endif

        @if ($attendance->hasRemote)
          <x-badge variant="info" size="sm">Remote</x-badge>
        @endif
      @elseif ($attendance->isRemote)
        <x-badge variant="info" size="sm">Remote</x-badge>
      @elseif (! $attendance->isRemote && ! empty($attendance->segments))
        <x-badge variant="success" size="sm">In Office</x-badge>
      @endif
    </div>
  </x-tooltip>
@endif
