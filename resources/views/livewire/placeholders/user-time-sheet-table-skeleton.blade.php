@php
  // Determine row count based on viewMode, default to 7 (week) if not specified or different from month
  $rowCount = isset($viewMode) && $viewMode === 'month' ? 30 : 7;
@endphp

<div
  class="animate-pulse overflow-hidden rounded-lg bg-white shadow dark:bg-gray-800"
>
  <!-- Table Header Placeholder -->
  <div
    class="flex items-center justify-between border-b border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-700/50"
  >
    <div class="flex items-center gap-2">
      <div class="h-6 w-24 rounded bg-gray-300 dark:bg-gray-600"></div>
      <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
      <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
    </div>
    <div class="flex items-center gap-2">
      <div class="h-6 w-16 rounded bg-gray-300 dark:bg-gray-600"></div>
      <div class="h-6 w-20 rounded bg-gray-300 dark:bg-gray-600"></div>
    </div>
  </div>

  <!-- Table Body Placeholder -->
  <div class="divide-y divide-gray-200 dark:divide-gray-700">
    @for ($i = 0; $i < $rowCount; $i++)
      <div class="flex items-center justify-between p-4">
        <div class="h-4 w-1/4 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-4 w-1/6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-4 w-1/5 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-4 w-1/4 rounded bg-gray-300 dark:bg-gray-600"></div>
      </div>
    @endfor
  </div>

  <!-- Table Footer Placeholder -->
  <div
    class="border-t border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-700/50"
  >
    <div class="flex items-center justify-between">
      <div class="h-4 w-1/3 rounded bg-gray-300 dark:bg-gray-600"></div>
      <div class="h-4 w-1/4 rounded bg-gray-300 dark:bg-gray-600"></div>
    </div>
  </div>
</div>
