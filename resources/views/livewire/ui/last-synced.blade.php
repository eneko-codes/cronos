<div
  wire:poll.10s="loadSyncInfo"
  class="relative flex w-full items-center gap-1 text-sm"
>
  <!-- Display status dot -->
  <span class="mr-1 flex items-center">
    @if ($syncInfo['status'] === 'success')
      <span
        class="my-auto inline-block h-3 w-3 rounded-full border border-gray-300 bg-green-500"
      ></span>
    @elseif ($syncInfo['status'] === 'error')
      <span
        class="my-auto inline-block h-3 w-3 rounded-full border border-gray-300 bg-red-500"
      ></span>
    @elseif ($syncInfo['status'] === 'in_progress')
      <span
        class="my-auto inline-block h-3 w-3 animate-pulse rounded-full border border-gray-300 bg-blue-500"
      ></span>
    @else
      <span
        class="my-auto inline-block h-3 w-3 rounded-full border border-gray-300 bg-gray-400"
      ></span>
    @endif
  </span>
  <span class="font-medium text-gray-700 dark:text-gray-300">Synced:</span>
  <span class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
    {{ $syncInfo['time'] }}
  </span>
</div>
