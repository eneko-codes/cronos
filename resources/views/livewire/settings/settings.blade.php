<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
  <!-- Monitoring Section -->
  @if ($telescopeEnabled || $pulseEnabled)
    <section class="relative lg:col-span-1">
      <div
        class="flex h-full flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
      >
        <div class="flex flex-col items-start gap-1 text-lg font-bold">
          <div class="inline-flex flex-row items-center gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="currentColor"
              class="size-5 text-gray-400 dark:text-gray-500"
              viewBox="0 0 16 16"
            >
              <path
                d="M4.5 1A1.5 1.5 0 0 0 3 2.5V3h4v-.5A1.5 1.5 0 0 0 5.5 1zM7 4v1h2V4h4v.882a.5.5 0 0 0 .276.447l.895.447A1.5 1.5 0 0 1 15 7.118V13H9v-1.5a.5.5 0 0 1 .146-.354l.854-.853V9.5a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5v.793l.854.853A.5.5 0 0 1 7 11.5V13H1V7.118a1.5 1.5 0 0 1 .83-1.342l.894-.447A.5.5 0 0 0 3 4.882V4zM1 14v.5A1.5 1.5 0 0 0 2.5 16h3A1.5 1.5 0 0 0 7 14.5V14zm8 0v.5a1.5 1.5 0 0 0 1.5 1.5h3a1.5 1.5 0 0 0 1.5-1.5V14zm4-11H9v-.5A1.5 1.5 0 0 1 10.5 1h1A1.5 1.5 0 0 1 13 2.5z"
              />
            </svg>

            <h2>Monitoring</h2>
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Access dashboards to monitor your application's performance,
            requests, jobs, logs and more.
          </p>
        </div>
        <div class="space-y-2">
          @if ($pulseEnabled)
            <div>
              <a href="/pulse" target="_blank" class="w-full">
                <x-button type="button" size="md" variant="info" class="w-full">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    fill="currentColor"
                    class="bi bi-activity size-4"
                    viewBox="0 0 16 16"
                  >
                    <path
                      fill-rule="evenodd"
                      d="M6 2a.5.5 0 0 1 .47.33L10 12.036l1.53-4.208A.5.5 0 0 1 12 7.5h3.5a.5.5 0 0 1 0 1h-3.15l-1.88 5.17a.5.5 0 0 1-.94 0L6 3.964 4.47 8.171A.5.5 0 0 1 4 8.5H.5a.5.5 0 0 1 0-1h3.15l1.88-5.17A.5.5 0 0 1 6 2"
                    />
                  </svg>
                  <span>Pulse Dashboard</span>
                </x-button>
              </a>
            </div>
          @endif

          @if ($telescopeEnabled)
            <div>
              <a href="/telescope" target="_blank" class="w-full">
                <x-button type="button" size="md" variant="info" class="w-full">
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    fill="currentColor"
                    class="bi bi-stars size-4"
                    viewBox="0 0 16 16"
                  >
                    <path
                      d="M7.657 6.247c.11-.33.576-.33.686 0l.645 1.937a2.89 2.89 0 0 0 1.829 1.828l1.936.645c.33.11.33.576 0 .686l-1.937.645a2.89 2.89 0 0 0-1.828 1.829l-.645 1.936a.361.361 0 0 1-.686 0l-.645-1.937a2.89 2.89 0 0 0-1.828-1.828l-1.937-.645a.361.361 0 0 1 0-.686l1.937-.645a2.89 2.89 0 0 0 1.828-1.828zM3.794 1.148a.217.217 0 0 1 .412 0l.387 1.162c.173.518.579.924 1.097 1.097l1.162.387a.217.217 0 0 1 0 .412l-1.162.387A1.73 1.73 0 0 0 4.593 5.69l-.387 1.162a.217.217 0 0 1-.412 0L3.407 5.69A1.73 1.73 0 0 0 2.31 4.593l-1.162-.387a.217.217 0 0 1 0-.412l1.162-.387A1.73 1.73 0 0 0 3.407 2.31zM10.863.099a.145.145 0 0 1 .274 0l.258.774c.115.346.386.617.732.732l.774.258a.145.145 0 0 1 0 .274l-.774.258a1.16 1.16 0 0 0-.732.732l-.258.774a.145.145 0 0 1-.274 0l-.258-.774a1.16 1.16 0 0 0-.732-.732L9.1 2.137a.145.145 0 0 1 0-.274l.774-.258c.346-.115.617-.386.732-.732z"
                    />
                  </svg>
                  <span>Telescope Dashboard</span>
                </x-button>
              </a>
            </div>
          @endif
        </div>
      </div>
    </section>
  @endif

  <!-- API Health Check -->
  <section class="relative lg:col-span-1">
    <div
      class="flex h-full flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="flex flex-col items-start gap-1 text-lg font-bold">
        <div class="inline-flex flex-row items-center gap-2">
          <!-- SVG Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="currentColor"
            class="size-4 text-gray-400 dark:text-gray-500"
            viewBox="0 0 16 16"
          >
            <path
              d="M14 13.5v-7a.5.5 0 0 0-.5-.5H12V4.5a.5.5 0 0 0-.5-.5h-1v-.5A.5.5 0 0 0 10 3H6a.5.5 0 0 0-.5.5V4h-1a.5.5 0 0 0-.5.5V6H2.5a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5M3.75 11h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25m2 0h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25m1.75.25a.25.25 0 0 1 .25-.25h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25m1.75.25a.25.25 0 0 1 .25-.25h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25z"
            />
            <path
              d="M2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM1 2a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1z"
            />
          </svg>
          <h2>API Health Check</h2>
        </div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Test the connection to the different APIs used by the application.
        </p>
      </div>
      <div class="space-y-2">
        <x-button
          wire:click="pingOdoo"
          wire:target="pingOdoo"
          type="button"
          size="md"
          variant="info"
          class="w-full"
        >
          <span wire:target="pingOdoo">Ping Odoo</span>
          <x-spinner size="4" wire:loading wire:target="pingOdoo" />
        </x-button>

        <x-button
          wire:click="pingDesktime"
          wire:target="pingDesktime"
          type="button"
          size="md"
          variant="info"
          class="w-full"
        >
          <span wire:target="pingDesktime">Ping Desktime</span>
          <x-spinner size="4" wire:loading wire:target="pingDesktime" />
        </x-button>

        <x-button
          wire:click="pingProofhub"
          wire:target="pingProofhub"
          type="button"
          size="md"
          variant="info"
          class="w-full"
        >
          <span wire:target="pingProofhub">Ping ProofHub</span>
          <x-spinner size="4" wire:loading wire:target="pingProofhub" />
        </x-button>
        <x-button
          wire:click="pingSystemPin"
          wire:target="pingSystemPin"
          type="button"
          size="md"
          variant="info"
          class="w-full"
        >
          <span wire:target="pingSystemPin">Ping SystemPin</span>
          <x-spinner size="4" wire:loading wire:target="pingSystemPin" />
        </x-button>
      </div>
    </div>
  </section>

  <!-- Data Synchronization Settings -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="flex h-full flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="flex flex-col items-start gap-1 text-lg font-bold">
        <div class="inline-flex flex-row items-center gap-2">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="currentColor"
            class="size-5 text-gray-400 dark:text-gray-500"
            viewBox="0 0 16 16"
          >
            <path
              d="M12.5 9a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7m.354 5.854 1.5-1.5a.5.5 0 0 0-.708-.708l-.646.647V10.5a.5.5 0 0 0-1 0v2.793l-.646-.647a.5.5 0 0 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0M8 1c-1.573 0-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4s.875 1.755 1.904 2.223C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777C13.125 5.755 14 5.007 14 4s-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1"
            />
            <path
              d="M2 7v-.839c.457.432 1.004.751 1.49.972C4.722 7.693 6.318 8 8 8s3.278-.307 4.51-.867c.486-.22 1.033-.54 1.49-.972V7c0 .424-.155.802-.411 1.133a4.51 4.51 0 0 0-4.815 1.843A12 12 0 0 1 8 10c-1.573 0-3.022-.289-4.096-.777C2.875 8.755 2 8.007 2 7m6.257 3.998L8 11c-1.682 0-3.278-.307-4.51-.867-.486-.22-1.033-.54-1.49-.972V10c0 1.007.875 1.755 1.904 2.223C4.978 12.711 6.427 13 8 13h.027a4.55 4.55 0 0 1 .23-2.002m-.002 3L8 14c-1.682 0-3.278-.307-4.51-.867-.486-.22-1.033-.54-1.49-.972V13c0 1.007.875 1.755 1.904 2.223C4.978 15.711 6.427 16 8 16c.536 0 1.058-.034 1.555-.097a4.5 4.5 0 0 1-1.3-1.905"
            />
          </svg>
          <h2>Data Synchronization Settings</h2>
        </div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Configure how often and how much data is synchronized from all
          connected APIs. These settings help balance data freshness,
          completeness, and storage usage.
        </p>
      </div>
      <div class="space-y-4">
        <div
          class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
        >
          <label
            for="syncFrequencySelect"
            class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Data Synchronization Frequency
            <x-tooltip>
              <x-slot name="text">
                Determines how often the system runs a complete data
                synchronization process from all connected services to update
                local database.
              </x-slot>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-3"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                />
              </svg>
            </x-tooltip>
          </label>
          <select
            id="syncFrequencySelect"
            wire:model.change="syncFrequency"
            class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
          >
            @foreach ($this->getSyncFrequencyOptions() as $value => $label)
              <option
                value="{{ $value }}"
                @selected($syncFrequency === $value)
              >
                {{ $label }}
              </option>
            @endforeach
          </select>
        </div>
        <!-- Sync Window Days Selector -->
        <div
          class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
        >
          <label
            for="syncWindowDaysSelect"
            class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Data Synchronization Window
            <x-tooltip>
              <x-slot name="text">
                Controls how many days of data are fetched in each sync.
                Increasing this window helps fill data gaps if a sync is missed,
                but may increase API usage.
              </x-slot>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-3"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                />
              </svg>
            </x-tooltip>
          </label>
          <select
            id="syncWindowDaysSelect"
            wire:model.change="syncWindowDays"
            class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
          >
            @foreach ($this->getSyncWindowDaysOptions() as $value => $label)
              <option
                value="{{ $value }}"
                @selected($syncWindowDays == $value)
              >
                {{ $label }}
              </option>
            @endforeach
          </select>
        </div>
        <!-- Data Retention Period Selector -->
        <div
          class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
        >
          <label
            for="dataRetentionSelect"
            class="flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Data Retention Period
            <x-tooltip>
              <x-slot name="text">
                Select how long to keep time entries, user attendances,
                schedules, and leaves. Select "Disabled" to disable automatic
                deletion.
              </x-slot>
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-3"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                />
              </svg>
            </x-tooltip>
          </label>
          <select
            id="dataRetentionSelect"
            wire:model.change="dataRetentionGlobalPeriod"
            class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
          >
            @foreach ($dataRetentionOptions as $days => $label)
              <option
                value="{{ $days }}"
                @selected($dataRetentionGlobalPeriod == $days)
              >
                {{ $label }}
              </option>
            @endforeach
          </select>
        </div>
      </div>
    </div>
  </section>

  <!-- Notification Settings Section -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="flex h-full flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="flex flex-col items-start gap-1 text-lg font-bold">
        <div class="inline-flex flex-row items-center gap-2">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="currentColor"
            class="size-5 text-gray-400 dark:text-gray-500"
            viewBox="0 0 16 16"
          >
            <path
              d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"
            />
          </svg>
          <h2>Notification Settings</h2>
        </div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Control system-wide email notifications.
        </p>
      </div>
      <div class="space-y-4">
        {{-- Master Global Toggle --}}
        <div
          class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
        >
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-200"
          >
            Enable All Notifications Globally
            <x-tooltip>
              <x-slot name="text">
                Master switch for all non-login email notifications. Users can
                further customize their preferences in the sidebar.
              </x-slot>
              {{-- Tooltip SVG --}}
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
                stroke-width="1.5"
                stroke="currentColor"
                class="size-3"
              >
                <path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                />
              </svg>
            </x-tooltip>
          </label>
          <x-toggle-switch
            id="globalNotificationsToggle"
            wire:model.change="globalNotificationsEnabled"
            :checked="(bool)$globalNotificationsEnabled"
          />
        </div>

        {{-- Individual Notification Type Toggles --}}
        <div class="space-y-2 rounded-md bg-gray-50 p-3 dark:bg-gray-700">
          @foreach ($notificationTypes as $type)
            <div class="flex items-center justify-between">
              <label
                class="{{ ! $globalNotificationsEnabled ? 'text-gray-400 dark:text-gray-500' : 'text-gray-600 dark:text-gray-300' }} inline-flex items-center gap-1 text-sm font-medium"
              >
                {{ $type->label() }}
                <x-tooltip>
                  <x-slot name="text">
                    Globally enable or disable
                    {{ strtolower($type->label()) }}.
                  </x-slot>
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke-width="1.5"
                    stroke="currentColor"
                    class="size-3"
                  >
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                    />
                  </svg>
                </x-tooltip>
                @if ($type->isAdminOnly())
                  <x-badge variant="primary" size="sm" class="ml-2">
                    Admins
                  </x-badge>
                @endif
              </label>
              <x-toggle-switch
                :id="'toggle-' . $type->value"
                wire:model.change="notificationStates.{{ $type->value }}"
                :checked="(bool)($notificationStates[$type->value] ?? false)"
                :disabled="!$globalNotificationsEnabled"
              />
            </div>
          @endforeach
        </div>
      </div>
    </div>
  </section>
</div>
