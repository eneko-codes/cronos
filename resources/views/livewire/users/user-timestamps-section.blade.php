<section>
  <h3
    class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
  >
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 20 20"
      fill="currentColor"
      class="size-4"
    >
      <path
        fill-rule="evenodd"
        d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-13a.75.75 0 0 0-1.5 0v5c0 .414.336.75.75.75h4a.75.75 0 0 0 0-1.5h-3.25V5Z"
        clip-rule="evenodd"
      />
    </svg>
    Timestamps
  </h3>
  <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
    <div
      class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
    >
      <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Created
      </span>
      <x-tooltip :text="$this->createdAtFormatted">
        <div class="text-sm text-gray-800 dark:text-gray-200">
          {{ $this->createdAtDiff }}
        </div>
      </x-tooltip>
    </div>
    <div
      class="rounded-lg border border-gray-200 bg-white p-3 dark:border-gray-700 dark:bg-gray-900"
    >
      <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Updated
      </span>
      <x-tooltip :text="$this->updatedAtFormatted">
        <div class="text-sm text-gray-800 dark:text-gray-200">
          {{ $this->updatedAtDiff }}
        </div>
      </x-tooltip>
    </div>
  </div>
</section>
