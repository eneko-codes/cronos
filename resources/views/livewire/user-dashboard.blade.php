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

    {{-- Missing Account Notification --}}
    @if (! $user->do_not_track && (! $user->proofhub_id || ! $user->desktime_id || ! $user->systempin_id))
        <div
            class="rounded-md border border-red-300 bg-red-50 p-2 text-sm text-red-800 dark:border-red-600/60 dark:bg-red-900/20 dark:text-red-200"
        >
            <div class="flex flex-col gap-1">
                <div class="flex flex-row items-center gap-1">
                    <svg
                        class="h-5 w-5 flex-shrink-0 text-red-500 dark:text-red-600"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke-width="1.5"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
                        />
                    </svg>
                    <h3 class="text-md font-semibold">Missing Account Links</h3>
                </div>

                <p class="text-xs">
                    User IDs for external platforms are automatically added via
                    sync, using the email set on the Odoo user profile as the
                    primary key.
                </p>
                <p class="text-xs">
                    To be able to pull the missing data make sure to use your
                    company email in the missing accounts:
                    <span class="font-medium">{{ $user->email }}</span>
                </p>

                <div class="flex flex-wrap gap-1">
                    @if (! $user->proofhub_id)
                        <x-tooltip text="ProofHub account ID is missing">
                            <x-badge variant="alert" size="sm">
                                ProofHub
                            </x-badge>
                        </x-tooltip>
                    @endif

                    @if (! $user->desktime_id)
                        <x-tooltip text="DeskTime account ID is missing">
                            <x-badge variant="alert" size="sm">
                                DeskTime
                            </x-badge>
                        </x-tooltip>
                    @endif

                    @if (! $user->systempin_id)
                        <x-tooltip text="SystemPin account ID is missing">
                            <x-badge variant="alert" size="sm">
                                SystemPin
                            </x-badge>
                        </x-tooltip>
                    @endif
                </div>
            </div>
        </div>
    @endif

    @if ($user->do_not_track)
        <div class="rounded-lg bg-gray-50 p-8 text-center dark:bg-gray-800">
            <p class="text-md text-gray-600 dark:text-gray-300">
                {{ $user->name }} is currently set to do not track. Data is not
                stored for this user.
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
                        {{ $viewMode === "weekly" ? "Week" : "Month" }} of
                        {{ now()->parse($currentDate)->translatedFormat("F d, Y") }}
                    </h2>

                    <button
                        class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-gray-200/75 px-1.5 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-gray-200 disabled:opacity-50 dark:bg-gray-200 dark:hover:bg-gray-100"
                        wire:click="nextPeriod"
                        @disabled($this->isNextPeriodDisabled)
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
                    :filters="collect([
                        'weekly' => 'Weekly',
                        'monthly' => 'Monthly'
                    ])"
                    onFilterChange="changeViewMode"
                    :showCounts="false"
                />
            </div>
        </div>

        <!-- Data Table -->
        <div class="scrollbar-thin overflow-x-auto shadow-xl">
            <table class="w-full table-auto border-collapse text-sm">
                <thead
                    class="bg-gray-200 text-left font-medium text-gray-800 dark:bg-gray-700 dark:text-gray-100"
                >
                    <tr>
                        <th
                            class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
                        >
                            Day
                        </th>
                        @foreach (collect([
                                "Scheduled" => "Hours from Odoo calendar",
                                "Leave" => "Time off from Odoo",
                                "Attendance" => "Hours from Desktime/SystemPin",
                                "Worked" => "Hours from Proofhub"
                            ])
                            as $name => $tooltip)
                            <th
                                class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
                            >
                                <div
                                    class="inline-flex flex-row items-center gap-1"
                                >
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
                                    "Attendance vs Scheduled" =>
                                        "Percentage deviation between attendance and scheduled hours",
                                    "Worked vs Scheduled" =>
                                        "Percentage deviation between worked and scheduled hours",
                                    "Worked vs Attendance" =>
                                        "Percentage deviation between worked and attendance hours"
                                ])
                                as $name => $tooltip)
                                <th
                                    class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
                                >
                                    <div
                                        class="inline-flex flex-row items-center gap-1"
                                    >
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
                    @foreach ($this->periodData as $day)
                        @php
                            $dayDate = now()->parse($day["date"]);
                            $isFutureDate = $dayDate->isFuture();
                        @endphp

                        <tr
                            class="{{ $isFutureDate ? "text-gray-500 dark:text-gray-500" : "" }} border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
                        >
                            <!-- Date Column -->
                            <td
                                class="whitespace-nowrap border p-2 font-semibold dark:border-gray-700"
                            >
                                <div class="flex items-center gap-2">
                                    {{ $dayDate->translatedFormat("l d") }}

                                    @if ($dayDate->isToday())
                                        <x-badge size="sm" variant="primary">
                                            Today
                                        </x-badge>
                                    @endif
                                </div>
                            </td>

                            <!-- Scheduled -->
                            <td
                                class="whitespace-nowrap border p-2 dark:border-gray-700"
                            >
                                <div class="flex flex-col gap-1">
                                    <x-tooltip>
                                        <x-slot name="text">
                                            <div class="flex flex-col gap-1">
                                                @if (collect($day["scheduled"]["slots"])->isNotEmpty())
                                                    @if (isset($day["scheduled"]["schedule_name"]))
                                                        <span
                                                            class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-100"
                                                        >
                                                            {{ $day["scheduled"]["schedule_name"] }}
                                                        </span>
                                                    @endif

                                                    @foreach (collect($day["scheduled"]["slots"]) as $slot)
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
                                        <span
                                            class="text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $day["scheduled"]["duration"] !== "0h 0m" ? $day["scheduled"]["duration"] : "" }}
                                        </span>
                                    </x-tooltip>
                                </div>
                            </td>

                            <!-- Leave -->
                            <td
                                class="whitespace-nowrap border p-2 dark:border-gray-700"
                            >
                                @if ($day["leave"])
                                    <div
                                        class="{{ $day["leave"]["status"] !== "validate" ? "opacity-60" : "" }} flex items-center gap-2"
                                    >
                                        <div>
                                            <x-tooltip>
                                                <x-slot name="text">
                                                    <div
                                                        class="flex max-w-xs flex-col gap-2"
                                                    >
                                                        <div
                                                            class="mb-1 flex flex-row items-center gap-1"
                                                        >
                                                            <span
                                                                class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                                            >
                                                                {{ $day["leave"]["duration"] }}
                                                            </span>
                                                        </div>

                                                        @if ($day["leave"]["is_half_day"])
                                                            <div>
                                                                <span
                                                                    class="text-xs text-gray-600 dark:text-gray-300"
                                                                >
                                                                    {{ Str::ucfirst($day["leave"]["time_period"]) }}
                                                                    ({{ $day["leave"]["half_day_time"] ?? "—" }})
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

                                                        <div>
                                                            <span
                                                                class="text-xs font-medium text-gray-600 dark:text-gray-300"
                                                            >
                                                                {{ $day["leave"]["type"] ?? "Leave" }}
                                                            </span>
                                                        </div>

                                                        @if ($day["leave"]["status"] !== "validate")
                                                            <div
                                                                class="mt-1 border border-dashed border-gray-200 pt-1 dark:border-gray-700"
                                                            >
                                                                <span
                                                                    class="text-xs italic text-gray-500 dark:text-gray-400"
                                                                >
                                                                    {{ $day["leave"]["status"] === "confirm" ? "Waiting approval" : "Cancelled" }}
                                                                </span>
                                                            </div>
                                                        @endif
                                                    </div>
                                                </x-slot>
                                                <span
                                                    class="text-gray-700 dark:text-gray-300"
                                                >
                                                    {{ $day["leave"]["duration_hours"] }}

                                                    @if ($day["leave"]["status"] === "validate")
                                                        <x-badge
                                                            variant="success"
                                                            size="sm"
                                                        >
                                                            {{ $day["leave"]["is_half_day"] ? "Half" : "Full" }}
                                                        </x-badge>
                                                    @elseif ($day["leave"]["status"] === "confirm")
                                                        <x-tooltip
                                                            text="Leave request is pending approval"
                                                        >
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
                                                                    d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"
                                                                />
                                                            </svg>
                                                        </x-tooltip>
                                                    @elseif ($day["leave"]["status"] === "cancel")
                                                        <x-tooltip
                                                            text="Leave request was cancelled"
                                                        >
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
                                                                    d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"
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
                            <td
                                class="whitespace-nowrap border p-2 dark:border-gray-700"
                            >
                                <x-tooltip>
                                    <x-slot name="text">
                                        <div class="flex flex-col gap-1">
                                            @if ($day["attendance"]["is_remote"])
                                                <span
                                                    class="text-xs text-gray-600 dark:text-gray-200"
                                                >
                                                    Remote work
                                                </span>
                                            @elseif (collect($day["attendance"]["times"])->isNotEmpty())
                                                <span
                                                    class="text-xs text-gray-600 dark:text-gray-200"
                                                >
                                                    {{ collect($day["attendance"]["times"])->join(" → ") }}
                                                </span>
                                            @else
                                                <span
                                                    class="text-xs text-gray-500 dark:text-gray-400"
                                                >
                                                    No data
                                                </span>
                                            @endif
                                        </div>
                                    </x-slot>
                                    <div
                                        class="flex flex-row items-center gap-2"
                                    >
                                        <span
                                            class="text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $day["attendance"]["duration"] !== "0h 0m" ? $day["attendance"]["duration"] : "" }}
                                        </span>
                                        @if ($day["attendance"]["is_remote"])
                                            <x-badge variant="info" size="sm">
                                                Remote
                                            </x-badge>
                                        @elseif (collect($day["attendance"]["times"])->isNotEmpty())
                                            <x-badge
                                                variant="success"
                                                size="sm"
                                            >
                                                In Office
                                            </x-badge>
                                        @endif
                                    </div>
                                </x-tooltip>
                            </td>

                            <!-- Worked -->
                            <td
                                class="whitespace-nowrap border p-2 dark:border-gray-700"
                            >
                                <div class="flex flex-col gap-1">
                                    <x-tooltip>
                                        <x-slot name="text">
                                            <div
                                                class="flex max-w-xs flex-col gap-2"
                                            >
                                                @if (collect($day["worked"]["detailed_entries"])->isNotEmpty())
                                                    @foreach (collect($day["worked"]["detailed_entries"]) as $entry)
                                                        <div
                                                            class="{{ ! $loop->last ? " dark:border-gray-700" : "" }} flex flex-col"
                                                        >
                                                            <div
                                                                class="mb-1 flex items-center justify-between"
                                                            >
                                                                <span
                                                                    class="text-xs font-medium text-gray-800 dark:text-gray-100"
                                                                >
                                                                    {{ $entry["project"] }}
                                                                </span>
                                                                <span
                                                                    class="ml-2 whitespace-nowrap rounded bg-gray-100 px-1.5 py-0.5 text-xs text-gray-700 dark:bg-gray-700 dark:text-gray-300"
                                                                >
                                                                    {{ $entry["duration"] }}
                                                                </span>
                                                            </div>
                                                            @if (isset($entry["task"]) && $entry["task"])
                                                                <span
                                                                    class="mb-0.5 text-xs text-gray-600 dark:text-gray-300"
                                                                >
                                                                    {{ $entry["task"] }}
                                                                </span>
                                                            @endif

                                                            @if (isset($entry["description"]) && $entry["description"])
                                                                <span
                                                                    class="text-xs italic text-gray-500 dark:text-gray-400"
                                                                >
                                                                    {{ Str::limit($entry["description"], 80) }}
                                                                </span>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @elseif (collect($day["worked"]["projects"])->isNotEmpty())
                                                    <div class="flex flex-col">
                                                        @foreach (collect($day["worked"]["projects"]) as $project)
                                                            <div
                                                                class="{{ ! $loop->last ? "mb-3" : "" }}"
                                                            >
                                                                <span
                                                                    class="text-xs font-medium text-gray-800 dark:text-gray-100"
                                                                >
                                                                    {{ $project["name"] }}
                                                                </span>
                                                                @if (collect($project["tasks"])->isNotEmpty())
                                                                    <div
                                                                        class="mt-1 text-xs text-gray-600 dark:text-gray-300"
                                                                    >
                                                                        {{ collect($project["tasks"])->join(", ") }}
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
                                        <span
                                            class="text-gray-700 dark:text-gray-300"
                                        >
                                            {{ $day["worked"]["duration"] !== "0h 0m" ? $day["worked"]["duration"] : "" }}
                                        </span>
                                    </x-tooltip>
                                </div>
                            </td>

                            <!-- Deviation Columns -->
                            @if ($showDeviations)
                                <!-- Attendance vs Scheduled -->
                                <td
                                    class="whitespace-nowrap border p-2 dark:border-gray-700"
                                >
                                    @if (! $isFutureDate && $showDeviations && isset($day["deviation_details"]) && $day["deviation_details"]["attendance_vs_scheduled"]["percentage"] !== 0)
                                        @php
                                            $detail = $day["deviation_details"]["attendance_vs_scheduled"];
                                            $percentage = $detail["percentage"];
                                        @endphp

                                        <x-tooltip :text="$detail['tooltip']">
                                            <span
                                                class="@if ($percentage > 0)
                                                    text-green-600
                                                    dark:text-green-600
                                                @elseif ($percentage <= -50)
                                                    text-red-600
                                                    dark:text-red-600
                                                @elseif ($percentage < 0 && $percentage > -50)
                                                    text-yellow-500
                                                    dark:text-yellow-500
                                                @else
                                                    text-gray-500
                                                    dark:text-gray-400
                                                @endif"
                                            >
                                                {{ $percentage > 0 ? "+" : "" }}{{ $percentage }}%
                                            </span>
                                        </x-tooltip>
                                    @endif
                                </td>

                                <!-- Worked vs Scheduled -->
                                <td
                                    class="whitespace-nowrap border p-2 dark:border-gray-700"
                                >
                                    @if (! $isFutureDate && $showDeviations && isset($day["deviation_details"]) && $day["deviation_details"]["worked_vs_scheduled"]["percentage"] !== 0)
                                        @php
                                            $detail = $day["deviation_details"]["worked_vs_scheduled"];
                                            $percentage = $detail["percentage"];
                                        @endphp

                                        <x-tooltip :text="$detail['tooltip']">
                                            <span
                                                class="@if ($percentage > 0)
                                                    text-green-600
                                                    dark:text-green-600
                                                @elseif ($percentage <= -50)
                                                    text-red-600
                                                    dark:text-red-600
                                                @elseif ($percentage < 0 && $percentage > -50)
                                                    text-yellow-500
                                                    dark:text-yellow-500
                                                @else
                                                    text-gray-500
                                                    dark:text-gray-400
                                                @endif"
                                            >
                                                {{ $percentage > 0 ? "+" : "" }}{{ $percentage }}%
                                            </span>
                                        </x-tooltip>
                                    @endif
                                </td>

                                <!-- Worked vs Attendance -->
                                <td
                                    class="whitespace-nowrap border p-2 dark:border-gray-700"
                                >
                                    @if (! $isFutureDate && $showDeviations && isset($day["deviation_details"]) && $day["deviation_details"]["worked_vs_attendance"]["percentage"] !== 0)
                                        @php
                                            $detail = $day["deviation_details"]["worked_vs_attendance"];
                                            $percentage = $detail["percentage"];
                                        @endphp

                                        <x-tooltip :text="$detail['tooltip']">
                                            <span
                                                class="@if ($percentage > 0)
                                                    text-green-600
                                                    dark:text-green-600
                                                @elseif ($percentage <= -50)
                                                    text-red-600
                                                    dark:text-red-600
                                                @elseif ($percentage < 0 && $percentage > -50)
                                                    text-yellow-500
                                                    dark:text-yellow-500
                                                @else
                                                    text-gray-500
                                                    dark:text-gray-400
                                                @endif"
                                            >
                                                {{ $percentage > 0 ? "+" : "" }}{{ $percentage }}%
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

                        @php
                            $totals = $this->totals;
                            $totalDeviationsDetails = $showDeviations ? $this->totalDeviations : null;
                        @endphp

                        @foreach (collect(["scheduled", "leave", "attendance", "worked"]) as $type)
                            <td
                                class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
                            >
                                {{ $totals[$type] > 0 ? $this->formatMinutesToHoursMinutes($totals[$type]) : "" }}
                            </td>
                        @endforeach

                        @if ($showDeviations)
                            @foreach ($totalDeviationsDetails as $deviationType => $details)
                                @php
                                    $percentage = $details["percentage"];
                                @endphp

                                <td
                                    class="whitespace-nowrap border border-gray-300 p-2 dark:border-gray-800"
                                >
                                    @if ($percentage !== 0)
                                        <x-tooltip :text="$details['tooltip']">
                                            <span
                                                class="@if ($percentage > 0)
                                                    text-green-600
                                                    dark:text-green-600
                                                @elseif ($percentage <= -50)
                                                    text-red-600
                                                    dark:text-red-600
                                                @elseif ($percentage < 0 && $percentage > -50)
                                                    text-yellow-500
                                                    dark:text-yellow-500
                                                @else
                                                    text-gray-500
                                                    dark:text-gray-400
                                                @endif"
                                            >
                                                {{ $percentage > 0 ? "+" : "" }}{{ $percentage }}%
                                            </span>
                                        </x-tooltip>
                                    @endif
                                </td>
                            @endforeach
                        @endif
                    </tr>
                </tbody>
            </table>
        </div>
    @endif
</div>
