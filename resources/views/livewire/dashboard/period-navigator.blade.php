<div class="flex items-center gap-2">
  <button
    class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-slate-100 px-1.5 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-slate-200 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
    wire:click="previousPeriod"
  >
    ←
  </button>

  <h2 class="text-sm font-semibold">
    {{ $this->periodTitle }}
  </h2>

  <button
    class="inline-flex h-fit w-fit flex-row items-center justify-center gap-2 rounded-lg bg-slate-100 px-1.5 py-1 text-xs font-semibold text-gray-800 shadow-sm hover:bg-slate-200 disabled:opacity-50 dark:bg-gray-600 dark:text-gray-200 dark:hover:bg-gray-500"
    wire:click="nextPeriod"
    @disabled($this->isNextPeriodDisabled)
  >
    →
  </button>
</div>
