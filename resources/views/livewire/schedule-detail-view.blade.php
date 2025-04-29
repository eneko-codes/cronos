<div class="flex flex-col gap-6">
  <!-- Back button -->
  <a
    href="{{ route('schedules.list') }}"
    wire:navigate
    class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 whitespace-nowrap rounded-lg bg-gray-200/75 px-2 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-200 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-100"
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
    Back to Schedules
  </a>
  {{-- Page Header --}}
  <div class="flex flex-col gap-2">
    <h1 class="text-2xl font-semibold text-gray-900 dark:text-gray-100">
      {{ $schedule->description ?? 'Schedule ID: ' . $schedule->odoo_schedule_id }}
    </h1>
    <p class="text-xs text-gray-600 dark:text-gray-400">
      Average hours per day: {{ $schedule->average_hours_day ?? 'N/A' }}
    </p>
    {{-- Timestamps --}}
    <div
      class="flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400"
    >
      <x-tooltip text="{{ $schedule->created_at->format('Y-m-d H:i') }}">
        <span>Created {{ $schedule->created_at->diffForHumans() }}</span>
      </x-tooltip>
      <x-tooltip text="{{ $schedule->updated_at->format('Y-m-d H:i') }}">
        <span>Updated {{ $schedule->updated_at->diffForHumans() }}</span>
      </x-tooltip>
    </div>
  </div>

  {{-- Schedule Details Section --}}
  <div
    class="rounded-md border border-gray-300 bg-white p-4 dark:border-gray-600 dark:bg-gray-800"
  >
    <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
      Weekly Time Slots
    </h2>
    @if ($schedule->scheduleDetails->isNotEmpty())
      <ul class="list-inside list-disc space-y-1 pl-4 text-sm">
        @foreach ($schedule->scheduleDetails->sortBy('day_of_week') as $detail)
          <li class="text-gray-600 dark:text-gray-400">
            <span class="font-semibold">
              {{ jddayofweek($detail->day_of_week - 1, 1) }}
            </span>
            : {{ \Carbon\Carbon::parse($detail->start_time)->format('H:i') }} -
            {{ \Carbon\Carbon::parse($detail->end_time)->format('H:i') }}
            <span class="text-gray-500">
              ({{ $detail->duration_in_hours }} hours)
            </span>
            @if ($detail->is_off_day)
              <span class="ml-1 font-medium text-orange-500">(Off Day)</span>
            @endif
          </li>
        @endforeach
      </ul>
    @else
      <p class="text-sm text-gray-500 dark:text-gray-400">
        No details defined for this schedule.
      </p>
    @endif
  </div>

  {{-- Assigned Users Section --}}
  <div
    class="rounded-md border border-gray-300 bg-white p-4 dark:border-gray-600 dark:bg-gray-800"
  >
    <h2 class="mb-3 text-lg font-semibold text-gray-900 dark:text-gray-100">
      Assigned Users (Current & Past)
    </h2>
    @if ($uniqueUserSchedules->isNotEmpty())
      <div class="flex flex-wrap gap-2">
        @foreach ($uniqueUserSchedules as $userSchedule)
          @if ($userSchedule->user)
            <x-tooltip
              text="Assigned from {{ $userSchedule->effective_from->format('Y-m-d') }} {{ $userSchedule->effective_until ? 'to ' . $userSchedule->effective_until->format('Y-m-d') : 'indefinitely' }}"
            >
              <a
                href="{{ route('user.dashboard', ['id' => $userSchedule->user->id]) }}"
                wire:navigate
                class="inline-block"
              >
                <x-badge size="sm" variant="default">
                  {{ $userSchedule->user->name }}
                </x-badge>
              </a>
            </x-tooltip>
          @endif
        @endforeach
      </div>
    @else
      <p class="text-sm text-gray-500 dark:text-gray-400">
        No users currently or previously assigned to this schedule.
      </p>
    @endif
  </div>
</div>
