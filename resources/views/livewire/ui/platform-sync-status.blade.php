<div wire:poll.5s.visible="loadStatuses" class="w-full space-y-2">
  {{-- Platform sync status header --}}
  <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
    Platform Sync Status
  </div>

  {{-- Per-platform details --}}
  <div class="space-y-1.5">
    @foreach ($platformStatuses as $key => $status)
      <div
        class="{{ $status['status'] === 'failed' ? 'bg-red-50 dark:bg-red-900/20' : '' }} flex items-center justify-between rounded-md px-2 py-1 text-sm"
      >
        <div class="flex items-center gap-2">
          {{-- Status indicator dot --}}
          <span
            class="{{ $this->getStatusDotClasses($status['status']) }} inline-block h-3 w-3 rounded-full border border-gray-300 dark:border-gray-600"
          ></span>

          {{-- Platform name --}}
          <span class="font-medium text-gray-700 dark:text-gray-300">
            {{ $status['platform']->label() }}
          </span>
        </div>

        {{-- Relative time or status --}}
        <span
          class="{{ $status['status'] === 'failed' ? 'font-medium text-red-600 dark:text-red-400' : 'text-gray-500 dark:text-gray-400' }} text-xs"
        >
          @if ($status['status'] === 'in_progress')
            <span class="inline-flex items-center gap-1">
              <svg
                class="h-3 w-3 animate-spin"
                xmlns="http://www.w3.org/2000/svg"
                fill="none"
                viewBox="0 0 24 24"
              >
                <circle
                  class="opacity-25"
                  cx="12"
                  cy="12"
                  r="10"
                  stroke="currentColor"
                  stroke-width="4"
                ></circle>
                <path
                  class="opacity-75"
                  fill="currentColor"
                  d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"
                ></path>
              </svg>
              Syncing
            </span>
          @elseif ($status['status'] === 'failed')
            Failed
          @elseif ($status['status'] === 'unknown')
            Never
          @else
            {{ $status['relative_time'] }}
          @endif
        </span>
      </div>

      {{-- Error message for failed syncs --}}
      @if ($status['status'] === 'failed' && $status['error_message'])
        <div
          class="ml-6 rounded border-l-2 border-red-300 bg-red-50 px-2 py-0.5 text-xs text-red-600 dark:border-red-600 dark:bg-red-900/30 dark:text-red-400"
        >
          {{ Str::limit($status['error_message'], 60) }}
        </div>
      @endif
    @endforeach
  </div>
</div>
