<div class="flex w-full animate-pulse flex-col gap-5 overflow-hidden">
  <!-- Header Section Placeholder -->
  <div
    class="flex flex-col items-start justify-start gap-4 rounded-lg bg-white p-3 shadow-sm md:flex-row md:items-stretch dark:bg-gray-800"
  >
    <div class="flex flex-1 flex-col items-center gap-4 md:flex-row">
      <!-- Search Input Placeholder -->
      <div
        class="h-9 w-full max-w-48 rounded-md bg-gray-200 dark:bg-gray-700"
      ></div>

      <!-- Filter Tabs Placeholder -->
      <div class="flex h-9 w-full gap-2 md:w-auto">
        <div class="h-full w-20 rounded-md bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-full w-20 rounded-md bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-full w-20 rounded-md bg-gray-200 dark:bg-gray-700"></div>
      </div>
    </div>

    <!-- Action Buttons Placeholder -->
    <div class="flex flex-row items-center gap-2">
      <div class="h-9 w-9 rounded-md bg-gray-200 dark:bg-gray-700"></div>
    </div>
  </div>

  <!-- Users Table Placeholder -->
  <div class="rounded-lg bg-white p-3 shadow-sm dark:bg-gray-800">
    <div class="w-full border-collapse">
      <div class="text-sm">
        <!-- Loop for a few placeholder rows -->
        @for ($i = 0; $i < $itemsPerPage; $i++)
          <div
            class="flex flex-row items-center justify-between gap-4 border-b border-gray-200 p-2 dark:border-gray-700"
          >
            <!-- User Info Column Placeholder -->
            <div
              class="flex w-full flex-1 flex-col gap-1 md:w-auto md:flex-row md:items-center"
            >
              <div class="flex items-center gap-2">
                <!-- Online Status Indicator Placeholder -->
                <div
                  class="h-2 w-2 rounded-full bg-gray-300 dark:bg-gray-600"
                ></div>
                <!-- User Name Placeholder -->
                <div
                  class="h-4 w-32 rounded bg-gray-300 dark:bg-gray-600"
                ></div>
              </div>
              <!-- User Badges Placeholder -->
              <div class="flex gap-1">
                <div
                  class="h-4 w-10 rounded-md bg-gray-300 dark:bg-gray-600"
                ></div>
                <div
                  class="h-4 w-10 rounded-md bg-gray-300 dark:bg-gray-600"
                ></div>
              </div>
            </div>
            <!-- Action Column Placeholder -->
            <div class="flex-none items-center justify-center">
              <div
                class="h-6 w-20 rounded-md bg-gray-300 dark:bg-gray-600"
              ></div>
            </div>
          </div>
        @endfor
      </div>
    </div>

    <!-- Pagination Links Placeholder -->
    <div class="mt-4 flex w-full justify-between">
      <div class="h-8 w-24 rounded-md bg-gray-200 dark:bg-gray-700"></div>
      <div class="flex gap-2">
        <div class="h-8 w-8 rounded-md bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-8 w-8 rounded-md bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-8 w-8 rounded-md bg-gray-200 dark:bg-gray-700"></div>
      </div>
      <div class="h-8 w-24 rounded-md bg-gray-200 dark:bg-gray-700"></div>
    </div>
  </div>
</div>
