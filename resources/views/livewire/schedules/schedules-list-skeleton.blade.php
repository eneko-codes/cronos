<div class="flex animate-pulse flex-col gap-4">
  <!-- Header Section Placeholder -->
  <div
    class="flex flex-col items-start justify-start gap-4 rounded-lg bg-white p-3 shadow-sm md:flex-row md:items-stretch dark:bg-gray-800"
  >
    <div class="flex flex-1 flex-col items-start gap-4 md:flex-row">
      <div
        class="flex w-full flex-col items-stretch gap-3 md:w-auto md:flex-row md:items-center"
      >
        <!-- Search Input Placeholder -->
        <div
          class="h-9 w-full max-w-xs rounded-md bg-gray-200 dark:bg-gray-700"
        ></div>
        <!-- Control Group Placeholder -->
        <div class="flex items-center gap-2">
          <div class="h-8 w-40 rounded-md bg-gray-200 dark:bg-gray-700"></div>
          <div class="h-8 w-20 rounded-md bg-gray-200 dark:bg-gray-700"></div>
        </div>
      </div>
    </div>
  </div>
  <!-- Schedule List Container Placeholder -->
  <div
    class="flex flex-col gap-2 rounded-lg bg-white p-3 shadow-sm dark:bg-gray-800"
  >
    @for ($i = 0; $i < 20; $i++)
      <div
        class="mb-2 block rounded-md border border-gray-300 bg-gray-100 p-3 dark:border-gray-600 dark:bg-gray-700"
      >
        <div class="flex flex-row items-center justify-between gap-4">
          <div class="flex flex-1 flex-col gap-1">
            <div
              class="mb-1 h-4 w-1/2 rounded bg-gray-300 dark:bg-gray-600"
            ></div>
            <div class="flex flex-row gap-4 text-xs">
              <div class="h-3 w-24 rounded bg-gray-200 dark:bg-gray-700"></div>
              <div class="h-3 w-32 rounded bg-gray-200 dark:bg-gray-700"></div>
            </div>
          </div>
          <div class="flex-none">
            <div class="h-5 w-5 rounded bg-gray-200 dark:bg-gray-700"></div>
          </div>
        </div>
      </div>
    @endfor

    <!-- Pagination Links Placeholder -->
    <div class="mt-4 flex w-full justify-between">
      <div class="h-8 w-24 rounded-md bg-gray-200 dark:bg-gray-700"></div>
      <div class="h-8 w-24 rounded-md bg-gray-200 dark:bg-gray-700"></div>
    </div>
  </div>
</div>
