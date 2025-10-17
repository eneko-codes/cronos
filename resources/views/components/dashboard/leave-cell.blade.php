@props([
  'leave',
  'futureTextClass' => 'text-gray-700 dark:text-gray-300',
])

@if ($leave->hasData())
  <div class="flex items-center gap-2">
    @if ($leave->isApproved())
      <x-tooltip>
        <x-slot name="text">
          <x-dashboard.leave-tooltip :leave="$leave" />
        </x-slot>
        <span class="{{ $futureTextClass }}">
          {{ $leave->duration }}
        </span>
      </x-tooltip>
      <x-badge variant="info" size="sm">
        {{ $leave->leaveType ?? 'Leave' }}
      </x-badge>
    @elseif ($leave->isPending())
      <x-tooltip>
        <x-slot name="text">
          <x-dashboard.leave-tooltip :leave="$leave" />
        </x-slot>
        <span class="{{ $futureTextClass }}">
          {{ $leave->duration }}
        </span>
      </x-tooltip>
      <x-badge variant="info" size="sm">
        {{ $leave->leaveType ?? 'Leave' }}
      </x-badge>
      <x-badge variant="warning" size="sm">Pending</x-badge>
    @elseif ($leave->isCancelled())
      <x-tooltip
        text="Leave request was {{ $leave->status === 'cancel' ? 'cancelled' : 'refused' }}"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          class="size-4 text-red-500"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
          stroke-width="2"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
          />
        </svg>
      </x-tooltip>
    @endif
  </div>
@endif
