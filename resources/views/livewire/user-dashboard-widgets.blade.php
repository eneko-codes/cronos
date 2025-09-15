<div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
  <!-- Today's Schedule -->
  <div
    class="flex flex-col gap-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
  >
    <div class="flex items-center gap-2">
      <svg
        class="size-5 text-gray-500 dark:text-gray-400"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"
        />
      </svg>
      <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300">
        Today's Schedule
      </h3>
    </div>
    @if ($todaysSchedule)
      <div class="flex flex-col">
        @if ($todaysSchedule['duration'] && $todaysSchedule['duration'] !== '0h 0m')
          {{-- Display Duration and detailed schedule information --}}
          <div class="flex items-center justify-between">
            <span class="text-lg font-bold text-gray-800 dark:text-gray-100">
              {{ $todaysSchedule['duration'] }}
            </span>
            @if ($todaysSchedule['flexibleHours'])
              <x-badge size="sm" variant="info">Flexible</x-badge>
            @endif
          </div>

          {{-- Schedule Name --}}
          <span class="mb-2 text-xs text-gray-500 dark:text-gray-400">
            {{ $todaysSchedule['name'] }}
          </span>

          {{-- Time Slots --}}
          @if (! empty($todaysSchedule['detailedSlots']))
            <div class="space-y-1">
              @foreach ($todaysSchedule['detailedSlots'] as $slot)
                <div class="text-xs">
                  <span class="text-gray-600 dark:text-gray-300">
                    {{ $slot['formatted'] }}
                  </span>
                </div>
              @endforeach
            </div>
          @endif
        @else
          {{-- Display Day Off Message --}}
          <span class="text-lg font-semibold text-gray-700 dark:text-gray-200">
            Scheduled Day Off
          </span>
          <span class="text-xs text-gray-500 dark:text-gray-400">
            ({{ $todaysSchedule['name'] }})
          </span>
        @endif
      </div>
    @else
      {{-- Display message when no active schedule record found --}}
      <p class="text-sm text-gray-500 dark:text-gray-400">
        No active schedule found for today.
      </p>
    @endif
  </div>

  <!-- Today's Attendance -->
  <div
    class="flex flex-col gap-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
  >
    <div class="flex items-center gap-2">
      <svg
        class="size-5 text-gray-500 dark:text-gray-400"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"
        />
      </svg>
      <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300">
        Today's Attendance
      </h3>
    </div>
    @if ($todaysAttendance)
      <div class="flex flex-col">
        <div class="flex items-center gap-2">
          @if (! empty($todaysAttendance['segments']) || $todaysAttendance['duration'] !== '0h 0m')
            <span class="text-lg font-bold text-gray-800 dark:text-gray-100">
              {{ $todaysAttendance['duration'] ?? '-' }}
            </span>
            <x-badge
              variant="{{ $todaysAttendance['is_remote'] ? 'info' : 'success' }}"
              size="sm"
            >
              {{ $todaysAttendance['is_remote'] ? 'Remote' : 'In Office' }}
            </x-badge>
          @else
            <span class="text-lg font-bold text-gray-800 dark:text-gray-100">
              {{ $todaysAttendance['status'] ?? 'Not Clocked In' }}
            </span>
          @endif
        </div>
        @if (! empty($todaysAttendance['segments']) || $todaysAttendance['duration'] !== '0h 0m')
          <div class="mt-1 flex flex-col gap-0.5">
            @if (! empty($todaysAttendance['segments']))
              {{-- Display individual segments --}}
              @foreach ($todaysAttendance['segments'] as $segment)
                <div class="text-xs text-gray-400">
                  {{ $segment['clock_in'] ?? '-' }} -
                  @if ($segment['clock_out'])
                    {{ $segment['clock_out'] }}
                  @else
                    <span
                      class="font-medium text-green-600 dark:text-green-500"
                    >
                      Clocked In
                    </span>
                  @endif
                  @if ($segment['duration'] !== '0h 0m')
                    <span class="text-gray-500">
                      ({{ $segment['duration'] }})
                    </span>
                  @endif
                </div>
              @endforeach
            @else
              {{-- Fallback to overall start/end times --}}
              <span class="text-xs text-gray-400">
                Start:
                {{ $todaysAttendance['start'] ?: '-' }}
              </span>
              <span class="text-xs text-gray-400">
                End:
                {{ $todaysAttendance['end'] ?: '-' }}
              </span>
            @endif
          </div>
        @endif
      </div>
    @else
      <p class="text-sm text-gray-500 dark:text-gray-400">
        No attendance data for today.
      </p>
    @endif
  </div>

  <!-- Today's Logged Time -->
  <div
    class="flex flex-col gap-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
  >
    <div class="flex items-center gap-2">
      <svg
        class="size-5 text-gray-500 dark:text-gray-400"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"
        />
      </svg>
      <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300">
        Today's Logged Time
      </h3>
    </div>
    @if (! empty($todaysTimeEntries))
      <div class="flex flex-col gap-2">
        @php
          $totalSeconds = array_sum(array_column($todaysTimeEntries, 'duration_seconds'));
          $totalHours = floor($totalSeconds / 3600);
          $totalMinutes = floor(($totalSeconds % 3600) / 60);
          $totalTime = $totalHours . 'h ' . $totalMinutes . 'm';
        @endphp

        <span class="text-lg font-bold text-gray-800 dark:text-gray-100">
          {{ $totalTime }}
        </span>
        <div class="max-h-32 overflow-y-auto">
          @foreach ($todaysTimeEntries as $entry)
            <div class="mb-2 border-l-2 border-gray-300 pl-2 text-xs">
              @if ($entry['status'] && $entry['status'] !== 'none')
                <div class="mb-1 flex justify-end">
                  <x-badge variant="info" size="sm">
                    {{ $entry['status'] }}
                  </x-badge>
                </div>
              @endif

              <div class="mb-1 flex items-start justify-between gap-2">
                <span
                  class="flex-1 text-xs font-medium break-words text-gray-600 dark:text-gray-300"
                >
                  {{ $entry['project_name'] }}
                  @if ($entry['task_name'])
                    <span class="text-gray-500">
                      → {{ $entry['task_name'] }}
                    </span>
                  @endif
                </span>
                <span
                  class="flex-shrink-0 rounded bg-gray-100 px-1.5 py-0.5 text-xs font-semibold whitespace-nowrap text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                >
                  {{ $entry['duration'] }}
                </span>
              </div>
              @if ($entry['description'])
                <div class="text-gray-500 dark:text-gray-400">
                  {{ $entry['description'] }}
                </div>
              @endif
            </div>
          @endforeach
        </div>
      </div>
    @else
      <span class="text-lg font-bold text-gray-800 dark:text-gray-100">
        No time logged
      </span>
    @endif
  </div>

  <!-- Upcoming Leave -->
  <div
    class="flex flex-col gap-3 rounded-lg bg-white p-4 shadow dark:bg-gray-800"
  >
    <div class="flex items-center gap-2">
      <svg
        class="size-5 text-gray-500 dark:text-gray-400"
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-6h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z"
        />
      </svg>
      <h3 class="text-sm font-semibold text-gray-600 dark:text-gray-300">
        Upcoming Leave
      </h3>
    </div>
    @if ($this->upcomingLeave)
      <div class="flex flex-col">
        <span class="text-base font-semibold text-gray-800 dark:text-gray-100">
          {{ $this->upcomingLeave->leaveType?->name ?? 'Leave' }}
        </span>
        <span class="text-sm text-gray-600 dark:text-gray-300">
          {{ $this->upcomingLeave->start_date->format('M d') }} -
          {{ $this->upcomingLeave->end_date->format('M d, Y') }}
        </span>
        <span class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          @if ($this->upcomingLeave->isHalfDay())
            Half day
            ({{ $this->upcomingLeave->isMorningLeave() ? 'Morning' : 'Afternoon' }})
          @elseif ($this->upcomingLeave->duration_days == 1)
            Full day
          @else
            {{ $this->upcomingLeave->duration_days }} days
          @endif
        </span>
      </div>
    @else
      <p class="text-sm text-gray-500 dark:text-gray-400">
        No approved leave in the next 30 days.
      </p>
    @endif
  </div>
</div>
