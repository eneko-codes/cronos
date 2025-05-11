<div class="flex animate-pulse flex-col gap-5">
  {{-- Conditionally display Back button Skeleton --}}
  @if ($isAdminPlaceholder && $isViewingSpecificUserPlaceholder)
    <!-- Back button Skeleton -->
    <div
      class="inline-flex items-center gap-1.5 self-start rounded-lg bg-gray-300 px-2.5 py-1.5 dark:bg-gray-600"
    >
      <div class="h-3.5 w-3.5 rounded-sm bg-gray-400 dark:bg-gray-500"></div>
      <!-- SVG placeholder -->
      <div class="h-3.5 w-24 rounded-sm bg-gray-400 dark:bg-gray-500"></div>
      <!-- Text placeholder -->
    </div>
  @endif

  <!-- Top User Info and Notification Area Skeleton -->
  <div class="flex items-center gap-2">
    <!-- User Name Placeholder -->
    <div class="h-6 w-1/3 rounded-md bg-gray-300 dark:bg-gray-600"></div>
    <!-- Badge Placeholders (Corrected to two: Admin, Odoo) -->
    <div class="h-5 w-16 rounded-md bg-gray-300 dark:bg-gray-600"></div>
    <div class="h-5 w-12 rounded-md bg-gray-300 dark:bg-gray-600"></div>
  </div>

  {{-- Conditionally display Missing Account Notification Skeleton --}}
  @if ($showMissingLinksAlertPlaceholder)
    <!-- Missing Account Notification Skeleton  -->
    <div
      class="rounded-md border border-gray-300/50 bg-gray-200/50 p-4 dark:border-gray-600/50 dark:bg-gray-800/30"
    >
      <div class="flex flex-col gap-2">
        <div class="flex flex-row items-center gap-2">
          <div
            class="h-5 w-5 flex-shrink-0 rounded-sm bg-gray-300 dark:bg-gray-600"
          ></div>
          <!-- Icon Placeholder -->
          <div class="h-5 w-2/5 rounded bg-gray-300 dark:bg-gray-600"></div>
          <!-- Title Placeholder "Missing Account Links" -->
        </div>
        <div class="mt-1 h-3 w-full rounded bg-gray-300 dark:bg-gray-600"></div>
        <!-- Text Line 1 -->
        <div class="h-3 w-5/6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <!-- Text Line 2 -->
        <div class="mt-2 flex flex-wrap gap-1">
          <!-- Corrected to three badges: ProofHub, DeskTime, SystemPin -->
          <div class="h-4 w-16 rounded-sm bg-gray-300 dark:bg-gray-600"></div>
          <div class="h-4 w-16 rounded-sm bg-gray-300 dark:bg-gray-600"></div>
          <div class="h-4 w-16 rounded-sm bg-gray-300 dark:bg-gray-600"></div>
        </div>
      </div>
    </div>
  @endif

  <!-- Widgets Section Skeleton -->
  <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
    <!-- Widget Skeleton (repeat 4 times) -->
    @for ($i = 0; $i < 4; $i++)
      <div
        class="flex flex-col gap-3 rounded-lg bg-gray-200 p-4 shadow dark:bg-gray-800"
      >
        <div class="flex items-center gap-2">
          <div class="h-5 w-5 rounded-full bg-gray-300 dark:bg-gray-600"></div>
          <!-- Icon -->
          <div class="h-4 w-2/3 rounded bg-gray-300 dark:bg-gray-600"></div>
          <!-- Title -->
        </div>
        <div class="h-7 w-3/5 rounded bg-gray-300 dark:bg-gray-600"></div>
        <!-- Main Value -->
        <div class="h-3 w-3/4 rounded bg-gray-300 dark:bg-gray-600"></div>
        <!-- Subtext -->
      </div>
    @endfor
  </div>

  <!-- Timesheet Table Section Skeleton -->
  <div
    class="flex flex-col gap-4 rounded-lg bg-gray-200 p-4 shadow dark:bg-gray-800"
  >
    <!-- Table Header and Controls Skeleton -->
    <div
      class="flex flex-col items-start justify-between gap-4 md:flex-row md:items-center"
    >
      <div class="flex items-center gap-2">
        <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <!-- Nav Button -->
        <div class="h-5 w-48 rounded bg-gray-300 dark:bg-gray-600"></div>
        <!-- "Week of..." -->
        <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <!-- Nav Button -->
      </div>
      <div class="flex items-center gap-2">
        <div class="h-8 w-24 rounded-md bg-gray-300 dark:bg-gray-600"></div>
        <!-- Deviations Toggle -->
        <div class="h-8 w-32 rounded-md bg-gray-300 dark:bg-gray-600"></div>
        <!-- View Mode Tabs -->
      </div>
    </div>

    <!-- Table Skeleton -->
    <div class="overflow-x-auto">
      <div class="w-full space-y-2">
        <!-- Table Head Skeleton -->
        <div
          class="flex h-10 items-center gap-2 rounded bg-gray-300 p-2 dark:bg-gray-700"
        >
          <div class="h-4 w-1/4 rounded bg-gray-400 dark:bg-gray-600"></div>
          <div class="h-4 w-1/6 rounded bg-gray-400 dark:bg-gray-600"></div>
          <div class="h-4 w-1/6 rounded bg-gray-400 dark:bg-gray-600"></div>
          <div class="h-4 w-1/6 rounded bg-gray-400 dark:bg-gray-600"></div>
          <div class="h-4 w-1/4 rounded bg-gray-400 dark:bg-gray-600"></div>
        </div>
        <!-- Table Body Skeleton Rows (repeat a few times) -->
        @for ($r = 0; $r < 7; $r++)
          <div
            class="flex h-12 items-center gap-2 rounded bg-gray-300 p-2 dark:bg-gray-700"
          >
            <div class="h-5 w-1/4 rounded bg-gray-400 dark:bg-gray-600"></div>
            <div class="h-5 w-1/6 rounded bg-gray-400 dark:bg-gray-600"></div>
            <div class="h-5 w-1/6 rounded bg-gray-400 dark:bg-gray-600"></div>
            <div class="h-5 w-1/6 rounded bg-gray-400 dark:bg-gray-600"></div>
            <div class="h-5 w-1/4 rounded bg-gray-400 dark:bg-gray-600"></div>
          </div>
        @endfor

        <!-- Table Footer/Totals Skeleton -->
        <div
          class="mt-2 flex h-10 items-center gap-2 rounded bg-gray-300 p-2 dark:bg-gray-700"
        >
          <div class="h-4 w-1/4 rounded bg-gray-400 dark:bg-gray-600"></div>
          <div class="h-4 w-1/6 rounded bg-gray-400 dark:bg-gray-600"></div>
        </div>
      </div>
    </div>
  </div>
</div>
