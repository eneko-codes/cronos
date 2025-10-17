<div
  class="flex flex-col gap-4 rounded-lg bg-white p-4 shadow-md dark:bg-gray-800"
>
  <!-- Period Controls -->
  <div
    class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
  >
    <div class="flex flex-row items-center gap-4">
      <livewire:dashboard.period-navigator
        :current-date="$currentDate"
        :view-mode="$viewMode"
        wire:key="period-navigator-{{ $userId }}"
      />
    </div>

    <!-- View Mode Toggles -->
    <div class="flex items-center gap-2">
      <!-- Deviations Toggle -->
      <x-toggle-button
        :active="$showDeviations"
        label="Deviations"
        wire:click="toggleDeviations"
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
        :filters="collect(['weekly' => 'Weekly', 'monthly' => 'Monthly'])"
        onFilterChange="changeViewMode"
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
                  <x-slot name="text">{{ $tooltip }}</x-slot>
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
                    <x-slot name="text">{{ $tooltip }}</x-slot>
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
        @foreach ($periodData as $dayData)
          @php
            $isFutureDate = $dayData->isFuture();
            $isWeekend = $dayData->isWeekend();
            $isPastOrToday = $dayData->isPastOrToday();

            $dateCellClasses = 'border border-gray-200 bg-gray-50 dark:border-gray-600 dark:bg-gray-700';
            $dataCellClasses = 'border border-gray-100 bg-white dark:border-gray-600 dark:bg-gray-800';
            $futureTextClass = $isFutureDate ? 'text-gray-400 dark:text-gray-500' : 'text-gray-700 dark:text-gray-300';
            $dateColumnTextClass = $isFutureDate ? 'text-gray-400 dark:text-gray-300' : 'text-gray-700 dark:text-gray-100';
          @endphp

          <tr class="border-b border-gray-100 dark:border-b-gray-600">
            <!-- Date Column -->
            <td
              class="{{ $dateCellClasses }} {{ $dateColumnTextClass }} p-2 font-semibold whitespace-nowrap"
            >
              <div class="flex items-center gap-2">
                {{ \Carbon\Carbon::parse($dayData->date)->translatedFormat('l d') }}
                @if ($dayData->isToday())
                  <x-badge size="sm" variant="primary">Today</x-badge>
                @endif
              </div>
            </td>

            <!-- Data Columns -->
            <x-dashboard.table-cell
              :day-data="$dayData"
              type="scheduled"
              :show-deviations="$showDeviations"
            />
            <x-dashboard.table-cell
              :day-data="$dayData"
              type="leave"
              :show-deviations="$showDeviations"
            />
            <x-dashboard.table-cell
              :day-data="$dayData"
              type="attendance"
              :show-deviations="$showDeviations"
            />
            <x-dashboard.table-cell
              :day-data="$dayData"
              type="worked"
              :show-deviations="$showDeviations"
            />

            <!-- Deviation Columns -->
            @if ($showDeviations)
              <x-dashboard.table-cell
                :day-data="$dayData"
                :show-deviations="$showDeviations"
                deviation-type="attendanceVsScheduled"
              />
              <x-dashboard.table-cell
                :day-data="$dayData"
                :show-deviations="$showDeviations"
                deviation-type="workedVsScheduled"
              />
              <x-dashboard.table-cell
                :day-data="$dayData"
                :show-deviations="$showDeviations"
                deviation-type="workedVsAttendance"
              />
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

          @foreach (['scheduled', 'leave', 'attendance', 'worked'] as $type)
            <td
              class="border border-gray-200 p-2 whitespace-nowrap text-gray-800 dark:border-gray-600 dark:text-gray-100"
            >
              {{ $totals->{'getFormatted' . ucfirst($type)}() }}
            </td>
          @endforeach

          @if ($showDeviations && $overallDeviations)
            @foreach ($overallDeviations as $deviationType => $details)
              <td
                class="border border-gray-200 p-2 whitespace-nowrap text-gray-800 dark:border-gray-600 dark:text-gray-100"
              >
                @if ($details['shouldDisplay'])
                  <x-tooltip :text="$details['tooltip']">
                    <span
                      class="{{ $details['percentage'] > 0 ? 'text-green-600' : ($details['percentage'] <= -50 ? 'text-red-600' : ($details['percentage'] < 0 ? 'text-yellow-500' : 'text-gray-700')) }}"
                    >
                      {{ $details['percentage'] > 0 ? '+' : '' }}{{ $details['percentage'] }}%
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
