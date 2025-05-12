<div class="flex animate-pulse flex-col gap-6">
  <!-- Back button placeholder -->
  <div class="mb-2 h-6 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>

  <!-- Project Header Placeholder -->
  <div class="flex flex-col gap-2">
    <div class="mb-2 h-8 w-1/3 rounded bg-gray-300 dark:bg-gray-600"></div>
    <div class="flex flex-wrap gap-4">
      <div class="h-4 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
      <div class="h-4 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
    </div>
    <div class="mt-2 flex flex-wrap gap-1">
      @for ($i = 0; $i < 4; $i++)
        <div class="h-5 w-16 rounded bg-gray-200 dark:bg-gray-700"></div>
      @endfor
    </div>
  </div>

  <!-- Sections Container: Tasks and Project Time Entries -->
  <div class="grid grid-cols-1 items-start gap-6 md:grid-cols-2">
    <!-- Section: Tasks Placeholder -->
    <div class="flex flex-col gap-3 rounded-md bg-white p-4 dark:bg-gray-800">
      <div class="mb-2 h-6 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>
      @for ($i = 0; $i < 10; $i++)
        <div
          class="mb-2 rounded-md border border-gray-300 bg-gray-50 p-3 dark:border-gray-500 dark:bg-gray-700"
        >
          <div class="flex flex-row items-center justify-between gap-2">
            <div class="flex flex-1 flex-col gap-1">
              <div
                class="mb-1 h-4 w-1/2 rounded bg-gray-300 dark:bg-gray-600"
              ></div>
              <div class="h-3 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="flex-none">
              <div class="h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
          </div>
        </div>
      @endfor
    </div>
    <!-- Section: Project-Level Time Entries Placeholder -->
    <div class="flex flex-col gap-3 rounded-md bg-white p-4 dark:bg-gray-800">
      <div class="mb-2 h-6 w-40 rounded bg-gray-200 dark:bg-gray-700"></div>
      @for ($i = 0; $i < 10; $i++)
        <div
          class="mb-2 rounded-md border border-gray-300 bg-gray-50 p-3 dark:border-gray-500 dark:bg-gray-700"
        >
          <div class="flex flex-row items-center justify-between gap-2">
            <div class="flex flex-1 flex-col gap-1">
              <div
                class="mb-1 h-4 w-1/2 rounded bg-gray-300 dark:bg-gray-600"
              ></div>
              <div class="h-3 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
            <div class="flex-none">
              <div class="h-4 w-4 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
          </div>
        </div>
      @endfor
    </div>
  </div>
</div>
