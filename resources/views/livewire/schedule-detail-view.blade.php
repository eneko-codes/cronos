<div class="flex flex-col gap-6">
  <!-- Back button -->
  <a
    href="{{ route("schedules.list") }}"
    wire:navigate
    class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-gray-200/75 px-2 py-1 text-xs font-semibold whitespace-nowrap text-gray-800 shadow-sm hover:bg-gray-200 dark:bg-gray-200 dark:text-gray-800 dark:hover:bg-gray-100"
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
      {{ $schedule->description ?? "Schedule ID: " . $schedule->odoo_schedule_id }}
    </h1>
    <p class="text-xs text-gray-600 dark:text-gray-400">
      Average hours per day: {{ $schedule->average_hours_day ?? "N/A" }}
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

  <!-- Sections Container: Details and Users -->
  <div class="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
    {{-- Schedule Details Section (Column 1) --}}
    <div
      class="flex flex-col gap-3 rounded-md bg-white p-4 md:col-span-1 dark:bg-gray-800"
    >
      {{-- Header with Toggle --}}
      <div
        wire:click="toggleScheduleDetails"
        class="flex cursor-pointer items-center justify-between"
      >
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
          Weekly Time Slots
        </h2>
        <svg
          class="{{ $showScheduleDetails ? "rotate-180" : "" }} size-5 transform text-gray-500 transition-transform duration-200 dark:text-gray-400"
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
        >
          <path
            fill-rule="evenodd"
            d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.06z"
            clip-rule="evenodd"
          />
        </svg>
      </div>

      {{-- Content (Conditionally Rendered) --}}
      @if ($showScheduleDetails)
        {{-- Access computed property like a public property --}}
        @if ($this->groupedScheduleDetails->isNotEmpty())
          <div class="space-y-4">
            {{-- Loop through grouped details (sorted Mon-Sun in component) --}}
            @foreach ($this->groupedScheduleDetails as $weekday => $details)
              <div class="flex flex-col gap-1">
                {{-- Day Header --}}
                <h4 class="font-semibold text-gray-700 dark:text-gray-300">
                  {{ Carbon\Carbon::now()->startOfWeek(Carbon\Carbon::SUNDAY)->addDays($weekday)->format("l") }}
                </h4>
                {{-- List of details for this day --}}
                <ul class="list-none space-y-2 pl-0 text-sm">
                  @foreach ($details as $detail)
                    {{-- $details are already sorted by start time --}}
                    <div
                      class="{{ $detail->status_classes }} rounded-md border p-3"
                    >
                      <div class="flex flex-row items-center justify-between">
                        <span
                          class="flex flex-row items-center gap-2 font-semibold"
                        >
                          {{-- Display period --}}
                          {{ ucfirst($detail->day_period) }}

                          {{-- Status indicator badge --}}
                          <span
                            class="@if ($detail->status === "active")
                                bg-green-100
                                text-green-800
                                dark:bg-green-900/40
                                dark:text-green-200
                            @elseif ($detail->status === "future")
                                bg-yellow-100
                                text-yellow-800
                                dark:bg-yellow-900/40
                                dark:text-yellow-200
                            @elseif ($detail->status === "historical")
                                bg-orange-100
                                text-orange-800
                                dark:bg-orange-900/40
                                dark:text-orange-200
                            @else
                                bg-gray-100
                                text-gray-800
                                dark:bg-gray-700
                                dark:text-gray-200
                            @endif inline-flex items-center rounded-full px-2 py-1 text-xs font-medium"
                          >
                            {{ $detail->status_label }}
                          </span>
                        </span>
                      </div>

                      <div class="mt-2">
                        {{-- Time and duration --}}
                        <div class="text-sm">
                          {{ \Carbon\Carbon::parse($detail->start)->format("H:i") }}
                          →
                          {{ \Carbon\Carbon::parse($detail->end)->format("H:i") }}
                          <span class="text-gray-500">
                            ({{ $detail->duration_string ?? "N/A" }})
                          </span>
                        </div>

                        {{-- Date range information if present --}}
                        @if ($detail->date_from || $detail->date_to)
                          <div
                            class="mt-1 text-xs text-gray-600 dark:text-gray-400"
                          >
                            Valid:
                            @if ($detail->date_from && $detail->date_to)
                              {{ \Carbon\Carbon::parse($detail->date_from)->format("M j, Y") }}
                              -
                              {{ \Carbon\Carbon::parse($detail->date_to)->format("M j, Y") }}
                            @elseif ($detail->date_from)
                              From
                              {{ \Carbon\Carbon::parse($detail->date_from)->format("M j, Y") }}
                            @elseif ($detail->date_to)
                              Until
                              {{ \Carbon\Carbon::parse($detail->date_to)->format("M j, Y") }}
                            @endif
                          </div>
                        @endif
                      </div>
                    </div>
                  @endforeach
                </ul>
              </div>
            @endforeach
          </div>
        @else
          <div
            class="rounded-md border border-dashed border-gray-300 bg-white p-4 text-center dark:border-gray-500 dark:bg-gray-700"
          >
            <p class="text-sm text-gray-500 dark:text-gray-400">
              No details defined for this schedule.
            </p>
          </div>
        @endif
      @endif
    </div>

    {{-- User Assignments Column (Column 2) --}}
    <div class="flex flex-col gap-6 md:col-span-1">
      {{-- Content Area (Conditionally Rendered based on toggle) --}}
      {{-- @if ($showUserAssignments) --}}
      {{-- Currently Assigned Users Section --}}
      <div class="flex flex-col gap-3 rounded-md bg-white p-4 dark:bg-gray-800">
        {{-- Header with Toggle --}}
        <div
          wire:click="toggleCurrentlyAssigned"
          class="flex cursor-pointer items-center justify-between"
        >
          <h3 class="font-semibold text-gray-800 dark:text-gray-200">
            Currently Assigned
          </h3>
          <svg
            class="{{ $showCurrentlyAssigned ? "rotate-180" : "" }} size-5 transform text-gray-500 transition-transform duration-200 dark:text-gray-400"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.06z"
              clip-rule="evenodd"
            />
          </svg>
        </div>

        {{-- Content (Conditionally Rendered) --}}
        @if ($showCurrentlyAssigned)
          {{-- Access computed property via method call --}}
          @if ($this->currentUserSchedules()->isNotEmpty())
            <div class="flex flex-wrap gap-2">
              {{-- Loop through computed property result --}}
              @foreach ($this->currentUserSchedules() as $userSchedule)
                @if ($userSchedule->user)
                  <x-tooltip
                    text="Assigned from {{ $userSchedule->effective_from->format('Y-m-d') }} {{ $userSchedule->effective_until ? 'to ' . $userSchedule->effective_until->format('Y-m-d') : 'indefinitely' }}"
                  >
                    <a
                      href="{{ route("user.dashboard", ["user" => $userSchedule->user->id]) }}"
                      wire:navigate
                      class="inline-block"
                    >
                      <x-badge size="sm" variant="info">
                        {{ $userSchedule->user->name }}
                      </x-badge>
                    </a>
                  </x-tooltip>
                @endif
              @endforeach
            </div>
          @else
            <div
              class="rounded-md border border-dashed border-gray-300 bg-white p-4 text-center dark:border-gray-500 dark:bg-gray-700"
            >
              <p class="text-sm text-gray-500 dark:text-gray-400">
                No users currently assigned to this schedule.
              </p>
            </div>
          @endif
        @endif
      </div>

      {{-- Previously Assigned Users Section --}}
      <div class="flex flex-col gap-3 rounded-md bg-white p-4 dark:bg-gray-800">
        {{-- Header with Toggle --}}
        <div
          wire:click="togglePreviouslyAssigned"
          class="flex cursor-pointer items-center justify-between"
        >
          <h3 class="font-semibold text-gray-800 dark:text-gray-200">
            Previously Assigned
          </h3>
          <svg
            class="{{ $showPreviouslyAssigned ? "rotate-180" : "" }} size-5 transform text-gray-500 transition-transform duration-200 dark:text-gray-400"
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path
              fill-rule="evenodd"
              d="M5.23 7.21a.75.75 0 011.06.02L10 10.94l3.71-3.71a.75.75 0 111.06 1.06l-4.25 4.25a.75.75 0 01-1.06 0L5.21 8.29a.75.75 0 01.02-1.06z"
              clip-rule="evenodd"
            />
          </svg>
        </div>

        {{-- Content (Conditionally Rendered) --}}
        @if ($showPreviouslyAssigned)
          {{-- Access computed property via method call --}}
          @if ($this->pastUserSchedules()->isNotEmpty())
            <div class="flex flex-wrap gap-2">
              {{-- Loop through computed property result --}}
              @foreach ($this->pastUserSchedules() as $userSchedule)
                @if ($userSchedule->user)
                  <x-tooltip
                    text="Assigned from {{ $userSchedule->effective_from->format('Y-m-d') }} to {{ $userSchedule->effective_until ? $userSchedule->effective_until->format('Y-m-d') : 'Error: No end date?' }}"
                  >
                    <a
                      href="{{ route("user.dashboard", ["user" => $userSchedule->user->id]) }}"
                      wire:navigate
                      class="inline-block"
                    >
                      <x-badge size="sm" variant="alert">
                        {{ $userSchedule->user->name }}
                      </x-badge>
                    </a>
                  </x-tooltip>
                @endif
              @endforeach
            </div>
          @else
            <div
              class="rounded-md border border-dashed border-gray-300 bg-white p-4 text-center dark:border-gray-500 dark:bg-gray-700"
            >
              <p class="text-sm text-gray-500 dark:text-gray-400">
                No users previously assigned (who aren't currently assigned).
              </p>
            </div>
          @endif
        @endif
      </div>
      {{-- @endif --}}
    </div>
  </div>
  <!-- End Sections Container -->
</div>
