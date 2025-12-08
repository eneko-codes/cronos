<div
  class="flex h-full flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
>
  <div class="flex flex-col items-start gap-1 text-lg font-bold">
    <div class="inline-flex flex-row items-center gap-2">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        class="size-5 text-gray-400 dark:text-gray-500"
        viewBox="0 0 16 16"
      >
        <path
          d="M12.5 9a3.5 3.5 0 1 1 0 7 3.5 3.5 0 0 1 0-7m.354 5.854 1.5-1.5a.5.5 0 0 0-.708-.708l-.646.647V10.5a.5.5 0 0 0-1 0v2.793l-.646-.647a.5.5 0 0 0-.708.708l1.5 1.5a.5.5 0 0 0 .708 0M8 1c-1.573 0-3.022.289-4.096.777C2.875 2.245 2 2.993 2 4s.875 1.755 1.904 2.223C4.978 6.711 6.427 7 8 7s3.022-.289 4.096-.777C13.125 5.755 14 5.007 14 4s-.875-1.755-1.904-2.223C11.022 1.289 9.573 1 8 1"
        />
        <path
          d="M2 7v-.839c.457.432 1.004.751 1.49.972C4.722 7.693 6.318 8 8 8s3.278-.307 4.51-.867c.486-.22 1.033-.54 1.49-.972V7c0 .424-.155.802-.411 1.133a4.51 4.51 0 0 0-4.815 1.843A12 12 0 0 1 8 10c-1.573 0-3.022-.289-4.096-.777C2.875 8.755 2 8.007 2 7m6.257 3.998L8 11c-1.682 0-3.278-.307-4.51-.867-.486-.22-1.033-.54-1.49-.972V10c0 1.007.875 1.755 1.904 2.223C4.978 12.711 6.427 13 8 13h.027a4.55 4.55 0 0 1 .23-2.002m-.002 3L8 14c-1.682 0-3.278-.307-4.51-.867-.486-.22-1.033-.54-1.49-.972V13c0 1.007.875 1.755 1.904 2.223C4.978 15.711 6.427 16 8 16c.536 0 1.058-.034 1.555-.097a4.5 4.5 0 0 1-1.3-1.905"
        />
      </svg>
      <h2>Data Synchronization Settings</h2>
    </div>
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
      Configure how often and how much data is synchronized from all connected
      APIs. These settings help balance data freshness, completeness, and
      storage usage.
    </p>
  </div>
  <div class="space-y-4">
    {{-- Sync Frequency --}}
    <div
      class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
    >
      <label
        for="syncFrequencySelect"
        class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
      >
        Data Synchronization Frequency
        <x-tooltip>
          <x-slot name="text">
            Determines how often the system runs a complete data synchronization
            process from all connected services to update local database.
          </x-slot>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-3"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
            />
          </svg>
        </x-tooltip>
      </label>
      <select
        id="syncFrequencySelect"
        wire:model.change="syncFrequency"
        class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
      >
        @foreach ($this->syncFrequencyOptions as $value => $label)
          <option value="{{ $value }}" @selected($syncFrequency === $value)>
            {{ $label }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- Sync Window Days --}}
    <div
      class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
    >
      <label
        for="syncWindowDaysSelect"
        class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
      >
        Data Synchronization Window
        <x-tooltip>
          <x-slot name="text">
            Controls how many days of data are fetched in each sync. Increasing
            this window helps fill data gaps if a sync is missed, but may
            increase API usage.
          </x-slot>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-3"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
            />
          </svg>
        </x-tooltip>
      </label>
      <select
        id="syncWindowDaysSelect"
        wire:model.change="syncWindowDays"
        class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
      >
        @foreach ($this->syncWindowDaysOptions as $value => $label)
          <option value="{{ $value }}" @selected($syncWindowDays == $value)>
            {{ $label }}
          </option>
        @endforeach
      </select>
    </div>

    {{-- Data Retention Period --}}
    <div
      class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
    >
      <label
        for="dataRetentionSelect"
        class="flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
      >
        Data Retention Period
        <x-tooltip>
          <x-slot name="text">
            Select how long to keep time entries, user attendances, schedules,
            and leaves. Select "Disabled" to disable automatic deletion.
          </x-slot>
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-3"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
            />
          </svg>
        </x-tooltip>
      </label>
      <select
        id="dataRetentionSelect"
        wire:model.change="dataRetentionGlobalPeriod"
        class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
      >
        @foreach ($this->dataRetentionOptions as $days => $label)
          <option
            value="{{ $days }}"
            @selected($dataRetentionGlobalPeriod == $days)
          >
            {{ $label }}
          </option>
        @endforeach
      </select>
    </div>
  </div>
</div>
