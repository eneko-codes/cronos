<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
  <!-- Telescope -->
  <section class="relative lg:col-span-1">
    <div
      class="h-full rounded-xl border border-gray-200 bg-gray-100 p-6 shadow-md dark:border-gray-700 dark:bg-gray-800"
    >
      <div class="flex flex-col items-start gap-1 text-lg font-bold">
        <div class="inline-flex flex-row items-center gap-2">
          <!-- SVG Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="size-5 text-gray-400 dark:text-gray-500"
          >
            <path
              fill-rule="evenodd"
              d="M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5ZM16.5 15a.75.75 0 0 1 .712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 0 1 0 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 0 1-1.422 0l-.395-1.183a1.5 1.5 0 0 0-.948-.948l-1.183-.395a.75.75 0 0 1 0-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0 1 16.5 15Z"
              clip-rule="evenodd"
            />
          </svg>

          <h2>Laravel Telescope</h2>
        </div>
        <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
          Access Laravel Telescope to monitor your application's requests, jobs,
          logs and more.
        </p>
      </div>
      <div class="mt-4">
        <a href="/telescope" target="_blank" class="w-full">
          <x-button type="button" size="md" variant="info" class="w-full">
            <span>Telescope Dashboard</span>
          </x-button>
        </a>
      </div>

      <!-- New Telescope Pruning Settings Form -->
      <form
        wire:submit.prevent="updateTelescopePruneSettings"
        class="mt-6 space-y-4 border-t border-gray-300 pt-4 dark:border-gray-600"
      >
        @csrf
        <div>
          <label
            class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Prune Telescope Data Frequency
            <x-tooltip>
              <x-slot name="text">
                How often should old Telescope entries be automatically deleted?
                Select 'Never' to disable.
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
            wire:model="telescopePruneFrequency"
            class="mt-1 block w-full rounded-md border border-gray-300 bg-gray-200 px-2 text-sm dark:border-gray-700 dark:bg-gray-700"
          >
            @foreach ($telescopeFrequencyOptions as $value => $label)
              <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
          </select>
        </div>

        <div class="flex justify-end">
          <x-button
            wire:target="updateTelescopePruneSettings"
            type="submit"
            size="md"
            variant="success"
          >
            <span wire:target="updateTelescopePruneSettings">
              Save Prune Settings
            </span>
            <x-spinner
              size="4"
              wire:loading.delay
              wire:target="updateTelescopePruneSettings"
            />
          </x-button>
        </div>
      </form>
    </div>
  </section>

  <!-- API Health Check -->
  <section class="relative lg:col-span-1">
    <div
      class="h-full rounded-xl border border-gray-200 bg-gray-100 p-6 shadow-md dark:border-gray-700 dark:bg-gray-800"
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
      <div class="mt-4 flex w-full flex-col gap-2">
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
          wire:click=""
          wire:target=""
          type="button"
          size="md"
          variant="info"
          class="w-full"
        >
          <span wire:target="">Ping SystemPin</span>
          <x-spinner size="4" wire:loading wire:target="" />
        </x-button>
      </div>
    </div>
  </section>

  <!-- Scheduled Job Frequencies Form -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="h-full rounded-xl border border-gray-200 bg-gray-100 p-6 shadow-md dark:border-gray-700 dark:bg-gray-800"
    >
      <form
        wire:submit.prevent="updateFrequencies"
        class="flex h-full flex-col justify-between"
      >
        @csrf
        <div class="space-y-4">
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
              Configure how often the system synchronizes all data from
              connected APIs.
            </p>
          </div>
          <div class="flex items-center justify-between">
            <label
              class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
            >
              Data Synchronization Frequency
              <x-tooltip>
                <x-slot name="text">
                  Determines how often the system runs a complete data
                  synchronization process from all connected services to update
                  local database
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
              wire:model="frequency"
              class="block w-40 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm dark:border-gray-700 dark:bg-gray-700"
            >
              @foreach ($options as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <x-button
            wire:target="updateFrequencies"
            type="submit"
            size="md"
            variant="success"
          >
            <span wire:target="updateFrequencies">Save Changes</span>
            <x-spinner
              size="4"
              wire:loading.delay
              wire:target="updateFrequencies"
            />
          </x-button>
        </div>
      </form>
    </div>
  </section>

  <!-- Notification Settings Section -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="h-full rounded-xl border border-gray-200 bg-gray-100 p-6 shadow-md dark:border-gray-700 dark:bg-gray-800"
    >
      <form wire:submit.prevent="updateNotificationToggles" class="space-y-4">
        <div class="flex flex-col items-start gap-1 text-lg font-bold">
          <div class="inline-flex flex-row items-center gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
              class="size-5 text-gray-400 dark:text-gray-500"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5"
              />
            </svg>

            <h2>Notification Settings</h2>
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Control which email notifications are sent by the application.
            <span class="mt-2 block space-y-1">
              <span
                class="block font-medium text-green-600 dark:text-green-400"
              >
                {{ $activeUsers }} users with notifications enabled out of
                {{ $totalUsers }}.
              </span>
              <span
                class="block font-medium text-green-600 dark:text-green-400"
              >
                {{ $activeAdmins }} admins will receive system alerts.
              </span>
            </span>
          </p>
        </div>

        <!-- Toggle notification 'Welcome Email' -->
        <div class="flex items-center justify-between">
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Welcome Email
            <x-tooltip>
              <x-slot name="text">
                Email sent to new users when their account is created
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
          <label class="relative inline-flex cursor-pointer items-center">
            <input
              type="checkbox"
              class="peer sr-only"
              wire:model="welcomeEmail"
            />
            <div
              class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
            ></div>
          </label>
        </div>

        <!-- Toggle notification 'API Down Warning' -->
        <div class="flex items-center justify-between">
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            API Down Email
            <x-tooltip>
              <x-slot name="text">
                Notification sent to administrators when an API connection fails
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
            <x-badge variant="primary" size="sm" class="ml-2">Admins</x-badge>
          </label>
          <label class="relative inline-flex cursor-pointer items-center">
            <input
              type="checkbox"
              class="peer sr-only"
              wire:model="apiDownWarning"
            />
            <div
              class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
            ></div>
          </label>
        </div>

        <!-- Toggle notification 'User Activity Alerts' -->
        <div class="flex items-center justify-between">
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Activity Alerts
            <x-tooltip>
              <x-slot name="text">
                Send alerts about missing time entries or unusual attendance
                patterns
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
            <x-badge variant="primary" size="sm" class="ml-2">Managers</x-badge>
          </label>
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only" disabled />
            <div
              class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
            ></div>
          </label>
        </div>

        <!-- Toggle notification 'Schedule Changes' -->
        <div class="flex items-center justify-between">
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Schedule Notifications
            <x-tooltip>
              <x-slot name="text">
                Notify users about schedule changes and schedule conflicts
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
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only" disabled />
            <div
              class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
            ></div>
          </label>
        </div>

        <!-- Toggle notification 'Time Entry Approvals' -->
        <div class="flex items-center justify-between">
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Time Entry Notifications
            <x-tooltip>
              <x-slot name="text">
                Notify about time entry approvals, rejections, and entries
                requiring review
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
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only" disabled />
            <div
              class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
            ></div>
          </label>
        </div>

        <!-- Toggle notification 'System Maintenance' -->
        <div class="flex items-center justify-between">
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Maintenance Alerts
            <x-tooltip>
              <x-slot name="text">
                Notify users about scheduled maintenance and system updates
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
            <x-badge variant="primary" size="sm" class="ml-2">Admins</x-badge>
          </label>
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only" disabled />
            <div
              class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
            ></div>
          </label>
        </div>

        <!-- Toggle notification 'Report Generation' -->
        <div class="flex items-center justify-between">
          <label
            class="inline-flex items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
          >
            Report Notifications
            <x-tooltip>
              <x-slot name="text">
                Notify when scheduled reports are generated and ready for
                viewing
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
          <label class="relative inline-flex cursor-pointer items-center">
            <input type="checkbox" class="peer sr-only" disabled />
            <div
              class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
            ></div>
          </label>
        </div>

        <div class="mt-4 flex justify-end">
          <x-button
            wire:target="updateNotificationToggles"
            type="submit"
            size="md"
            variant="success"
          >
            <span>Save Changes</span>
            <x-spinner
              size="4"
              wire:loading.delay
              wire:target="updateNotificationToggles"
            />
          </x-button>
        </div>
      </form>
    </div>
  </section>

  <!-- Data Retention Settings Section -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="h-full rounded-xl border border-gray-200 bg-gray-100 p-6 shadow-md dark:border-gray-700 dark:bg-gray-800"
    >
      <form wire:submit.prevent="updateDataRetentionSettings" class="space-y-4">
        <div class="flex flex-col items-start gap-1 text-lg font-bold">
          <div class="inline-flex flex-row items-center gap-2">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              fill="currentColor"
              class="size-5 text-gray-400 dark:text-gray-500"
              viewBox="0 0 16 16"
            >
              <path
                d="M4 .5a.5.5 0 0 0-1 0V1H2a2 2 0 0 0-2 2v1h16V3a2 2 0 0 0-2-2h-1V.5a.5.5 0 0 0-1 0V1H4zM16 14V5H0v9a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2M6.854 8.146 8 9.293l1.146-1.147a.5.5 0 1 1 .708.708L8.707 10l1.147 1.146a.5.5 0 0 1-.708.708L8 10.707l-1.146 1.147a.5.5 0 0 1-.708-.708L7.293 10 6.146 8.854a.5.5 0 1 1 .708-.708"
              />
            </svg>

            <h2>Data Retention Settings</h2>
          </div>
          <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
            Configure automatic deletion of old time-related user data.
          </p>
        </div>

        <!-- Single data retention period selector -->
        <div class="mt-4">
          <div class="flex items-center justify-between">
            <label
              class="flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
            >
              Retain time-related data for
              <x-tooltip>
                <x-slot name="text">
                  Select how long to keep time entries, user attendances,
                  schedules, and leaves. Select "NO" to disable automatic
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
              wire:model="globalRetentionPeriod"
              class="block w-40 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm dark:border-gray-700 dark:bg-gray-700"
            >
              <option value="0">NO - Keep all data</option>
              @foreach ($retentionOptions as $days => $label)
                <option value="{{ $days }}">{{ $label }}</option>
              @endforeach
            </select>
          </div>

          <!-- Show affected data types -->
          <div class="mt-4 rounded-md bg-gray-50 p-3 dark:bg-gray-700">
            <p class="text-xs font-medium text-gray-600 dark:text-gray-300">
              This setting will affect all the following data types:
            </p>
            <ul
              class="mt-2 list-inside list-disc space-y-1 text-xs text-gray-500 dark:text-gray-400"
            >
              @foreach ($dataTypes as $dataType => $label)
                <li>{{ $label }}</li>
              @endforeach
            </ul>
          </div>
        </div>
        <div class="mt-4 flex justify-end">
          <x-button
            wire:target="updateDataRetentionSettings"
            type="submit"
            size="md"
            variant="success"
          >
            <span>Save Changes</span>
            <x-spinner
              size="4"
              wire:loading.delay
              wire:target="updateDataRetentionSettings"
            />
          </x-button>
        </div>
      </form>
    </div>
  </section>
</div>
