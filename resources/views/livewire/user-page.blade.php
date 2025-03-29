<div class="flex flex-col gap-5">
  <!-- Header Section -->
  <div class="flex flex-row items-center gap-2">
    <h1 class="text-xl font-bold">{{ $user->name }}</h1>

    <!-- User Badges -->
    <div class="flex flex-row gap-1">
      @if ($user->is_admin)
        <x-tooltip text="User can see all employee data">
          <x-badge size="sm" variant="primary">Admin</x-badge>
        </x-tooltip>
      @endif

      @if ($user->do_not_track)
        <x-tooltip text="The data of this user will not be fetched">
          <x-badge size="sm" variant="alert">Not tracking</x-badge>
        </x-tooltip>
      @endif

      @if ($user->muted_notifications)
        <x-tooltip text="User notifications are currently muted">
          <x-badge size="sm" variant="alert">Muted</x-badge>
        </x-tooltip>
      @endif

      @if ($user->odoo_id)
        <x-tooltip text="User has an Odoo account linked">
          <x-badge size="sm" variant="info">Odoo</x-badge>
        </x-tooltip>
      @endif

      @if ($user->desktime_id)
        <x-tooltip text="User has a Desktime account linked">
          <x-badge size="sm" variant="info">Desktime</x-badge>
        </x-tooltip>
      @endif

      @if ($user->proofhub_id)
        <x-tooltip text="User has a Proofhub account linked">
          <x-badge size="sm" variant="info">Proofhub</x-badge>
        </x-tooltip>
      @endif

      @if ($user->systempin_id)
        <x-tooltip text="User has a SystemPin account linked">
          <x-badge size="sm" variant="info">SystemPin</x-badge>
        </x-tooltip>
      @endif
    </div>
  </div>

  @if ($user->do_not_track)
    <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
      <p class="text-md text-gray-600 dark:text-gray-300">
        {{ $user->name }} is currently set to do not track. Data is not stored
        for this user.
      </p>
    </div>
  @else
    <!-- Period Controls -->
    <div
      class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
    >
      <div class="flex flex-row items-center gap-4">
        <!-- Navigation for Previous/Next Period -->
        <div class="flex items-center gap-2">
          <button
            class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-gray-200/75 px-1.5 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-200 dark:bg-gray-200 dark:hover:bg-gray-100"
            wire:click="previousPeriod"
          >
            ←
          </button>

          <h2 class="text-sm font-semibold">
            @php
              // Use UTC directly without timezone conversion
              $carbonDate = \Carbon\Carbon::parse($currentDate, 'UTC');
            @endphp

            {{ $viewMode === 'weekly' ? 'Week' : 'Month' }} of
            {{ $carbonDate->format('F d, Y') }}
          </h2>

          <button
            class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-gray-200/75 px-1.5 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-200 dark:hover:bg-gray-100"
            wire:click="nextPeriod"
            @if($this->isNextPeriodDisabled) disabled @endif
          >
            →
          </button>
        </div>
      </div>

      <!-- View Mode Toggles -->
      <div class="flex items-center gap-2">
        <!-- Deviations Toggle -->
        <x-toggle-button
          :active="$showDeviations"
          label="Deviations"
          onClick="toggleDeviations"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            class="h-4 w-4"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              stroke-width="2"
              d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"
            />
          </svg>
        </x-toggle-button>

        <!-- Period Toggle (Weekly/Monthly) -->
        <x-tabs
          :active="$viewMode"
          :filters="[
            'weekly' => 'Weekly',
            'monthly' => 'Monthly'
          ]"
          onFilterChange="setViewMode"
          :showCounts="false"
        />
      </div>
    </div>

    <!-- Data Table -->
    <div class="scrollbar-thin overflow-x-auto shadow-xl">
      <table
        class="w-full table-auto border-collapse text-sm text-gray-800 dark:text-gray-200"
      >
        <thead
          class="bg-gray-200 text-left font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-100"
        >
          <tr>
            <th
              class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
            >
              Day
            </th>
            @foreach ([
                'Scheduled' => 'Hours from Odoo calendar',
                'Leave' => 'Time off from Odoo',
                'Attendance' => 'Hours from Desktime/SystemPin',
                'Worked' => 'Hours from Proofhub'
              ]
              as $name => $tooltip)
              <th
                class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
              >
                <div class="inline-flex flex-row items-center gap-1">
                  {{ $name }}
                  <x-tooltip text="{{ $tooltip }}">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke-width="1.5"
                      stroke="currentColor"
                      class="size-4"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                      />
                    </svg>
                  </x-tooltip>
                </div>
              </th>
            @endforeach

            @if ($showDeviations)
              @foreach ([
                  'Attendance vs Scheduled' =>
                    'Percentage deviation between attendance and scheduled hours',
                  'Worked vs Scheduled' =>
                    'Percentage deviation between worked and scheduled hours',
                  'Worked vs Attendance' =>
                    'Percentage deviation between worked and attendance hours'
                ]
                as $name => $tooltip)
                <th
                  class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
                >
                  <div class="inline-flex flex-row items-center gap-1">
                    {{ $name }}
                    <x-tooltip text="{{ $tooltip }}">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                        class="size-4"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                        />
                      </svg>
                    </x-tooltip>
                  </div>
                </th>
              @endforeach
            @endif
          </tr>
        </thead>
        <tbody>
          @foreach ($this->getPeriodData() as $day)
            @php
              $dayDate = \Carbon\Carbon::parse($day['date']);
              $isFutureDate = $dayDate->isFuture();
              if ($showDeviations) {
                $deviations = $this->getDeviationPercentages($day);
              }
            @endphp

            <tr
              class="{{ $isFutureDate ? ' text-gray-500 dark:text-gray-500' : '' }} border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
            >
              <!-- Date Column -->
              <td
                class="whitespace-nowrap border p-2 font-semibold dark:border-gray-700"
              >
                <div class="flex items-center gap-2">
                  {{ $dayDate->format('l d') }}

                  @if ($dayDate->isToday())
                    <x-badge size="sm" variant="primary">Today</x-badge>
                  @endif
                </div>
              </td>

              <!-- Scheduled -->
              <td class="whitespace-nowrap border p-2 dark:border-gray-700">
                <div class="flex flex-col gap-1">
                  <x-tooltip>
                    <x-slot name="text">
                      <div class="flex flex-col gap-1">
                        @if (count($day['scheduled']['slots']) > 0)
                          @if (isset($day['scheduled']['schedule_name']))
                            <span
                              class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-100"
                            >
                              {{ $day['scheduled']['schedule_name'] }}
                            </span>
                          @endif

                          @foreach ($day['scheduled']['slots'] as $slot)
                            <span
                              class="text-xs text-gray-600 dark:text-gray-200"
                            >
                              {{ $slot }}
                            </span>
                          @endforeach
                        @else
                          <span
                            class="text-xs text-gray-500 dark:text-gray-400"
                          >
                            No data
                          </span>
                        @endif
                      </div>
                    </x-slot>
                    <span class="text-gray-700 dark:text-gray-300">
                      {{ $day['scheduled']['duration'] !== '0h 0m' ? $day['scheduled']['duration'] : '' }}
                    </span>
                  </x-tooltip>
                </div>
              </td>

              <!-- Leave -->
              <td class="whitespace-nowrap border p-2 dark:border-gray-700">
                <!-- Simplified default view - only show when leave exists -->
                @if ($day['leave'])
                  <div
                    class="{{ $day['leave']['status'] !== 'validate' ? 'opacity-60' : '' }} flex items-center gap-2"
                  >
                    <!-- Main leave information with tooltip -->
                    <div>
                      <x-tooltip>
                        <x-slot name="text">
                          <div class="flex max-w-xs flex-col gap-2">
                            <!-- Duration in days -->
                            <div class="mb-1 flex flex-row items-center gap-1">
                              <span
                                class="text-xs font-medium text-gray-600 dark:text-gray-300"
                              >
                                {{ $day['leave']['duration'] }}
                              </span>
                            </div>

                            <!-- Time details -->

                            @if ($day['leave']['is_half_day'])
                              <div>
                                <span
                                  class="text-xs text-gray-600 dark:text-gray-300"
                                >
                                  {{ ucfirst($day['leave']['time_period']) }}
                                  ({{ $day['leave']['half_day_time'] ?? '—' }})
                                </span>
                              </div>
                            @else
                              <div>
                                <span
                                  class="text-xs text-gray-600 dark:text-gray-300"
                                >
                                  Full day
                                </span>
                              </div>
                            @endif

                            <!-- Leave type -->
                            <div>
                              <span
                                class="text-xs font-medium text-gray-600 dark:text-gray-300"
                              >
                                {{ $day['leave']['type'] ?? 'Leave' }}
                              </span>
                            </div>

                            <!-- Status info if not validated -->
                            @if ($day['leave']['status'] !== 'validate')
                              <div
                                class="mt-1 border border-dashed border-gray-200 pt-1 dark:border-gray-700"
                              >
                                <span
                                  class="text-xs italic text-gray-500 dark:text-gray-400"
                                >
                                  {{ $day['leave']['status'] === 'confirm' ? 'Waiting approval' : 'Cancelled' }}
                                </span>
                              </div>
                            @endif
                          </div>
                        </x-slot>
                        <span class="text-gray-700 dark:text-gray-300">
                          {{ $day['leave']['duration_hours'] }}

                          @if ($day['leave']['status'] === 'validate')
                            <x-badge variant="success" size="sm">
                              {{ $day['leave']['is_half_day'] ? 'Half' : 'Full' }}
                            </x-badge>
                          @elseif ($day['leave']['status'] === 'confirm')
                            <x-tooltip text="Leave request is pending approval">
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="size-4"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                              >
                                <path
                                  d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"
                                />
                              </svg>
                            </x-tooltip>
                          @elseif ($day['leave']['status'] === 'cancel')
                            <x-tooltip text="Leave request was cancelled">
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="size-4"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                              >
                                <path
                                  fill-rule="evenodd"
                                  d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z"
                                  clip-rule="evenodd"
                                />
                              </svg>
                            </x-tooltip>
                          @endif
                        </span>
                      </x-tooltip>
                    </div>
                  </div>
                @endif
              </td>

              <!-- Attendance -->
              <td class="whitespace-nowrap border p-2 dark:border-gray-700">
                <x-tooltip>
                  <x-slot name="text">
                    <div class="flex flex-col gap-1">
                      @if ($day['attendance']['is_remote'])
                        <span class="text-xs text-gray-600 dark:text-gray-200">
                          Remote work
                        </span>
                      @elseif (! empty($day['attendance']['times']))
                        <span class="text-xs text-gray-600 dark:text-gray-200">
                          {{ implode(' → ', $day['attendance']['times']) }}
                        </span>
                      @else
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                          No data
                        </span>
                      @endif
                    </div>
                  </x-slot>
                  <div class="flex flex-row items-center gap-2">
                    <span class="text-gray-700 dark:text-gray-300">
                      {{ $day['attendance']['duration'] !== '0h 0m' ? $day['attendance']['duration'] : '' }}
                    </span>
                    @if ($day['attendance']['is_remote'])
                      <x-badge variant="info" size="sm">Remote</x-badge>
                    @elseif (! empty($day['attendance']['times']))
                      <x-badge variant="success" size="sm">In Office</x-badge>
                    @endif
                  </div>
                </x-tooltip>
              </td>

              <!-- Worked -->
              <td class="whitespace-nowrap border p-2 dark:border-gray-700">
                <div class="flex flex-col gap-1">
                  <x-tooltip>
                    <x-slot name="text">
                      <div class="flex max-w-xs flex-col gap-2">
                        @if (isset($day['worked']['detailed_entries']) && count($day['worked']['detailed_entries']) > 0)
                          @foreach ($day['worked']['detailed_entries'] as $entry)
                            <div
                              class="{{ ! $loop->last ? 'mb-3 border border-gray-200 pb-3 dark:border-gray-700' : '' }} flex flex-col"
                            >
                              <div
                                class="mb-1 flex items-center justify-between"
                              >
                                <span
                                  class="text-xs font-medium text-gray-800 dark:text-gray-100"
                                >
                                  {{ $entry['project'] }}
                                </span>
                                <span
                                  class="ml-2 whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                >
                                  {{ $entry['duration'] }}
                                </span>
                              </div>
                              @if (isset($entry['task']) && $entry['task'])
                                <span
                                  class="mb-0.5 text-xs text-gray-600 dark:text-gray-300"
                                >
                                  {{ $entry['task'] }}
                                </span>
                              @endif

                              @if (isset($entry['description']) && $entry['description'])
                                <span
                                  class="text-xs italic text-gray-500 dark:text-gray-400"
                                >
                                  {{ Str::limit($entry['description'], 80) }}
                                </span>
                              @endif
                            </div>
                          @endforeach
                        @elseif (count($day['worked']['projects']) > 0)
                          <div class="flex flex-col">
                            @foreach ($day['worked']['projects'] as $project)
                              <div class="{{ ! $loop->last ? 'mb-3' : '' }}">
                                <span
                                  class="text-xs font-medium text-gray-800 dark:text-gray-100"
                                >
                                  {{ $project['name'] }}
                                </span>
                                @if (! empty($project['tasks']))
                                  <div
                                    class="mt-1 text-xs text-gray-600 dark:text-gray-300"
                                  >
                                    {{ implode(', ', $project['tasks']) }}
                                  </div>
                                @endif
                              </div>
                            @endforeach
                          </div>
                        @else
                          <span
                            class="text-xs text-gray-500 dark:text-gray-400"
                          >
                            No data
                          </span>
                        @endif
                      </div>
                    </x-slot>
                    <span class="text-gray-700 dark:text-gray-300">
                      {{ $day['worked']['duration'] !== '0h 0m' ? $day['worked']['duration'] : '' }}
                    </span>
                  </x-tooltip>
                </div>
              </td>

              <!-- Deviation Columns -->
              @if ($showDeviations)
                <!-- Attendance vs Scheduled -->
                <td class="whitespace-nowrap border p-2 dark:border-gray-700">
                  @if (! $isFutureDate && $deviations['attendance_vs_scheduled'] !== 0)
                    @php
                      $scheduledMins = $this->durationToMinutes($day['scheduled']['duration']);
                      $attendanceMins = $this->durationToMinutes($day['attendance']['duration']);
                      $diffMins = $attendanceMins - $scheduledMins;
                      $diffFormatted = $this->formatDuration(abs($diffMins));

                      if ($diffMins == 0) {
                        $attendanceTooltip = 'Attendance exactly matches scheduled time';
                      } else {
                        $attendanceTooltip =
                          $diffMins > 0
                            ? "Attended $diffFormatted more than scheduled"
                            : "Attended $diffFormatted less than scheduled";
                      }

                      // Set color based on deviation severity
                      $deviationValue = $deviations['attendance_vs_scheduled'];
                      $deviationClass =
                        $deviationValue >= 0
                          ? 'text-green-600 dark:text-green-400'
                          : ($deviationValue > -15
                            ? 'text-amber-600 dark:text-amber-400'
                            : 'text-red-600 dark:text-red-400');

                      // Ensure -100% is always red
                      if ($deviationValue <= -100) {
                        $deviationClass = 'text-red-600 dark:text-red-400';
                      }
                    @endphp

                    <x-tooltip text="{{ $attendanceTooltip }}">
                      <span class="{{ $deviationClass }}">
                        {{ $deviationValue > 0 ? '+' : '' }}{{ $deviationValue }}%
                      </span>
                    </x-tooltip>
                  @endif
                </td>

                <!-- Worked vs Scheduled -->
                <td class="whitespace-nowrap border p-2 dark:border-gray-700">
                  @if (! $isFutureDate && $deviations['worked_vs_scheduled'] !== 0)
                    @php
                      $scheduledMins = $this->durationToMinutes($day['scheduled']['duration']);
                      $workedMins = $this->durationToMinutes($day['worked']['duration']);
                      $diffMins = $workedMins - $scheduledMins;
                      $diffFormatted = $this->formatDuration(abs($diffMins));

                      if ($diffMins == 0) {
                        $workedTooltip = 'Work logged exactly matches scheduled time';
                      } else {
                        $workedTooltip = $diffMins > 0 ? "Worked $diffFormatted more than scheduled" : "Worked $diffFormatted less than scheduled";
                      }

                      // Set color based on deviation severity
                      $deviationValue = $deviations['worked_vs_scheduled'];
                      $deviationClass = $deviationValue >= 0 ? 'text-green-600 dark:text-green-400' : ($deviationValue > -15 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400');

                      // Ensure -100% is always red
                      if ($deviationValue <= -100) {
                        $deviationClass = 'text-red-600 dark:text-red-400';
                      }
                    @endphp

                    <x-tooltip text="{{ $workedTooltip }}">
                      <span class="{{ $deviationClass }}">
                        {{ $deviationValue > 0 ? '+' : '' }}{{ $deviationValue }}%
                      </span>
                    </x-tooltip>
                  @endif
                </td>

                <!-- Worked vs Attendance -->
                <td class="whitespace-nowrap border p-2 dark:border-gray-700">
                  @if (! $isFutureDate && $deviations['worked_vs_attendance'] !== 0)
                    @php
                      $attendanceMins = $this->durationToMinutes($day['attendance']['duration']);
                      $workedMins = $this->durationToMinutes($day['worked']['duration']);
                      $diffMins = $workedMins - $attendanceMins;
                      $diffFormatted = $this->formatDuration(abs($diffMins));

                      if ($diffMins == 0) {
                        $workAttendanceTooltip = 'Work logged exactly matches attendance time';
                      } else {
                        $workAttendanceTooltip = $diffMins > 0 ? "Worked $diffFormatted more than attended" : "Worked $diffFormatted less than attended";
                      }

                      // Set color based on deviation severity
                      $deviationValue = $deviations['worked_vs_attendance'];
                      $deviationClass = $deviationValue >= 0 ? 'text-green-600 dark:text-green-400' : ($deviationValue > -15 ? 'text-amber-600 dark:text-amber-400' : 'text-red-600 dark:text-red-400');

                      // Ensure -100% is always red
                      if ($deviationValue <= -100) {
                        $deviationClass = 'text-red-600 dark:text-red-400';
                      }
                    @endphp

                    <x-tooltip text="{{ $workAttendanceTooltip }}">
                      <span class="{{ $deviationClass }}">
                        {{ $deviationValue > 0 ? '+' : '' }}{{ $deviationValue }}%
                      </span>
                    </x-tooltip>
                  @endif
                </td>
              @endif
            </tr>
          @endforeach

          <!-- Totals Row -->
          <tr class="bg-gray-200 font-bold dark:bg-gray-700">
            <td
              class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
            >
              <x-tooltip
                text="Totals only include past dates and today. Future dates are not counted in calculations."
              >
                <div class="flex items-center gap-1">
                  Totals
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-4"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                    />
                  </svg>
                </div>
              </x-tooltip>
            </td>
            <td
              class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
            >
              <!-- Convert minutes to "Xh Ym" -->
              @php
                $scheduledMins = $this->getTotals()['scheduled'];
                $scheduledH = intdiv($scheduledMins, 60);
                $scheduledR = $scheduledMins % 60;
              @endphp

              {{ $scheduledMins > 0 ? "{$scheduledH}h {$scheduledR}m" : '' }}
            </td>
            <td
              class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
            >
              @php
                $leaveMins = $this->getTotals()['leave'];
                $leaveH = intdiv($leaveMins, 60);
                $leaveR = $leaveMins % 60;
              @endphp

              {{ $leaveMins > 0 ? "{$leaveH}h {$leaveR}m" : '' }}
            </td>
            <td
              class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
            >
              @php
                $attendanceMins = $this->getTotals()['attendance'];
                $attendanceH = intdiv($attendanceMins, 60);
                $attendanceR = $attendanceMins % 60;
              @endphp

              {{ $attendanceMins > 0 ? "{$attendanceH}h {$attendanceR}m" : '' }}
            </td>
            <td
              class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
            >
              @php
                $workedMins = $this->getTotals()['worked'];
                $workedH = intdiv($workedMins, 60);
                $workedR = $workedMins % 60;
              @endphp

              {{ $workedMins > 0 ? "{$workedH}h {$workedR}m" : '' }}
            </td>

            <!-- Total Deviations -->
            @if ($showDeviations)
              @php
                $totalDeviations = $this->getTotalDeviations();
              @endphp

              <!-- Attendance vs Scheduled -->
              <td
                class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
              >
                @if ($totalDeviations['attendance_vs_scheduled'] !== 0)
                  @php
                    $scheduledMins = $this->getTotals()['scheduled'];
                    $attendanceMins = $this->getTotals()['attendance'];
                    $diffMins = $attendanceMins - $scheduledMins;
                    $diffFormatted = $this->formatDuration(abs($diffMins));

                    if ($diffMins == 0) {
                      $attendanceTooltip = 'Total attendance exactly matches scheduled time';
                    } else {
                      $attendanceTooltip =
                        $diffMins > 0
                          ? "Total attendance is $diffFormatted more than scheduled"
                          : "Total attendance is $diffFormatted less than scheduled";
                    }

                    // Set color based on deviation severity
                    $deviationValue = $totalDeviations['attendance_vs_scheduled'];
                    $deviationClass =
                      $deviationValue >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : ($deviationValue > -15
                          ? 'text-amber-600 dark:text-amber-400'
                          : 'text-red-600 dark:text-red-400');

                    // Ensure -100% is always red
                    if ($deviationValue <= -100) {
                      $deviationClass = 'text-red-600 dark:text-red-400';
                    }
                  @endphp

                  <x-tooltip text="{{ $attendanceTooltip }}">
                    <span class="{{ $deviationClass }}">
                      {{ $deviationValue > 0 ? '+' : '' }}{{ $deviationValue }}%
                    </span>
                  </x-tooltip>
                @endif
              </td>

              <!-- Worked vs Scheduled -->
              <td
                class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
              >
                @if ($totalDeviations['worked_vs_scheduled'] !== 0)
                  @php
                    $scheduledMins = $this->getTotals()['scheduled'];
                    $workedMins = $this->getTotals()['worked'];
                    $diffMins = $workedMins - $scheduledMins;
                    $diffFormatted = $this->formatDuration(abs($diffMins));

                    if ($diffMins == 0) {
                      $workedTooltip = 'Total work logged exactly matches scheduled time';
                    } else {
                      $workedTooltip =
                        $diffMins > 0
                          ? "Total work logged is $diffFormatted more than scheduled"
                          : "Total work logged is $diffFormatted less than scheduled";
                    }

                    // Set color based on deviation severity
                    $deviationValue = $totalDeviations['worked_vs_scheduled'];
                    $deviationClass =
                      $deviationValue >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : ($deviationValue > -15
                          ? 'text-amber-600 dark:text-amber-400'
                          : 'text-red-600 dark:text-red-400');

                    // Ensure -100% is always red
                    if ($deviationValue <= -100) {
                      $deviationClass = 'text-red-600 dark:text-red-400';
                    }
                  @endphp

                  <x-tooltip text="{{ $workedTooltip }}">
                    <span class="{{ $deviationClass }}">
                      {{ $deviationValue > 0 ? '+' : '' }}{{ $deviationValue }}%
                    </span>
                  </x-tooltip>
                @endif
              </td>

              <!-- Worked vs Attendance -->
              <td
                class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
              >
                @if ($totalDeviations['worked_vs_attendance'] !== 0)
                  @php
                    $attendanceMins = $this->getTotals()['attendance'];
                    $workedMins = $this->getTotals()['worked'];
                    $diffMins = $workedMins - $attendanceMins;
                    $diffFormatted = $this->formatDuration(abs($diffMins));

                    if ($diffMins == 0) {
                      $workAttendanceTooltip = 'Total work logged exactly matches attendance time';
                    } else {
                      $workAttendanceTooltip =
                        $diffMins > 0
                          ? "Total work logged is $diffFormatted more than attendance"
                          : "Total work logged is $diffFormatted less than attendance";
                    }

                    // Set color based on deviation severity
                    $deviationValue = $totalDeviations['worked_vs_attendance'];
                    $deviationClass =
                      $deviationValue >= 0
                        ? 'text-green-600 dark:text-green-400'
                        : ($deviationValue > -15
                          ? 'text-amber-600 dark:text-amber-400'
                          : 'text-red-600 dark:text-red-400');

                    // Ensure -100% is always red
                    if ($deviationValue <= -100) {
                      $deviationClass = 'text-red-600 dark:text-red-400';
                    }
                  @endphp

                  <x-tooltip text="{{ $workAttendanceTooltip }}">
                    <span class="{{ $deviationClass }}">
                      {{ $deviationValue > 0 ? '+' : '' }}{{ $deviationValue }}%
                    </span>
                  </x-tooltip>
                @endif
              </td>
            @endif
          </tr>
        </tbody>
      </table>
    </div>
  @endif
</div>
