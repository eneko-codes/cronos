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
                $tz = session('timezone', 'UTC');
                $carbonDate = \Carbon\Carbon::parse($currentDate, 'UTC')->setTimezone($tz);
              @endphp

              Week of {{ $carbonDate->format('F d, Y') }}
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

        <div class="flex items-center gap-4">
          @if (auth()->user()->is_admin)
            <!-- Manual sync button -->
            <x-tooltip text="Synchronize data from all sources">
              <livewire:sync-button :key="'users-page-sync'" sync-type="data" />
            </x-tooltip>
          @endif
        </div>
      </div>

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
              <tr class="border-t border-gray-200 dark:border-gray-600">
                <!-- Date Column -->
                <td class="whitespace-nowrap px-4 py-2 font-semibold">
                  {{ \Carbon\Carbon::parse($day['date'])->format('l d') }}
                </td>

                <!-- Scheduled -->
                <td class="px-4 py-2">
                  <div class="flex flex-col gap-1">
                    <!-- Tooltip with schedule slots -->
                    <x-tooltip>
                      <x-slot name="text">
                        <div class="flex flex-col gap-1">
                          @foreach ($day['scheduled']['slots'] as $slot)
                            <span
                              class="text-xs text-gray-600 dark:text-gray-200"
                            >
                              {{ $slot }}
                            </span>
                          @endforeach
                        </div>
                      </x-slot>
                      <span>{{ $day['scheduled']['duration'] }}</span>
                    </x-tooltip>
                  </div>
                </td>

                <!-- Leave -->
                <td class="px-4 py-2">
                  @if ($day['leave'])
                    <div class="flex flex-col gap-1">
                      <span
                        class="w-fit rounded bg-blue-100 px-2 py-1 text-xs text-blue-800"
                      >
                        {{ $day['leave']['leave_type'] }}
                        @if ($day['leave']['context'])
                            ({{ $day['leave']['context'] }})
                        @endif
                      </span>
                      <span class="text-xs text-gray-500">
                        {{ $day['leave']['duration'] }}
                      </span>
                    </div>
                  @endif
                </td>

                <!-- Attendance -->
                <td class="px-4 py-2">
                  <div class="flex flex-row items-center gap-2">
                    <span>{{ $day['attendance']['duration'] }}</span>
                    @if ($day['attendance']['is_remote'])
                      <x-badge variant="info" size="sm">Remote</x-badge>
                    @elseif (! empty($day['attendance']['times']))
                      <span class="text-xs text-gray-500">
                        {{ implode(' -> ', $day['attendance']['times']) }}
                      </span>
                    @endif
                  </div>
                </td>

                <!-- Worked -->
                <td class="px-4 py-2">
                  <div class="flex flex-col gap-1">
                    <span>{{ $day['worked']['duration'] }}</span>
                    @foreach ($day['worked']['projects'] as $project)
                      <div class="text-xs text-gray-500">
                        {{ $project['name'] }}
                        @if (! empty($project['tasks']))
                          <div class="ml-2 text-xs text-gray-400">
                            {{ implode(', ', $project['tasks']) }}
                          </div>
                        @endif
                      </div>
                    @endforeach
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

                {{ $scheduledH }}h {{ $scheduledR }}m
              </td>
              <td class="px-4 py-2"></td>
              <td class="px-4 py-2">
                @php
                  $attendanceMins = $this->getTotals()['attendance'];
                  $attendanceH = intdiv($attendanceMins, 60);
                  $attendanceR = $attendanceMins % 60;
                @endphp

                {{ $attendanceH }}h {{ $attendanceR }}m
              </td>
              <td class="px-4 py-2">
                @php
                  $workedMins = $this->getTotals()['worked'];
                  $workedH = intdiv($workedMins, 60);
                  $workedR = $workedMins % 60;
                @endphp

                {{ $workedH }}h {{ $workedR }}m
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    @endif
  </div>
</div>
