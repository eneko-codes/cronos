<div class="mx-auto max-w-screen-2xl px-4 py-6">
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
          {{ $user->name }} is currently set to do not track
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
                // Simplified - use UTC directly without timezone conversion
                $carbonDate = \Carbon\Carbon::parse($currentDate, 'UTC');
              @endphp

              {{ $viewMode === 'weekly' ? 'Week' : 'Month' }} of {{ $carbonDate->format('F d, Y') }}
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
          <!-- Period Toggle (Weekly/Monthly) -->
          <div class="inline-flex w-fit gap-1 whitespace-nowrap rounded-lg border border-gray-200 bg-gray-100 p-1 text-xs font-semibold dark:border-gray-700 dark:bg-gray-800">
            <button
              type="button"
              wire:click="setViewMode('weekly')"
              class="{{ $viewMode === 'weekly' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Weekly
            </button>
            <button
              type="button"
              wire:click="setViewMode('monthly')"
              class="{{ $viewMode === 'monthly' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Monthly
            </button>
          </div>

          <!-- Display Toggle (Table/Graph) -->
          <div class="inline-flex w-fit gap-1 whitespace-nowrap rounded-lg border border-gray-200 bg-gray-100 p-1 text-xs font-semibold dark:border-gray-700 dark:bg-gray-800">
            <button
              type="button"
              wire:click="setDisplayMode('table')"
              class="{{ $displayMode === 'table' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Table
            </button>
            <button
              type="button"
              wire:click="setDisplayMode('graph')"
              class="{{ $displayMode === 'graph' ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
              Graph
            </button>
          </div>
        </div>
      </div>

      <!-- Table View -->
      @if ($displayMode === 'table')
        <!-- Data Table -->
        <div class="overflow-x-auto">
          <table
            class="w-full min-w-[800px] rounded-lg bg-gray-50 text-sm dark:bg-gray-800"
          >
            <thead>
              <tr class="bg-gray-200 text-left font-bold dark:bg-gray-800">
                <th class="px-4 py-2">Day</th>
                @foreach ([
                    'Scheduled' => 'Hours from Odoo calendar',
                    'Leave' => 'Time off from Odoo',
                    'Attendance' => 'Hours from Desktime/SystemPin',
                    'Worked' => 'Hours from Proofhub'
                  ]
                  as $name => $tooltip)
                  <th class="px-4 py-2">
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
              </tr>
            </thead>
            <tbody class="bg-gray-100 dark:bg-gray-700">
              @foreach ($this->getPeriodData() as $day)
                @php
                  $dayDate = \Carbon\Carbon::parse($day['date']);
                  $isFutureDate = $dayDate->isFuture();
                @endphp
                <tr class="border-t border-gray-200 dark:border-gray-600 {{ $isFutureDate ? 'bg-gray-200 dark:bg-gray-800 text-gray-500 dark:text-gray-400' : '' }}">
                  <!-- Date Column -->
                  <td class="whitespace-nowrap px-4 py-2 font-semibold">
                    <div class="flex items-center gap-2">
                      {{ $dayDate->format('l d') }}
                      
                      @if($dayDate->isToday())
                        <x-badge size="sm" variant="primary">Today</x-badge>
                      @endif
                    </div>
                  </td>

                  <!-- Scheduled -->
                  <td class="px-4 py-2">
                    <div class="flex flex-col gap-1">
                      <x-tooltip>
                        <x-slot name="text">
                          <div class="flex flex-col gap-1">
                            @if(count($day['scheduled']['slots']) > 0)
                              @if(isset($day['scheduled']['schedule_name']))
                                <span class="text-xs font-medium text-gray-700 dark:text-gray-100 mb-1">
                                  {{ $day['scheduled']['schedule_name'] }}
                                </span>
                              @endif
                              @foreach ($day['scheduled']['slots'] as $slot)
                                <span class="text-xs text-gray-600 dark:text-gray-200">
                                  {{ $slot }}
                                </span>
                              @endforeach
                            @else
                              <span class="text-xs text-gray-500 dark:text-gray-400">No data</span>
                            @endif
                          </div>
                        </x-slot>
                        <span>{{ $day['scheduled']['duration'] !== '0h 0m' ? $day['scheduled']['duration'] : '' }}</span>
                      </x-tooltip>
                    </div>
                  </td>

                  <!-- Leave -->
                  <td class="px-4 py-2">
                    <!-- Simplified default view - only show when leave exists -->
                    @if ($day['leave'])
                      <div class="flex items-center gap-2 {{ $day['leave']['status'] !== 'validate' ? 'opacity-60' : '' }}">
                        <!-- Main leave information with tooltip -->
                        <div>
                          <x-tooltip>
                            <x-slot name="text">
                              <div class="flex flex-col gap-2 max-w-xs">
                                <!-- Duration in days -->
                                <div class="flex flex-row items-center gap-1 mb-1">
                                  <span class="text-xs text-gray-600 dark:text-gray-300 font-medium">
                                    {{ $day['leave']['duration'] }}
                                  </span>
                                </div>
                                
                                <!-- Time details -->
                                @if ($day['leave']['is_half_day'])
                                  <div>
                                    <span class="text-xs text-gray-600 dark:text-gray-300">
                                      {{ ucfirst($day['leave']['time_period']) }} ({{ $day['leave']['half_day_time'] ?? '—' }})
                                    </span>
                                  </div>
                                @else
                                  <div>
                                    <span class="text-xs text-gray-600 dark:text-gray-300">
                                      Time: {{ $day['leave']['time_range'] }}
                                    </span>
                                  </div>
                                @endif
                              </div>
                            </x-slot>
                            
                            <div class="flex items-center gap-2">
                              <!-- Leave duration - show 8h 0m if it's approved but showing 0h 0m -->
                              <span class="text-sm font-medium">
                                @if ($day['leave']['status'] === 'validate' && $day['leave']['duration_hours'] === '0h 0m')
                                  8h 0m
                                @else
                                  {{ $day['leave']['duration_hours'] }}
                                @endif
                              </span>
                              
                              <!-- Leave type badge -->
                              <span class="rounded bg-blue-100 px-1.5 py-0.5 text-xs font-medium text-blue-800">
                                {{ $day['leave']['leave_type'] }}
                                @if ($day['leave']['context'])
                                  ({{ $day['leave']['context'] }})
                                @endif
                                
                                <!-- Only show half-day indicator in badge -->
                                @if (isset($day['leave']['is_half_day']) && $day['leave']['is_half_day'])
                                  <span class="ml-1 inline-flex items-center rounded-full bg-blue-50 px-1 text-xs font-medium text-blue-700">
                                    {{ substr(ucfirst($day['leave']['time_period']), 0, 1) }}
                                  </span>
                                @endif
                              </span>
                            </div>
                          </x-tooltip>
                        </div>
                        
                        <!-- Status indicator icon with separate tooltip -->
                        @if ($day['leave']['status'] === 'validate')
                          <!-- Green checkmark for validated leaves -->
                          <x-tooltip text="Fully approved leave - counts toward leave totals">
                            <span class="text-green-500">
                              <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                              </svg>
                            </span>
                          </x-tooltip>
                        @else
                          <!-- Status-specific icon for non-validated statuses -->
                          <span class="{{ 
                            match($day['leave']['status']) {
                              'refuse' => 'text-red-500',
                              'confirm' => 'text-yellow-500',
                              'validate1' => 'text-orange-500',
                              'draft' => 'text-gray-400',
                              'cancel' => 'text-red-300',
                              default => 'text-yellow-500'
                            }
                          }}">
                            <!-- Status-specific icon -->
                            @if($day['leave']['status'] === 'refuse')
                              <x-tooltip text="Leave request was denied by a manager">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                              </x-tooltip>
                            @elseif($day['leave']['status'] === 'confirm')
                              <x-tooltip text="Leave request submitted, awaiting approval">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                              </x-tooltip>
                            @elseif($day['leave']['status'] === 'validate1')
                              <x-tooltip text="First approval received, awaiting final validation">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                              </x-tooltip>
                            @elseif($day['leave']['status'] === 'draft')
                              <x-tooltip text="Leave request created but not yet submitted">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                  <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z" />
                                </svg>
                              </x-tooltip>
                            @elseif($day['leave']['status'] === 'cancel')
                              <x-tooltip text="Leave request was cancelled">
                                <svg xmlns="http://www.w3.org/2000/svg" class="size-4" viewBox="0 0 20 20" fill="currentColor">
                                  <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM7 9a1 1 0 000 2h6a1 1 0 100-2H7z" clip-rule="evenodd" />
                                </svg>
                              </x-tooltip>
                            @endif
                          </span>
                        @endif
                      </div>
                    @endif
                  </td>

                  <!-- Attendance -->
                  <td class="px-4 py-2">
                    <x-tooltip>
                      <x-slot name="text">
                        <div class="flex flex-col gap-1">
                          @if ($day['attendance']['is_remote'])
                            <span class="text-xs text-gray-600 dark:text-gray-200">Remote work</span>
                          @elseif (! empty($day['attendance']['times']))
                            <span class="text-xs text-gray-600 dark:text-gray-200">
                              {{ implode(' → ', $day['attendance']['times']) }}
                            </span>
                          @else
                            <span class="text-xs text-gray-500 dark:text-gray-400">No data</span>
                          @endif
                        </div>
                      </x-slot>
                      <div class="flex flex-row items-center gap-2">
                        <span>{{ $day['attendance']['duration'] !== '0h 0m' ? $day['attendance']['duration'] : '' }}</span>
                        @if ($day['attendance']['is_remote'])
                          <x-badge variant="info" size="sm">Remote</x-badge>
                        @elseif (! empty($day['attendance']['times']))
                          <x-badge variant="success" size="sm">In Office</x-badge>
                        @endif
                      </div>
                    </x-tooltip>
                  </td>

                  <!-- Worked -->
                  <td class="px-4 py-2">
                    <div class="flex flex-col gap-1">
                      <x-tooltip>
                        <x-slot name="text">
                          <div class="flex flex-col gap-2 max-w-xs">
                            @if(isset($day['worked']['detailed_entries']) && count($day['worked']['detailed_entries']) > 0)
                              @foreach ($day['worked']['detailed_entries'] as $entry)
                                <div class="flex flex-col {{ !$loop->last ? 'border-b border-gray-200 dark:border-gray-600 pb-3 mb-3' : '' }}">
                                  <div class="flex items-center justify-between mb-1">
                                    <span class="font-medium text-xs text-gray-800 dark:text-gray-100">
                                      {{ $entry['project'] }}
                                    </span>
                                    <span class="ml-2 whitespace-nowrap text-xs px-1.5 py-0.5 bg-gray-100 dark:bg-gray-700 rounded text-gray-700 dark:text-gray-300">
                                      {{ $entry['duration'] }}
                                    </span>
                                  </div>
                                  @if (isset($entry['task']) && $entry['task'])
                                    <span class="text-xs text-gray-600 dark:text-gray-300 mb-0.5">
                                      {{ $entry['task'] }}
                                    </span>
                                  @endif
                                  @if (isset($entry['description']) && $entry['description'])
                                    <span class="text-xs text-gray-500 dark:text-gray-400 italic">
                                      {{ Str::limit($entry['description'], 80) }}
                                    </span>
                                  @endif
                                </div>
                              @endforeach
                            @elseif(count($day['worked']['projects']) > 0)
                              <div class="flex flex-col">
                                @foreach ($day['worked']['projects'] as $project)
                                  <div class="{{ !$loop->last ? 'mb-3' : '' }}">
                                    <span class="font-medium text-xs text-gray-800 dark:text-gray-100">
                                      {{ $project['name'] }}
                                    </span>
                                    @if (! empty($project['tasks']))
                                      <div class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                        {{ implode(', ', $project['tasks']) }}
                                      </div>
                                    @endif
                                  </div>
                                @endforeach
                              </div>
                            @else
                              <span class="text-xs text-gray-500 dark:text-gray-400">No data</span>
                            @endif
                          </div>
                        </x-slot>
                        <span>{{ $day['worked']['duration'] !== '0h 0m' ? $day['worked']['duration'] : '' }}</span>
                      </x-tooltip>
                    </div>
                  </td>
                </tr>
              @endforeach

              <!-- Totals Row -->
              <tr
                class="border-t-2 border-gray-300 font-bold dark:border-gray-500"
              >
                <td class="whitespace-nowrap px-4 py-2">Totals</td>
                <td class="px-4 py-2">
                  <!-- Convert minutes to "Xh Ym" -->
                  @php
                    $scheduledMins = $this->getTotals()['scheduled'];
                    $scheduledH = intdiv($scheduledMins, 60);
                    $scheduledR = $scheduledMins % 60;
                  @endphp

                  {{ $scheduledMins > 0 ? "{$scheduledH}h {$scheduledR}m" : '' }}
                </td>
                <td class="px-4 py-2">
                  @php
                    $leaveMins = $this->getTotals()['leave'];
                    $leaveH = intdiv($leaveMins, 60);
                    $leaveR = $leaveMins % 60;
                  @endphp

                  {{ $leaveMins > 0 ? "{$leaveH}h {$leaveR}m" : '' }}
                </td>
                <td class="px-4 py-2">
                  @php
                    $attendanceMins = $this->getTotals()['attendance'];
                    $attendanceH = intdiv($attendanceMins, 60);
                    $attendanceR = $attendanceMins % 60;
                  @endphp

                  {{ $attendanceMins > 0 ? "{$attendanceH}h {$attendanceR}m" : '' }}
                </td>
                <td class="px-4 py-2">
                  @php
                    $workedMins = $this->getTotals()['worked'];
                    $workedH = intdiv($workedMins, 60);
                    $workedR = $workedMins % 60;
                  @endphp

                  {{ $workedMins > 0 ? "{$workedH}h {$workedR}m" : '' }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      @else
        <!-- Graph View -->
        <div class="w-full rounded-lg bg-gray-50 p-6 dark:bg-gray-800">
          <div class="mb-4 flex justify-between">
            <h3 class="text-sm font-medium">Hours Distribution</h3>
            
            <!-- Graph Legend -->
            <div class="flex items-center gap-3 text-xs">
              <div class="flex items-center gap-1">
                <span class="block h-3 w-3 rounded-sm bg-blue-500"></span>
                <span>Scheduled</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="block h-3 w-3 rounded-sm bg-green-500"></span>
                <span>Attendance</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="block h-3 w-3 rounded-sm bg-purple-500"></span>
                <span>Worked</span>
              </div>
              <div class="flex items-center gap-1">
                <span class="block h-3 w-3 rounded-sm bg-yellow-500"></span>
                <span>Leave</span>
              </div>
            </div>
          </div>
          
          <!-- Chart Container -->
          <div 
            x-data="{
              chart: null,
              chartData: @js($this->getChartData()),
              initChart() {
                // Define chart options
                const options = {
                  chart: {
                    type: 'bar',
                    height: 300,
                    stacked: true,
                    toolbar: {
                      show: false
                    },
                    animations: {
                      enabled: false
                    },
                    fontFamily: 'inherit',
                  },
                  plotOptions: {
                    bar: {
                      horizontal: false,
                      columnWidth: '70%',
                      borderRadius: 4
                    },
                  },
                  dataLabels: {
                    enabled: false
                  },
                  colors: this.chartData.datasets.map(dataset => dataset.color),
                  series: this.chartData.datasets.map(dataset => ({
                    name: dataset.name,
                    data: dataset.data
                  })),
                  xaxis: {
                    categories: this.chartData.labels,
                    labels: {
                      style: {
                        colors: '#64748b', // text-slate-500
                        cssClass: 'text-xs'
                      }
                    },
                    axisBorder: {
                      show: false
                    },
                    axisTicks: {
                      show: false
                    }
                  },
                  yaxis: {
                    labels: {
                      style: {
                        colors: '#64748b', // text-slate-500
                        cssClass: 'text-xs'
                      },
                      formatter: function(val) {
                        return val.toFixed(1) + 'h';
                      }
                    }
                  },
                  tooltip: {
                    y: {
                      formatter: function(val) {
                        return val.toFixed(1) + ' hours';
                      }
                    }
                  },
                  legend: {
                    show: false
                  },
                  grid: {
                    show: true,
                    borderColor: '#e2e8f0', // border-slate-200
                    strokeDashArray: 1,
                    padding: {
                      left: 0,
                      right: 0
                    }
                  },
                  theme: {
                    mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light',
                    palette: 'palette1'
                  }
                };
                
                // Initialize the chart
                this.chart = new ApexCharts(this.$refs.chartContainer, options);
                this.chart.render();

                // Dark mode detection
                const observer = new MutationObserver(mutations => {
                  mutations.forEach(mutation => {
                    if (mutation.attributeName === 'class') {
                      const isDarkMode = document.documentElement.classList.contains('dark');
                      this.chart.updateOptions({
                        theme: {
                          mode: isDarkMode ? 'dark' : 'light'
                        },
                        grid: {
                          borderColor: isDarkMode ? '#334155' : '#e2e8f0' // dark:border-slate-700 : border-slate-200
                        }
                      });
                    }
                  });
                });
                
                observer.observe(document.documentElement, { attributes: true });
                
                // Listen for refresh-chart events from Livewire
                window.addEventListener('refresh-chart', (event) => {
                  if (this.chart) {
                    const newData = event.detail.data;
                    this.chartData = newData;
                    
                    this.chart.updateOptions({
                      colors: newData.datasets.map(dataset => dataset.color),
                      xaxis: {
                        categories: newData.labels,
                      }
                    });
                    
                    this.chart.updateSeries(
                      newData.datasets.map(dataset => ({
                        name: dataset.name,
                        data: dataset.data
                      }))
                    );
                  }
                });
              }
            }"
            x-init="$nextTick(() => { 
              // Load ApexCharts from CDN if not already loaded
              if (typeof ApexCharts === 'undefined') {
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/apexcharts';
                script.onload = () => initChart();
                document.head.appendChild(script);
              } else {
                initChart();
              }
            })"
            wire:ignore
            class="relative h-80 w-full overflow-hidden rounded-lg border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800"
          >
            <div x-ref="chartContainer" class="h-full w-full"></div>
          </div>
          
          <!-- Totals below graph -->
          <div class="mt-4 grid grid-cols-4 gap-4">
            @php
              $totals = $this->getTotals();
              
              $scheduledMins = $totals['scheduled'];
              $scheduledH = intdiv($scheduledMins, 60);
              $scheduledR = $scheduledMins % 60;
              
              $leaveMins = $totals['leave'];
              $leaveH = intdiv($leaveMins, 60);
              $leaveR = $leaveMins % 60;
              
              $attendanceMins = $totals['attendance'];
              $attendanceH = intdiv($attendanceMins, 60);
              $attendanceR = $attendanceMins % 60;
              
              $workedMins = $totals['worked'];
              $workedH = intdiv($workedMins, 60);
              $workedR = $workedMins % 60;
            @endphp
            
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
              <div class="text-xs text-gray-500 dark:text-gray-400">Scheduled</div>
              <div class="text-lg font-semibold">{{ $scheduledMins > 0 ? "{$scheduledH}h {$scheduledR}m" : '0h 0m' }}</div>
            </div>
            
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
              <div class="text-xs text-gray-500 dark:text-gray-400">Leave</div>
              <div class="text-lg font-semibold">{{ $leaveMins > 0 ? "{$leaveH}h {$leaveR}m" : '0h 0m' }}</div>
            </div>
            
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
              <div class="text-xs text-gray-500 dark:text-gray-400">Attendance</div>
              <div class="text-lg font-semibold">{{ $attendanceMins > 0 ? "{$attendanceH}h {$attendanceR}m" : '0h 0m' }}</div>
            </div>
            
            <div class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-800">
              <div class="text-xs text-gray-500 dark:text-gray-400">Worked</div>
              <div class="text-lg font-semibold">{{ $workedMins > 0 ? "{$workedH}h {$workedR}m" : '0h 0m' }}</div>
            </div>
          </div>
        </div>
      @endif
    @endif
  </div>
</div>
