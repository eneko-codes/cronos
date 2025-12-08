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
          d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"
        />
      </svg>
      <h2>Notification Settings</h2>
    </div>
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
      Control system-wide notification settings. These settings apply to all
      users.
    </p>
  </div>

  <div class="space-y-4">
    {{-- Master Global Toggle --}}
    <div
      class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
    >
      <label
        class="inline-flex items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-200"
      >
        Enable All Notifications Globally
        <x-tooltip>
          <x-slot name="text">
            Master switch for all non-login email notifications. Users can
            further customize their preferences in the sidebar.
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
      <x-toggle-switch
        id="globalNotificationsToggle"
        wire:model.change="globalNotificationsEnabled"
        :checked="(bool) $globalNotificationsEnabled"
      />
    </div>

    {{-- Notification Channel Selector (Admin Only) --}}
    @if (auth()->user()?->isAdmin())
      <div
        class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
      >
        <label
          for="notificationChannelSelect"
          class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
        >
          Notification Channel
          <x-tooltip>
            <x-slot name="text">
              Select the delivery channel for all notifications (except login
              flow notifications which always use email).
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
          id="notificationChannelSelect"
          wire:model.change="notificationChannel"
          class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
        >
          <option value="mail" @selected($notificationChannel === 'mail')>
            Email
          </option>
          <option value="slack" @selected($notificationChannel === 'slack')>
            Slack
          </option>
          <option
            value="database"
            @selected($notificationChannel === 'database')
          >
            In App
          </option>
        </select>
      </div>

      {{-- Notification Retention Period --}}
      <div
        class="flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
      >
        <label
          for="notificationRetentionSelect"
          class="flex flex-row items-center gap-1 text-sm font-medium text-gray-600 dark:text-gray-300"
        >
          Notification Retention Period
          <x-tooltip>
            <x-slot name="text">
              Select how long to keep notifications before automatically
              deleting them. Old notifications are pruned daily at 2:00 AM.
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
          id="notificationRetentionSelect"
          wire:model.change="notificationRetentionPeriod"
          class="focus:ring-opacity-50 block w-48 rounded-md border border-gray-300 bg-gray-200 px-2 text-sm shadow-sm focus:border-indigo-500 focus:ring focus:ring-indigo-500 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-100"
        >
          @foreach ($this->notificationRetentionOptions as $days => $label)
            <option
              value="{{ $days }}"
              @selected($notificationRetentionPeriod == $days)
            >
              {{ $label }}
            </option>
          @endforeach
        </select>
      </div>
    @endif

    {{-- Grouped Notification Type Toggles --}}
    <div class="space-y-3">
      @foreach ($this->groupedNotificationTypes as $groupKey => $groupData)
        <div
          wire:key="global-group-{{ $groupKey }}"
          class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-gray-700"
        >
          {{-- Group Header --}}
          <div class="mb-3 border-b border-gray-200 pb-2 dark:border-gray-600">
            <h4
              class="text-xs font-semibold tracking-wide text-gray-600 uppercase dark:text-gray-300"
            >
              {{ $groupData['label'] }}
            </h4>
            <p class="mt-0.5 text-xs text-gray-400 dark:text-gray-500">
              {{ $groupData['description'] }}
            </p>
          </div>

          {{-- Group Notification Types --}}
          <div class="space-y-2">
            @foreach ($groupData['types'] as $type)
              <div
                wire:key="global-toggle-{{ $type->value }}"
                class="flex items-center justify-between py-1"
              >
                <label
                  class="{{ ! $globalNotificationsEnabled ? 'text-gray-400 dark:text-gray-500' : 'text-gray-600 dark:text-gray-300' }} inline-flex items-center gap-1 text-sm font-medium"
                >
                  {{ $type->label() }}
                  <x-tooltip>
                    <x-slot name="text">
                      {{ $type->description() }}
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
                <x-toggle-switch
                  :id="'toggle-' . $type->value"
                  wire:model.change="notificationStates.{{ $type->value }}"
                  :checked="(bool) ($notificationStates[$type->value] ?? false)"
                  :disabled="! $globalNotificationsEnabled"
                />
              </div>
            @endforeach
          </div>
        </div>
      @endforeach
    </div>
  </div>
</div>
