@php
  // Determine row count based on viewMode, default to 7 (week) if not specified or different from month
  $rowCount = isset($viewMode) && $viewMode === 'month' ? 30 : 7;
  $showDeviationsPlaceholder = isset($showDeviations) && $showDeviations === true;
  $columnCount = 5 + ($showDeviationsPlaceholder ? 3 : 0);
@endphp

<div
  class="animate-pulse overflow-hidden rounded-lg bg-white shadow-md dark:bg-gray-800"
>
  <!-- Header Placeholder -->
  <div
    class="flex flex-col items-start justify-between gap-4 border-b border-gray-200 bg-gray-50 p-4 md:flex-row md:items-center dark:border-gray-700 dark:bg-gray-700/50"
  >
    <div class="flex flex-row items-center gap-4">
      {{-- Period Navigation Placeholder --}}
      <div class="flex items-center gap-2">
        <div class="h-8 w-8 rounded-lg bg-gray-200 dark:bg-gray-600"></div>
        {{-- Prev button --}}
        <div class="h-5 w-40 rounded bg-gray-200 dark:bg-gray-600"></div>
        {{-- Date text --}}
        <div class="h-8 w-8 rounded-lg bg-gray-200 dark:bg-gray-600"></div>
        {{-- Next button --}}
      </div>
    </div>
    {{-- View Mode Toggles Placeholder --}}
    <div class="flex items-center gap-2">
      <div class="h-9 w-28 rounded-md bg-gray-200 dark:bg-gray-600"></div>
      {{-- Deviations toggle --}}
      <div
        class="flex items-center gap-1 rounded-md bg-gray-200 p-1 dark:bg-gray-600"
      >
        {{-- Tabs --}}
        <div class="h-7 w-20 rounded bg-gray-300 dark:bg-gray-500"></div>
        <div class="h-7 w-20 rounded bg-gray-200/50 dark:bg-gray-600/50"></div>
      </div>
    </div>
  </div>

  <!-- Table Body Placeholder -->
  <div class="divide-y divide-gray-200 dark:divide-gray-700">
    @for ($i = 0; $i < $rowCount; $i++)
      <div class="flex items-center space-x-3 p-4">
        <div
          class="h-4 flex-none rounded bg-gray-200 dark:bg-gray-600"
          style="width: 15%"
        ></div>
        {{-- Day --}}
        <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-600"></div>
        {{-- Scheduled --}}
        <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-600"></div>
        {{-- Leave --}}
        <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-600"></div>
        {{-- Attendance --}}
        <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-600"></div>
        {{-- Worked --}}
        @if ($showDeviationsPlaceholder)
          <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-600"></div>
          {{-- Deviation 1 --}}
          <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-600"></div>
          {{-- Deviation 2 --}}
          <div class="h-4 flex-1 rounded bg-gray-200 dark:bg-gray-600"></div>
          {{-- Deviation 3 --}}
        @endif
      </div>
    @endfor
  </div>

  <!-- Table Footer Placeholder -->
  <div
    class="border-t border-gray-200 bg-gray-50 px-4 py-4 dark:border-gray-700 dark:bg-gray-700/50"
  >
    <div class="flex items-center justify-between gap-4">
      <div class="h-5 w-1/3 rounded bg-gray-200 dark:bg-gray-600"></div>
      <div class="h-5 w-1/4 rounded bg-gray-200 dark:bg-gray-600"></div>
      @if ($showDeviationsPlaceholder)
        <div class="h-5 w-1/5 rounded bg-gray-200 dark:bg-gray-600"></div>
      @endif
    </div>
  </div>
</div>
