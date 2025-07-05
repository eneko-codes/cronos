<div>
  @if ($isOpen)
    <div
      wire:key="modal-backdrop"
      wire:click="$set('isOpen', false)"
      x-data
      @keydown.escape.window="$wire.set('isOpen', false)"
      class="fixed inset-0 z-40 flex items-center justify-center bg-gray-500/75 p-2 backdrop-blur-sm transition-opacity duration-300 md:p-6 dark:bg-gray-900/75"
      role="dialog"
      aria-modal="true"
    >
      <div
        wire:key="modal-content"
        wire:click.stop
        class="relative z-50 mx-4 my-8 flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border-2 border-gray-200 bg-gray-100 shadow-lg transition-transform duration-300 md:max-w-2xl lg:max-w-3xl dark:border-gray-700 dark:bg-gray-800"
      >
        <div
          class="sticky top-0 z-20 flex items-start justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-3 backdrop-blur dark:border-gray-700 dark:bg-gray-800"
        >
          <h2 class="m-0 mt-0 self-center text-xl font-bold">{{ $name }}</h2>
          <button
            wire:click="$set('isOpen', false)"
            class="mt-0 rounded-full p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
            aria-label="Close modal"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
              class="size-6"
            >
              <path
                fill-rule="evenodd"
                d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z"
                clip-rule="evenodd"
              />
            </svg>
          </button>
        </div>

        <div class="flex-1 overflow-x-auto overflow-y-auto p-4 md:p-6">
          <div
            class="flex flex-col gap-4"
            wire:target="isOpen"
            wire:loading.class="opacity-50"
          >
            <!-- User Details List -->
            <ul>
              @foreach ($details as $label => $value)
                <li
                  class="flex flex-col border-b border-gray-300 py-2 dark:border-gray-700"
                >
                  <p class="text-sm font-semibold">{{ $label }}:</p>
                  <p class="overflow-x-scroll text-sm font-light break-all">
                    {{ $value }}
                  </p>
                </li>
              @endforeach

              {{-- Add Created At with Tooltip --}}
              <li
                class="flex flex-col border-b border-gray-300 py-2 dark:border-gray-700"
              >
                <p class="text-sm font-semibold">Created at:</p>
                <x-tooltip :text="$createdAtFormatted">
                  <p class="overflow-x-scroll text-sm font-light break-all">
                    {{ $createdAtDiff }}
                  </p>
                </x-tooltip>
              </li>

              {{-- Add Updated At with Tooltip --}}
              <li
                class="flex flex-col border-b border-gray-300 py-2 dark:border-gray-700"
              >
                <p class="text-sm font-semibold">Updated at:</p>
                <x-tooltip :text="$updatedAtFormatted">
                  <p class="overflow-x-scroll text-sm font-light break-all">
                    {{ $updatedAtDiff }}
                  </p>
                </x-tooltip>
              </li>
            </ul>

            <!-- Admin Management Section -->
            <div class="flex flex-col gap-2">
              @if ($isAdmin && $canDemoteAdmin)
                <x-button
                  wire:click="demoteFromAdmin"
                  wire:confirm="Are you sure you want to remove admin rights from {{ $name }}?"
                  class="w-full"
                  size="lg"
                  variant="alert"
                  type="button"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    class="size-5"
                    viewBox="0 0 16 16"
                  >
                    <path
                      d="M13.879 10.414a2.501 2.501 0 0 0-3.465 3.465zm.707.707-3.465 3.465a2.501 2.501 0 0 0 3.465-3.465m-4.56-1.096a3.5 3.5 0 1 1 4.949 4.95 3.5 3.5 0 0 1-4.95-4.95ZM11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"
                    />
                  </svg>
                  Remove Admin Rights
                  <x-spinner
                    size="4"
                    wire:loading.delay
                    wire:target="demoteFromAdmin"
                  />
                </x-button>
              @elseif ($canPromoteToAdmin)
                <x-button
                  wire:click="promoteToAdmin"
                  wire:confirm="Are you sure you want to promote {{ $name }} to admin?"
                  class="w-full"
                  size="lg"
                  variant="success"
                  type="button"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    class="size-5"
                    viewBox="0 0 16 16"
                  >
                    <path
                      d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4m9.886-3.54c.18-.613 1.048-.613 1.229 0l.043.148a.64.64 0 0 0 .921.382l.136-.074c.561-.306 1.175.308.87.869l-.075.136a.64.64 0 0 0 .382.92l.149.045c.612.18.612 1.048 0 1.229l-.15.043a.64.64 0 0 0-.38.921l.074.136c.305.561-.309 1.175-.87.87l-.136-.075a.64.64 0 0 0-.92.382l-.045.149c-.18.612-1.048.612-1.229 0l-.043-.15a.64.64 0 0 0-.921-.38l-.136.074c-.561.305-1.175-.309-.87-.87l.075-.136a.64.64 0 0 0-.382-.92l-.148-.045c-.613-.18-.613-1.048 0-1.229l.148-.043a.64.64 0 0 0 .382-.921l-.074-.136c-.306-.561.308-1.175.869-.87l.136.075a.64.64 0 0 0 .92-.382zM14 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0"
                    />
                  </svg>
                  Promote to Admin
                  <x-spinner
                    size="4"
                    wire:loading.delay
                    wire:target="promoteToAdmin"
                  />
                </x-button>
              @endif
            </div>

            <!-- Tracking Management Section -->
            <div class="flex flex-col gap-2">
              @if ($isDoNotTrack && $canEnableTracking)
                <x-button
                  wire:click="enableTracking"
                  wire:confirm="Are you sure you want to enable tracking for {{ $name }}?"
                  class="w-full"
                  size="lg"
                  variant="success"
                  type="button"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 24 24"
                    fill="currentColor"
                    class="size-5"
                  >
                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
                    <path
                      fill-rule="evenodd"
                      d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z"
                      clip-rule="evenodd"
                    />
                  </svg>
                  Enable Tracking
                  <x-spinner
                    size="4"
                    wire:loading.delay
                    wire:target="enableTracking"
                  />
                </x-button>
              @elseif ($canNotTrack)
                <x-button
                  wire:click="doNotTrackUser"
                  wire:confirm="Are you sure you want to disable tracking for {{ $name }}?"
                  class="w-full"
                  size="lg"
                  variant="alert"
                  type="button"
                >
                  <div class="flex items-center gap-2">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 24 24"
                      fill="currentColor"
                      class="size-5"
                    >
                      <path
                        d="M3.53 2.47a.75.75 0 0 0-1.06 1.06l18 18a.75.75 0 1 0 1.06-1.06l-18-18ZM22.676 12.553a11.249 11.249 0 0 1-2.631 4.31l-3.099-3.099a5.25 5.25 0 0 0-6.71-6.71L7.759 4.577a11.217 11.217 0 0 1 4.242-.827c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113Z"
                      />
                      <path
                        d="M15.75 12c0 .18-.013.357-.037.53l-4.244-4.243A3.75 3.75 0 0 1 15.75 12ZM12.53 15.713l-4.243-4.244a3.75 3.75 0 0 0 4.244 4.243Z"
                      />
                      <path
                        d="M6.75 12c0-.619.107-1.213.304-1.764l-3.1-3.1a11.25 11.25 0 0 0-2.63 4.31c-.12.362-.12.752 0 1.114 1.489 4.467 5.704 7.69 10.675 7.69 1.5 0 2.933-.294 4.242-.827l-2.477-2.477A5.25 5.25 0 0 1 6.75 12Z"
                      />
                    </svg>
                    Do not track user
                    <x-spinner
                      size="4"
                      wire:loading.delay
                      wire:target="doNotTrackUser"
                    />
                  </div>
                </x-button>
              @endif
            </div>

            <!-- Notification Management Section -->
            <div class="flex flex-col gap-2">
              @if ($isMuted && $canUnmuteNotifications)
                <x-button
                  wire:click="unmuteNotifications"
                  wire:confirm="Are you sure you want to enable notifications for {{ $name }}?"
                  class="w-full"
                  size="lg"
                  variant="success"
                  type="button"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    class="size-5"
                    viewBox="0 0 16 16"
                  >
                    <path
                      d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"
                    />
                  </svg>
                  Enable Notifications
                  <x-spinner
                    size="4"
                    wire:loading.delay
                    wire:target="unmuteNotifications"
                  />
                </x-button>
              @elseif ($canMuteNotifications)
                <x-button
                  wire:click="muteNotifications"
                  wire:confirm="Are you sure you want to mute notifications for {{ $name }}?"
                  class="w-full"
                  size="lg"
                  variant="alert"
                  type="button"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    fill="currentColor"
                    class="size-5"
                    viewBox="0 0 16 16"
                  >
                    <path
                      d="M5.164 14H15c-1.5-1-2-5.902-2-7q0-.396-.06-.776zm6.288-10.617A5 5 0 0 0 8.995 2.1a1 1 0 1 0-1.99 0A5 5 0 0 0 3 7c0 .898-.335 4.342-1.278 6.113zM10 15a2 2 0 1 1-4 0zm-9.375.625a.53.53 0 0 0 .75.75l14.75-14.75a.53.53 0 0 0-.75-.75z"
                    />
                  </svg>
                  Mute Notifications
                  <x-spinner
                    size="4"
                    wire:loading.delay
                    wire:target="muteNotifications"
                  />
                </x-button>
              @endif
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
