<div>
  @if ($isOpen)
    <div
      wire:key="modal-backdrop-notification"
      wire:click="closeModal"
      x-data
      @keydown.escape.window="$wire.closeModal()"
      class="fixed inset-0 z-[100] flex items-center justify-center bg-gray-500/75 backdrop-blur-sm transition-opacity duration-300 dark:bg-gray-900/75"
    >
      <div
        wire:key="modal-content-notification"
        wire:click.stop
        class="relative z-[101] flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border-2 border-gray-200 bg-gray-100 shadow-lg transition-transform duration-300 dark:border-gray-700 dark:bg-gray-800"
      >
        {{-- Sticky Header --}}
        <div
          class="sticky top-0 z-20 flex items-start justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-3 backdrop-blur dark:border-gray-700 dark:bg-gray-800"
        >
          <div class="mt-0 flex flex-col items-start gap-2">
            @php
              $level = $notificationData['level'] ?? 'info';
              $badgeVariant = match ($level) {
                'success' => 'success',
                'warning' => 'warning',
                'error' => 'alert',
                default => 'info',
              };
            @endphp

            <x-badge :variant="$badgeVariant" size="md">
              {{ Str::ucfirst($level) }}
            </x-badge>
            <h2 class="m-0 text-xl font-bold text-gray-900 dark:text-white">
              {{ $notificationSubject }}
            </h2>
            <div
              class="flex w-full flex-row flex-wrap gap-4 text-xs text-gray-500 dark:text-gray-400"
            >
              <x-tooltip :text="$notificationCreatedAtFormatted">
                <span>Received: {{ $notificationCreatedAtDiff }}</span>
              </x-tooltip>
              @if ($notificationReadAtDiff)
                <x-tooltip :text="$notificationReadAtFormatted">
                  <span>Read: {{ $notificationReadAtDiff }}</span>
                </x-tooltip>
              @endif
            </div>
          </div>
          <button
            wire:click="closeModal"
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

        {{-- Content Area --}}
        <div
          class="flex flex-1 flex-col gap-2 overflow-y-auto p-6"
          wire:target="isOpen"
          wire:loading.class="opacity-50"
        >
          {{-- Message Section --}}
          <div class="py-4">
            <p class="text-sm text-gray-800 dark:text-gray-200">
              {!! $notificationMessage !!}
            </p>
          </div>

          {{-- Actions Section --}}
          <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:justify-end">
            <x-button
              wire:click="deleteNotification"
              wire:confirm="Are you sure you want to delete this notification?"
              size="md"
              variant="alert"
              type="button"
            >
              <svg
                xmlns="http://www.w3.org/2000/svg"
                fill="currentColor"
                class="size-4"
                viewBox="0 0 16 16"
              >
                <path
                  d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5m-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5M4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06m6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528M8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5"
                />
              </svg>
              Delete
              <x-spinner
                size="4"
                wire:loading.delay
                wire:target="deleteNotification"
              />
            </x-button>
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
