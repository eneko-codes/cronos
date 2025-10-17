<div class="fixed bottom-5 right-5 z-50 flex flex-col items-end gap-2">
  @foreach ($toasts as $toast)
    @php
      // Determine the CSS classes based on the toast variant.
      $variantClass = match ($toast['variant']) {
        'success' => 'border-green-500 bg-green-50 text-green-800',
        'error' => 'border-red-500 bg-red-50 text-red-800',
        'warning' => 'border-yellow-500 bg-yellow-50 text-yellow-800',
        'info' => 'border-blue-500 bg-blue-50 text-blue-800',
        default => 'border-gray-500 bg-gray-50 text-gray-800',
      };
    @endphp

    <div
      x-data="{ show: true }"
      x-init="
        // Automatically close the toast after 3 seconds.
        setTimeout(() => {
          show = false
          $wire.removeToast('{{ $toast['id'] }}')
        }, 3000)
      "
      x-show="show"
      x-transition:enter="transition duration-500 ease-in-out"
      x-transition:enter-start="translate-y-2 scale-95 transform opacity-0"
      x-transition:enter-end="translate-y-0 scale-100 transform opacity-100"
      x-transition:leave="transition duration-500 ease-in-out"
      x-transition:leave-start="translate-y-0 scale-100 transform opacity-100"
      x-transition:leave-end="translate-y-2 scale-95 transform opacity-0"
      class="{{ $variantClass }} flex w-full min-w-[200px] max-w-lg gap-2 rounded border-2 px-4 py-3 shadow-lg"
    >
      <!-- Icon Section -->
      <div class="mt-1 flex-shrink-0 self-start">
        @if ($toast['variant'] === 'success')
          <!-- Success Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="size-6"
          >
            <path
              fill-rule="evenodd"
              d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z"
              clip-rule="evenodd"
            />
          </svg>
        @elseif ($toast['variant'] === 'error')
          <!-- Error Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="size-6"
          >
            <path
              fill-rule="evenodd"
              d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
              clip-rule="evenodd"
            />
          </svg>
        @elseif ($toast['variant'] === 'warning')
          <!-- Warning Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="size-6"
          >
            <path
              fill-rule="evenodd"
              d="M9.401 3.003c1.155-2 4.043-2 5.197 0l7.355 12.748c1.154 2-.29 4.5-2.599 4.5H4.645c-2.309 0-3.752-2.5-2.598-4.5L9.4 3.003ZM12 8.25a.75.75 0 0 1 .75.75v3.75a.75.75 0 0 1-1.5 0V9a.75.75 0 0 1 .75-.75Zm0 8.25a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
              clip-rule="evenodd"
            />
          </svg>
        @elseif ($toast['variant'] === 'info')
          <!-- Info Icon -->
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 24 24"
            fill="currentColor"
            class="size-6"
          >
            <path
              fill-rule="evenodd"
              d="M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm8.706-1.442c1.146-.573 2.437.463 2.126 1.706l-.709 2.836.042-.02a.75.75 0 0 1 .67 1.34l-.04.022c-1.147.573-2.438-.463-2.127-1.706l.71-2.836-.042.02a.75.75 0 1 1-.671-1.34l.041-.022ZM12 9a.75.75 0 1 0 0-1.5.75.75 0 0 0 0 1.5Z"
              clip-rule="evenodd"
            />
          </svg>
        @endif
      </div>

      <!-- Message Section -->
      <div class="flex-1 self-center">
        <p class="text-sm font-medium">{{ $toast['message'] }}</p>
      </div>

      <!-- Close Button -->
      <button
        @click="show = false; $wire.removeToast('{{ $toast['id'] }}')"
        class="ml-2 text-gray-400 hover:text-gray-600"
      >
        <!-- Close Icon -->
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 24 24"
          fill="currentColor"
          class="size-5"
        >
          <path
            fill-rule="evenodd"
            d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z"
            clip-rule="evenodd"
          />
        </svg>
      </button>
    </div>
  @endforeach
</div>
