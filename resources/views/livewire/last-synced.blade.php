<div wire:poll.10s="loadSyncInfo" class="relative w-full flex items-center gap-1 text-sm">
    <!-- Display status dot -->
    <span class="mr-1 flex items-center">
        @if($syncInfo['status'] === 'success')
            <span class="inline-block h-3 w-3 rounded-full bg-green-500 border border-gray-300 my-auto"></span>
        @elseif($syncInfo['status'] === 'error')
            <span class="inline-block h-3 w-3 rounded-full bg-red-500 border border-gray-300 my-auto"></span>
        @elseif($syncInfo['status'] === 'in_progress')
            <span class="inline-block h-3 w-3 rounded-full bg-blue-500 animate-pulse border border-gray-300 my-auto"></span>
        @else
            <span class="inline-block h-3 w-3 rounded-full bg-gray-400 border border-gray-300 my-auto"></span>
        @endif
    </span>
    <span class="text-gray-700 font-medium dark:text-gray-300">Synced:</span>
    <span class="flex items-center gap-2 text-gray-800 dark:text-gray-200">
        {{ $syncInfo['time'] }}
    </span>
</div> 