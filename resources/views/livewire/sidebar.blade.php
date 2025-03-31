<!-- Settings sidebar -->
<div>
  @if ($isOpen)
    <!-- Backdrop -->
    <div
      wire:key="sidebar-backdrop"
      wire:click="$set('isOpen', false)"
      @keydown.escape.window="$wire.set('isOpen', false)"
      class="fixed inset-0 z-40 bg-gray-500/75 backdrop-blur-sm transition-opacity duration-300 dark:bg-gray-900/80"
    ></div>

    <!-- Sidebar -->
    <div
      wire:key="sidebar-content"
      class="fixed right-0 top-0 z-50 flex h-full w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-xl transition-transform dark:border-gray-800 dark:bg-gray-900"
    >
      <!-- Header -->
      <div
        class="flex h-12 items-center justify-between border-b border-gray-100 px-4 dark:border-gray-800"
      >
        <!-- Title -->
        <div class="flex items-center gap-2">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-6 rounded-full bg-gray-100 p-1 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
            />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
            />
          </svg>
          <h2 class="text-md font-semibold text-gray-900 dark:text-white">
            Settings
          </h2>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <button
            wire:click="$set('isOpen', false)"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
          >
            <svg
              class="h-5 w-5"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
              />
            </svg>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="flex-1 overflow-y-auto p-4">
        <!-- Settings Content -->
        <div class="space-y-6">
          <div
            class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-800"
          >
            <h3
              class="mb-4 text-sm font-semibold text-gray-900 dark:text-white"
            >
              Email Notification Settings
            </h3>

            <!-- Notification Toggle -->
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="size-5 text-gray-600 dark:text-gray-400"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                  />
                  @if ($this->isNotificationsMuted)
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M 4 4 L 20 20"
                    />
                  @endif
                </svg>
                <div>
                  <p class="text-sm font-medium text-gray-900 dark:text-white">
                    Email Notifications
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $this->isNotificationsMuted ? 'Currently muted' : 'Currently active' }}
                  </p>
                </div>
              </div>
              <label class="relative inline-flex cursor-pointer items-center">
                <input
                  type="checkbox"
                  class="peer sr-only"
                  wire:click="toggleNotifications"
                  {{ ! $this->isNotificationsMuted ? 'checked' : '' }}
                />
                <div
                  class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
                ></div>
              </label>
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
