<div
  wire:poll.5s.visible="loadStatuses"
  class="w-full space-y-2"
  x-data="{ cooldownRemaining: @entangle('cooldownRemainingSeconds').live }"
  x-init="
    setInterval(() => {
      if (cooldownRemaining > 0) {
        cooldownRemaining = Math.max(0, cooldownRemaining - 1)
      }
    }, 1000)
  "
>
  {{-- Platform sync status header --}}
  <div class="flex items-center justify-between">
    <div class="text-sm font-medium text-gray-700 dark:text-gray-300">
      Platform Sync Status
    </div>

    {{-- Admin and Maintenance sync button --}}
    @if (auth()->user()?->isAdmin() ||auth()->user()?->isMaintenance())
      <x-button
        wire:click="runSync"
        type="button"
        size="xs"
        :variant="$this->isSyncOnCooldown ? 'default' : 'info'"
        x-bind:title="
          cooldownRemaining > 0
            ? 'Cooldown: ' + cooldownRemaining + 's remaining'
            : 'Trigger manual sync'
        "
        x-bind:class="cooldownRemaining > 0 ? 'opacity-60 cursor-not-allowed' : ''"
      >
        <svg
          class="h-3 w-3"
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="2"
          stroke="currentColor"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99"
          />
        </svg>
        <span>Sync now</span>
        <span
          x-show="cooldownRemaining > 0"
          x-text="' (' + cooldownRemaining + 's)'"
        ></span>
      </x-button>
    @endif
  </div>

  {{-- Per-platform details --}}
  <div class="space-y-1.5">
    @foreach ($platformStatuses as $key => $status)
      <div
        class="{{ $status['status'] === 'failed' ? 'bg-red-100 dark:bg-red-900/30' : '' }} flex items-center justify-between rounded-md px-2 py-1 text-sm"
      >
        <div class="flex items-center gap-2">
          {{-- Status indicator dot --}}
          <span
            class="{{ $this->getStatusDotClasses($status['status']) }} inline-block h-3 w-3 rounded-full border border-gray-300 dark:border-gray-600"
          ></span>

          {{-- Platform name --}}
          <span
            class="{{ $status['status'] === 'failed' ? 'font-medium text-red-700 dark:text-red-300' : 'font-medium text-gray-700 dark:text-gray-300' }}"
          >
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
          class="ml-6 rounded border-l-2 border-red-400 bg-red-100 px-2 py-0.5 text-xs font-medium text-red-800 dark:border-red-500 dark:bg-red-900/40 dark:text-red-200"
        >
          {{ Str::limit($status['error_message'], 60) }}
        </div>
      @endif
    @endforeach
  </div>
</div>
