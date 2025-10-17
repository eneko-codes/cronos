@props([
  'leave',
])

<div class="flex max-w-sm flex-col gap-3">
  <div>
    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100">
      {{ $leave->leaveType ?? 'Leave' }}
    </h4>
    @if ($leave->context)
      <p class="text-xs text-gray-500 dark:text-gray-400">
        {{ $leave->context }}
      </p>
    @endif
  </div>

  <div class="space-y-1">
    @if ($leave->isHalfDay)
      <div class="text-sm text-gray-700 dark:text-gray-300">
        @if ($leave->timePeriod === 'morning')
          Morning half-day
        @elseif ($leave->timePeriod === 'afternoon')
          Afternoon half-day
        @else
            Half-day leave
        @endif
      </div>
      @if ($leave->halfDayTime && $leave->halfDayTime !== '—')
        <div class="text-xs text-gray-500 dark:text-gray-400">
          {{ $leave->halfDayTime }}
        </div>
      @endif
    @elseif ($leave->durationDays >= 1)
      <div class="text-sm text-gray-700 dark:text-gray-300">
        @if ($leave->durationDays == 1)
          Full day leave
        @else
          {{ $leave->durationText }}
          ({{ $leave->duration }} today)
        @endif
      </div>
    @else
      <div class="text-sm text-gray-700 dark:text-gray-300">
        {{ $leave->durationText ?: $leave->duration }}
      </div>
      @if ($leave->durationDays < 1 && $leave->timeRange && $leave->timeRange !== '00:00 - 00:00')
        <div class="text-xs text-gray-500 dark:text-gray-400">
          {{ $leave->timeRange }}
        </div>
      @endif
    @endif
  </div>

  <div>
    @if ($leave->isApproved())
      <x-badge variant="success" size="sm">Validated</x-badge>
    @elseif ($leave->isPending())
      <x-badge variant="warning" size="sm">Pending Approval</x-badge>
    @else
      <x-badge variant="secondary" size="sm">
        {{ ucfirst($leave->status) }}
      </x-badge>
    @endif
  </div>
</div>
