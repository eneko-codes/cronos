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
        {{-- Close Button --}}
        <button
          wire:click="closeModal"
          class="absolute right-2 top-2 z-10 rounded-full p-1 text-gray-500 hover:bg-gray-200 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
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

        {{-- Content Area --}}
        <div
          class="flex flex-1 flex-col gap-2 overflow-y-auto p-6"
          wire:target="isOpen"
          wire:loading.class="opacity-50"
        >
          {{-- Subject Header with Optional Badge --}}
          <div class="flex flex-col items-start gap-2">
            {{-- Conditional Badge --}}
            @php
              $level = $notificationData['level'] ?? 'info';
              // Map notification level to badge variant
              $badgeVariant = match ($level) {
                'success' => 'success',
                'warning' => 'warning',
                'error' => 'alert',
                default => 'info',
              };
            @endphp

            {{-- Always show badge now --}}
            <x-badge :variant="$badgeVariant" size="md">
              {{ Str::ucfirst($level) }}
            </x-badge>

            <h2 class="text-xl font-bold text-gray-900 dark:text-white">
              {{ $notificationSubject }}
            </h2>
          </div>

          {{-- Timestamps Section --}}
          <div
            class="flex flex-row gap-4 text-xs text-gray-500 dark:text-gray-400"
          >
            {{-- Received Timestamp --}}
            <x-tooltip :text="$notificationCreatedAtFormatted">
              <span>Received: {{ $notificationCreatedAtDiff }}</span>
            </x-tooltip>

            {{-- Read Timestamp (Conditional) --}}
            @if ($notificationReadAtDiff)
              <x-tooltip :text="$notificationReadAtFormatted">
                <span>Read: {{ $notificationReadAtDiff }}</span>
              </x-tooltip>
            @endif
          </div>

          {{-- Message Section --}}
          <div class="border-y border-gray-300 py-4 dark:border-gray-700">
            <p class="text-sm text-gray-800 dark:text-gray-200">
              {!! $notificationMessage !!}
            </p>
          </div>

          {{-- Actions Section --}}
          <div class="mt-2 flex flex-col gap-2 sm:flex-row sm:justify-end">
            @if (! $notificationReadAtDiff)
              <x-button
                wire:click="markAsRead"
                size="md"
                variant="info"
                type="button"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="currentColor"
                  class="size-4"
                  viewBox="0 0 16 16"
                >
                  <path
                    d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"
                  />
                  <path
                    d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"
                  />
                </svg>
                Mark as Read
                <x-spinner
                  size="4"
                  wire:loading.delay
                  wire:target="markAsRead"
                />
              </x-button>
            @endif

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
