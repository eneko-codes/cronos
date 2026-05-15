<div class="grid animate-pulse grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
  <!-- Monitoring Section -->
  <section class="relative lg:col-span-1">
    <div
      class="flex h-full min-h-[210px] flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="mb-2 flex items-center gap-2">
        <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-6 w-1/3 rounded bg-gray-300 dark:bg-gray-600"></div>
      </div>
      <div class="mb-4 h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
      <div class="mt-2 flex flex-col gap-3">
        <div class="h-8 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-8 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
      </div>
    </div>
  </section>
  <!-- API Health Check -->
  <section class="relative lg:col-span-1">
    <div
      class="flex h-full min-h-[260px] flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="mb-2 flex items-center gap-2">
        <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-6 w-1/2 rounded bg-gray-300 dark:bg-gray-600"></div>
      </div>
      <div class="mb-4 h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
      <div class="mt-2 flex flex-col gap-3">
        <div class="h-8 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-8 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-8 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
        <div class="h-8 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
      </div>
    </div>
  </section>
  <!-- Data Synchronization Settings -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="flex h-full min-h-[140px] flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="mb-2 flex items-center gap-2">
        <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-6 w-1/3 rounded bg-gray-300 dark:bg-gray-600"></div>
      </div>
      <div class="mb-4 h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
      <div class="mt-2 flex flex-col gap-4">
        <div
          class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
        >
          <div class="h-4 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
          <div class="h-8 w-48 rounded bg-gray-200 dark:bg-gray-700"></div>
        </div>
      </div>
    </div>
  </section>
  <!-- Notification Settings -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="flex h-full min-h-[340px] flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="mb-2 flex items-center gap-2">
        <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-6 w-1/3 rounded bg-gray-300 dark:bg-gray-600"></div>
      </div>
      <div class="mb-4 h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
      <div class="mt-2 flex flex-col gap-2">
        <div
          class="mb-2 flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
        >
          <div class="h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
          <div class="h-6 w-20 rounded-full bg-gray-200 dark:bg-gray-700"></div>
        </div>
        @for ($i = 0; $i < 7; $i++)
          <div class="h-6 w-full rounded-md bg-gray-200 dark:bg-gray-700"></div>
        @endfor
      </div>
    </div>
  </section>
  <!-- Data Retention Settings -->
  <section class="relative sm:col-span-2 lg:col-span-2">
    <div
      class="flex h-full min-h-[180px] flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
    >
      <div class="mb-2 flex items-center gap-2">
        <div class="h-6 w-6 rounded bg-gray-300 dark:bg-gray-600"></div>
        <div class="h-6 w-1/3 rounded bg-gray-300 dark:bg-gray-600"></div>
      </div>
      <div class="mb-4 h-4 w-2/3 rounded bg-gray-200 dark:bg-gray-700"></div>
      <div class="mt-2 flex flex-col gap-4">
        <div
          class="mb-2 flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
        >
          <div class="h-4 w-1/2 rounded bg-gray-200 dark:bg-gray-700"></div>
          <div class="h-8 w-40 rounded bg-gray-200 dark:bg-gray-700"></div>
        </div>
        <div class="h-10 w-full rounded bg-gray-200 dark:bg-gray-700"></div>
      </div>
    </div>
  </section>
</div>
