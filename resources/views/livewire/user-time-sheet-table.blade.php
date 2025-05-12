<div
  class="flex flex-col gap-4 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800"
>
  <!-- Period Controls -->
  <div
    class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
  >
    <div class="flex flex-row items-center gap-4">
      <!-- Navigation for Previous/Next Period -->
      <div class="flex items-center gap-2">
        <button
          class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-slate-100 px-1.5 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-slate-200 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
          wire:click="dispatchPreviousPeriod"
        >
          ←
        </button>

        <h2 class="text-sm font-semibold">
          {{ $viewMode === 'weekly' ? 'Week' : 'Month' }} of
          {{ Illuminate\Support\Carbon::parse($currentDate)->translatedFormat('F d, Y') }}
        </h2>

        <button
          class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-slate-100 px-1.5 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-slate-200 disabled:opacity-50 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
          wire:click="dispatchNextPeriod"
          @disabled($isNextPeriodDisabled)
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
        wire:click="dispatchToggleDeviations"
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
        :filters="collect([
          'weekly' => 'Weekly',
          'monthly' => 'Monthly'
        ])"
        onFilterChange="dispatchChangeViewMode"
        :showCounts="false"
      />
    </div>
  </div>

  <!-- Data Table -->
  <div class="scrollbar-thin overflow-x-auto rounded-lg">
    <table class="w-full table-auto border-collapse text-sm">
      <thead
        class="bg-gray-50 text-left font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-100"
      >
        <tr>
          <th
            class="border border-gray-200 p-2 whitespace-nowrap dark:border-gray-600"
          >
            Day
          </th>
          @foreach (collect([
              'Scheduled' => 'Hours from Odoo calendar',
              'Leave' => 'Time off from Odoo',
              'Attendance' => 'Hours from Desktime/SystemPin',
              'Worked' => 'Hours from Proofhub'
            ])
            as $name => $tooltip)
            <th
              class="border border-gray-200 p-2 whitespace-nowrap dark:border-gray-600"
            >
              <div class="inline-flex flex-row items-center gap-1">
                {{ $name }}
                <x-tooltip>
                  <x-slot name="text">
                    {{ $tooltip }}
                  </x-slot>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="size-4"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      stroke-width="2"
                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                    />
                  </svg>
                </x-tooltip>
              </div>
            </th>
          @endforeach

          @if ($showDeviations)
            @foreach (collect([
                'Attendance vs Scheduled' =>
                  'Percentage deviation between attendance and scheduled hours',
                'Worked vs Scheduled' =>
                  'Percentage deviation between worked and scheduled hours',
                'Worked vs Attendance' =>
                  'Percentage deviation between worked and attendance hours'
              ])
              as $name => $tooltip)
              <th
                class="border border-gray-200 p-2 whitespace-nowrap dark:border-gray-600"
              >
                <div class="inline-flex flex-row items-center gap-1">
                  {{ $name }}
                  <x-tooltip>
                    <x-slot name="text">
                      {{ $tooltip }}
                    </x-slot>
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      class="size-4"
                      fill="none"
                      viewBox="0 0 24 24"
                      stroke="currentColor"
                    >
                      <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
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
        @foreach ($periodData as $day)
          @php
            $isTotalRow = $day->isTotalRow ?? false;
            $dayDate = $isTotalRow ? null : Illuminate\Support\Carbon::parse($day->date);
            $isFutureDate = ! $isTotalRow && $dayDate->isFuture();
            $isWeekend = ! $isTotalRow && $dayDate->isWeekend();
            $isPastOrToday = ! $isTotalRow && ! $isFutureDate;

            // CSS classes for cell backgrounds
            $dateCellClasses = 'border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700'; // For Day column & Totals label cell
            $dataCellBgLight = $isFutureDate ? 'bg-gray-50' : 'bg-white';
            $dataCellBgDark = $isFutureDate ? 'dark:bg-gray-800' : 'dark:bg-gray-800';
            $dataCellClasses = $dataCellBgLight . ' ' . $dataCellBgDark . ' border border-gray-100 dark:border-gray-600';

            // Text color for future dates
            $futureTextClass = $isFutureDate ? 'text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300';
            $dateColumnTextClass = $isFutureDate ? 'text-gray-400 dark:text-gray-300' : ($isTotalRow ? 'text-gray-700 dark:text-gray-100' : 'text-gray-700 dark:text-gray-100');
          @endphp

          <tr
            class="{{ $isTotalRow ? 'border-t-2 border-gray-200 dark:border-t-2 dark:border-gray-500' : 'border-b border-gray-100 dark:border-b-gray-600' }}"
          >
            <!-- Date Column -->
            <td
              class="{{ $dateCellClasses }} {{ $dateColumnTextClass }} p-2 font-semibold whitespace-nowrap"
            >
              @if ($isTotalRow)
                Totals
              @else
                <div class="flex items-center gap-2">
                  {{ $dayDate->translatedFormat('l d') }}

                  @if ($dayDate->isToday())
                    <x-badge size="sm" variant="primary">Today</x-badge>
                  @endif
                </div>
              @endif
            </td>

            <!-- Scheduled -->
            <td
              class="{{ $dataCellClasses }} {{ $futureTextClass }} p-2 whitespace-nowrap"
            >
              <div class="flex flex-col gap-1">
                <x-tooltip>
                  <x-slot name="text">
                    <div class="flex flex-col gap-1">
                      @if (! empty($day->scheduled->slots))
                        @if ($day->scheduled->scheduleName)
                          <span
                            class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-100"
                          >
                            {{ $day->scheduled->scheduleName }}
                          </span>
                        @endif

                        @foreach ($day->scheduled->slots as $slot)
                          <span
                            class="text-xs text-gray-600 dark:text-gray-200"
                          >
                            {{ $slot }}
                          </span>
                        @endforeach
                      @else
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                          No data
                        </span>
                      @endif
                    </div>
                  </x-slot>
                  <span class="{{ $futureTextClass }}">
                    {{ $day->scheduled->duration !== '0h 0m' ? $day->scheduled->duration : '' }}
                  </span>
                </x-tooltip>
              </div>
            </td>

            <!-- Leave -->
            <td
              class="{{ $dataCellClasses }} {{ $futureTextClass }} p-2 whitespace-nowrap"
            >
              @if ($day->leave)
                <div
                  class="{{ $day->leave->status !== 'validate' ? 'opacity-60' : '' }} flex items-center gap-2"
                >
                  <x-tooltip>
                    <x-slot name="text">
                      <div class="flex max-w-xs flex-col gap-2">
                        <span
                          class="text-xs font-medium text-gray-600 dark:text-gray-300"
                        >
                          {{ $day->leave->duration }}
                        </span>

                        @if ($day->leave->isHalfDay)
                          <span
                            class="text-xs text-gray-600 dark:text-gray-300"
                          >
                            {{ Illuminate\Support\Str::ucfirst($day->leave->timePeriod) }}
                            ({{ $day->leave->halfDayTime ?? '—' }})
                          </span>
                        @elseif ($day->leave->durationDays == 1)
                          <span
                            class="text-xs text-gray-600 dark:text-gray-300"
                          >
                            Full day
                          </span>
                        @endif

                        @if ($day->leave->status !== 'validate')
                          <span
                            class="text-xs text-gray-500 italic dark:text-gray-400"
                          >
                            {{ $day->leave->status === 'confirm' ? 'Waiting approval' : 'Cancelled' }}
                          </span>
                        @endif

                        <span class="text-xs text-gray-500 dark:text-gray-400">
                          Type: {{ $day->leave->leaveType }}
                          @if ($day->leave->context)
                              ({{ $day->leave->context }})
                          @endif
                        </span>
                        @if ($day->leave->leaveTypeDescription)
                          <span
                            class="text-xs text-gray-500 dark:text-gray-400"
                          >
                            Description:
                            {{ $day->leave->leaveTypeDescription }}
                          </span>
                        @endif

                        <span class="text-xs text-gray-500 dark:text-gray-400">
                          Hours: {{ $day->leave->durationHours }}
                        </span>
                      </div>
                    </x-slot>
                    <span class="{{ $futureTextClass }}">
                      {{ $day->leave->durationHours !== '0h 0m' ? $day->leave->durationHours : '' }}
                    </span>
                  </x-tooltip>

                  @if ($day->leave->status === 'validate')
                    <x-badge variant="info" size="sm">
                      {{ $day->leave->leaveType ?? 'Leave' }}
                    </x-badge>
                  @elseif ($day->leave->status === 'confirm')
                    <x-tooltip text="Leave request is pending approval">
                      <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="size-4 text-yellow-500"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                      >
                        <path
                          stroke-linecap="round"
                          stroke-linejoin="round"
                          d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.79 4 4s-1.79 4-4 4c-1.742 0-3.223-.835-3.772-2M12 12H9m3 3h3m-3-3V6m0 9v3m0-9H6m9 0h3m0 0v3m0-9V6m0 9H9"
                        />
                      </svg>
                    </x-tooltip>
                  @elseif ($day->leave->status === 'cancel' || $day->leave->status === 'refuse')
                    <x-tooltip
                      text="Leave request was {{ $day->leave->status === 'cancel' ? 'cancelled' : 'refused' }}"
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
            </td>

            <!-- Attendance -->
            <td
              class="{{ $dataCellClasses }} {{ $futureTextClass }} p-2 whitespace-nowrap"
            >
              <x-tooltip>
                <x-slot name="text">
                  <div class="flex flex-col gap-1">
                    @if ($day->attendance && $day->attendance->isRemote)
                      <span class="text-xs text-gray-600 dark:text-gray-200">
                        Remote work
                      </span>
                    @endif
                  </div>
                </x-slot>
                <div class="flex flex-row items-center gap-2">
                  @if ($day->attendance)
                    <span class="{{ $futureTextClass }}">
                      {{ $day->attendance->duration !== '0h 0m' ? $day->attendance->duration : '' }}
                    </span>
                    @if ($day->attendance->isRemote)
                      <x-badge variant="info" size="sm">Remote</x-badge>
                    @elseif (collect($day->attendance->times)->isNotEmpty())
                      <x-badge variant="success" size="sm">In Office</x-badge>
                    @endif
                  @else
                    <span class="{{ $futureTextClass }}">&nbsp;</span>
                  @endif
                </div>
              </x-tooltip>
            </td>

            <!-- Worked -->
            <td
              class="{{ $dataCellClasses }} {{ $futureTextClass }} p-2 whitespace-nowrap"
            >
              <div class="flex flex-col gap-1">
                <x-tooltip>
                  <x-slot name="text">
                    <div class="flex max-w-xs flex-col gap-2">
                      @if ($day->worked && collect($day->worked->detailedEntries)->isNotEmpty())
                        @foreach (collect($day->worked->detailedEntries) as $entry)
                          <div
                            class="{{ ! $loop->last ? 'mb-1 ' : '' }} flex flex-col"
                          >
                            <div class="mb-1 flex items-center justify-between">
                              <span
                                class="text-xs font-medium text-gray-800 dark:text-gray-100"
                              >
                                {{ $entry->project }}
                              </span>
                              <span
                                class="ml-2 rounded bg-gray-100 px-1.5 py-0.5 text-xs whitespace-nowrap text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                              >
                                {{ $entry->duration }}
                              </span>
                            </div>
                            @if (isset($entry->task) && $entry->task)
                              <span
                                class="mb-0.5 text-xs text-gray-600 dark:text-gray-300"
                              >
                                {{ $entry->task }}
                              </span>
                            @endif

                            @if (isset($entry->description) && $entry->description)
                              <span
                                class="text-xs text-gray-500 italic dark:text-gray-400"
                              >
                                {{ Illuminate\Support\Str::limit($entry->description, 80) }}
                              </span>
                            @endif
                          </div>
                        @endforeach
                      @elseif ($day->worked && collect($day->worked->projects)->isNotEmpty())
                        <div class="flex flex-col">
                          @foreach (collect($day->worked->projects) as $project)
                            <div class="{{ ! $loop->last ? 'mb-2' : '' }}">
                              <span
                                class="text-xs font-medium text-gray-800 dark:text-gray-100"
                              >
                                {{ $project->name }}
                              </span>
                              @if (collect($project->tasks)->isNotEmpty())
                                <div
                                  class="mt-1 text-xs text-gray-600 dark:text-gray-300"
                                >
                                  {{ collect($project->tasks)->join(', ') }}
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
                    {{ $day->worked && $day->worked->duration !== '0h 0m' ? $day->worked->duration : '' }}
                  </span>
                </x-tooltip>
              </div>
            </td>

            <!-- Deviation Columns -->
            @if ($showDeviations)
              <!-- Attendance vs Scheduled -->
              @php
                $attVsSchDetail = $day->deviationDetails ? $day->deviationDetails->attendanceVsScheduled : null;
                $attVsSchPercentage = $attVsSchDetail ? $attVsSchDetail->percentage : 0;
                $attVsSchShouldDisplay = ! $isFutureDate && $showDeviations && ($attVsSchDetail ? $attVsSchDetail->shouldDisplay : false);
                $attVsSchBgClass = '';
                $attVsSchTextClass = '';
                if ($attVsSchShouldDisplay) {
                  if ($attVsSchPercentage > 0) {
                    $attVsSchBgClass = 'bg-green-50 dark:bg-green-900/30';
                    $attVsSchTextClass = 'text-green-600 dark:text-green-600';
                  } elseif ($attVsSchPercentage <= -50) {
                    $attVsSchBgClass = 'bg-red-50 dark:bg-red-900/30';
                    $attVsSchTextClass = 'text-red-600 dark:text-red-600';
                  } elseif ($attVsSchPercentage < 0) {
                    $attVsSchBgClass = 'bg-yellow-50 dark:bg-yellow-900/30';
                    $attVsSchTextClass = 'text-yellow-500 dark:text-yellow-500';
                  }
                }
              @endphp

              @php
                $deviationCellSpecificBgClass = '';
                if ($attVsSchShouldDisplay) {
                  $deviationCellSpecificBgClass = $attVsSchBgClass;
                }
              @endphp

              <td
                class="{{ $deviationCellSpecificBgClass ?: ($isFutureDate ? 'bg-gray-50 dark:bg-gray-700' : 'bg-white dark:bg-gray-800') }} {{ ! $attVsSchShouldDisplay ? 'text-transparent' : '' }} {{ $isFutureDate ? 'text-gray-400 dark:text-gray-500' : '' }} border border-gray-100 p-2 whitespace-nowrap dark:border-gray-600"
              >
                @if ($attVsSchShouldDisplay && $attVsSchDetail)
                  <x-tooltip :text="$attVsSchDetail->tooltip">
                    <span class="{{ $attVsSchTextClass }}">
                      {{ $attVsSchPercentage > 0 ? '+' : '' }}{{ $attVsSchPercentage }}%
                    </span>
                  </x-tooltip>
                @endif
              </td>

              <!-- Worked vs Scheduled -->
              @php
                $workVsSchDetail = $day->deviationDetails ? $day->deviationDetails->workedVsScheduled : null;
                $workVsSchPercentage = $workVsSchDetail ? $workVsSchDetail->percentage : 0;
                $workVsSchShouldDisplay = ! $isFutureDate && $showDeviations && ($workVsSchDetail ? $workVsSchDetail->shouldDisplay : false);
                $workVsSchBgClass = '';
                $workVsSchTextClass = '';
                if ($workVsSchShouldDisplay) {
                  if ($workVsSchPercentage > 0) {
                    $workVsSchBgClass = 'bg-green-50 dark:bg-green-900/30';
                    $workVsSchTextClass = 'text-green-600 dark:text-green-600';
                  } elseif ($workVsSchPercentage <= -50) {
                    $workVsSchBgClass = 'bg-red-50 dark:bg-red-900/30';
                    $workVsSchTextClass = 'text-red-600 dark:text-red-600';
                  } elseif ($workVsSchPercentage < 0) {
                    $workVsSchBgClass = 'bg-yellow-50 dark:bg-yellow-900/30';
                    $workVsSchTextClass = 'text-yellow-500 dark:text-yellow-500';
                  }
                }
              @endphp

              @php
                $deviationCellSpecificBgClass = '';
                if ($workVsSchShouldDisplay) {
                  $deviationCellSpecificBgClass = $workVsSchBgClass;
                }
              @endphp

              <td
                class="{{ $deviationCellSpecificBgClass ?: ($isFutureDate ? 'bg-gray-50 dark:bg-gray-700' : 'bg-white dark:bg-gray-800') }} {{ ! $workVsSchShouldDisplay ? 'text-transparent' : '' }} {{ $isFutureDate ? 'text-gray-400 dark:text-gray-500' : '' }} border border-gray-100 p-2 whitespace-nowrap dark:border-gray-600"
              >
                @if ($workVsSchShouldDisplay && $workVsSchDetail)
                  <x-tooltip :text="$workVsSchDetail->tooltip">
                    <span class="{{ $workVsSchTextClass }}">
                      {{ $workVsSchPercentage > 0 ? '+' : '' }}{{ $workVsSchPercentage }}%
                    </span>
                  </x-tooltip>
                @endif
              </td>

              <!-- Worked vs Attendance -->
              @php
                $workVsAttDetail = $day->deviationDetails ? $day->deviationDetails->workedVsAttendance : null;
                $workVsAttPercentage = $workVsAttDetail ? $workVsAttDetail->percentage : 0;
                $workVsAttShouldDisplay = ! $isFutureDate && $showDeviations && ($workVsAttDetail ? $workVsAttDetail->shouldDisplay : false);
                $workVsAttBgClass = '';
                $workVsAttTextClass = '';
                if ($workVsAttShouldDisplay) {
                  if ($workVsAttPercentage > 0) {
                    $workVsAttBgClass = 'bg-green-50 dark:bg-green-900/30';
                    $workVsAttTextClass = 'text-green-600 dark:text-green-600';
                  } elseif ($workVsAttPercentage <= -50) {
                    $workVsAttBgClass = 'bg-red-50 dark:bg-red-900/30';
                    $workVsAttTextClass = 'text-red-600 dark:text-red-600';
                  } elseif ($workVsAttPercentage < 0) {
                    $workVsAttBgClass = 'bg-yellow-50 dark:bg-yellow-900/30';
                    $workVsAttTextClass = 'text-yellow-500 dark:text-yellow-500';
                  }
                }
              @endphp

              @php
                $deviationCellSpecificBgClass = '';
                if ($workVsAttShouldDisplay) {
                  $deviationCellSpecificBgClass = $workVsAttBgClass;
                }
              @endphp

              <td
                class="{{ $deviationCellSpecificBgClass ?: ($isFutureDate ? 'bg-gray-50 dark:bg-gray-700' : 'bg-white dark:bg-gray-800') }} {{ ! $workVsAttShouldDisplay ? 'text-transparent' : '' }} {{ $isFutureDate ? 'text-gray-400 dark:text-gray-500' : '' }} border border-gray-100 p-2 whitespace-nowrap dark:border-gray-600"
              >
                @if ($workVsAttShouldDisplay && $workVsAttDetail)
                  <x-tooltip :text="$workVsAttDetail->tooltip">
                    <span class="{{ $workVsAttTextClass }}">
                      {{ $workVsAttPercentage > 0 ? '+' : '' }}{{ $workVsAttPercentage }}%
                    </span>
                  </x-tooltip>
                @endif
              </td>
            @endif
          </tr>
        @endforeach
      </tbody>
      <tfoot class="text-sm">
        <!-- Totals Row -->
        <tr
          class="border-t-2 border-gray-200 bg-gray-50 font-bold dark:border-t-2 dark:border-gray-600 dark:bg-gray-700"
        >
          <td
            class="border border-gray-200 p-2 whitespace-nowrap text-gray-800 dark:border-gray-600 dark:text-gray-100"
          >
            <x-tooltip
              text="Totals only include past dates and today. Future dates are not counted in calculations."
            >
              <div class="flex items-center gap-1">
                Totals
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  class="size-4"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke="currentColor"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                  />
                </svg>
              </div>
            </x-tooltip>
          </td>

          @foreach (collect(['scheduled', 'leave', 'attendance', 'worked']) as $type)
            <td
              class="border border-gray-200 p-2 whitespace-nowrap text-gray-800 dark:border-gray-600 dark:text-gray-100"
            >
              @php
                $value = 0;
                switch ($type) {
                  case 'scheduled':
                    $value = $dashboardTotals->scheduled;
                    break;
                  case 'leave':
                    $value = $dashboardTotals->leave;
                    break;
                  case 'attendance':
                    $value = $dashboardTotals->attendance;
                    break;
                  case 'worked':
                    $value = $dashboardTotals->worked;
                    break;
                }
              @endphp

              {{ $value > 0 ? $this->formatMinutesToHoursMinutes($value) : '' }}
            </td>
          @endforeach

          @if ($showDeviations && $totalDeviationsDetails)
            @foreach ($totalDeviationsDetails as $deviationType => $details)
              @php
                $percentage = $details->percentage ?? 0;
                $totalShouldDisplay = $showDeviations && ($details->shouldDisplay ?? false) && isset($details->tooltip);
                $totalBgClass = '';
                $totalTextClass = '';
                if ($totalShouldDisplay) {
                  if ($percentage > 0) {
                    $totalBgClass = 'bg-green-50 dark:bg-green-900/30';
                    $totalTextClass = 'text-green-600 dark:text-green-600';
                  } elseif ($percentage <= -50) {
                    $totalBgClass = 'bg-red-50 dark:bg-red-900/30';
                    $totalTextClass = 'text-red-600 dark:text-red-600';
                  } elseif ($percentage < 0) {
                    $totalBgClass = 'bg-yellow-50 dark:bg-yellow-900/30';
                    $totalTextClass = 'text-yellow-500 dark:text-yellow-500';
                  } else {
                    $totalTextClass = 'text-gray-700 dark:text-gray-300';
                  }
                }
              @endphp

              @php
                $footerCellBaseClasses = 'border border-gray-200 dark:border-gray-600';
                $footerTextClasses = 'text-gray-800 dark:text-gray-100';
              @endphp

              <td
                class="{{ $totalBgClass ?: 'bg-gray-50 dark:bg-gray-700' }} {{ $footerCellBaseClasses }} {{ ! $totalShouldDisplay ? 'text-transparent' : '' }} {{ $totalTextClass ?: $footerTextClasses }} p-2 whitespace-nowrap"
              >
                @if ($totalShouldDisplay)
                  <x-tooltip :text="$details->tooltip">
                    <span class="{{ $totalTextClass }}">
                      {{ $percentage > 0 ? '+' : '' }}{{ $percentage }}%
                    </span>
                  </x-tooltip>
                @endif
              </td>
            @endforeach
          @endif
        </tr>
      </tfoot>
    </table>
  </div>
</div>
