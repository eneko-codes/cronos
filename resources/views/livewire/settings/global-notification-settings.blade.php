<div
  class="flex flex-col gap-4 rounded-xl border border-gray-200 bg-white p-6 shadow-sm dark:border-gray-700 dark:bg-gray-800"
>
  <div class="flex flex-col items-start gap-1">
    <div class="inline-flex flex-row items-center gap-2">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        class="size-5 text-gray-500 dark:text-gray-400"
        viewBox="0 0 16 16"
      >
        <path
          d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2m.995-14.901a1 1 0 1 0-1.99 0A5 5 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901"
        />
      </svg>
      <h2 class="text-lg font-bold text-gray-900 dark:text-gray-100">
        Notification Settings
      </h2>
    </div>
    <p class="text-sm text-gray-600 dark:text-gray-400">
      Control system-wide notification settings. These settings apply to all
      users.
    </p>
  </div>

  <div class="space-y-4">
    {{-- Master Global Toggle --}}
    <div
      class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-700/50"
    >
      <label
        class="inline-flex items-center gap-1 text-sm font-medium text-gray-900 dark:text-gray-100"
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

    {{-- Notification Channel Selector (Admin and Maintenance) --}}
    @if (auth()->user()?->isAdmin() ||auth()->user()?->isMaintenance())
      <div
        class="flex flex-col gap-3 rounded-lg bg-gray-50 p-3 md:flex-row md:items-center md:justify-between md:gap-0 dark:bg-gray-700/50"
      >
        <label
          for="notificationChannelSelect"
          class="inline-flex flex-row items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-300"
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
        <x-select
          id="notificationChannelSelect"
          wire:model.change="notificationChannel"
          class="w-full md:w-48 md:flex-shrink-0"
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
        </x-select>
      </div>

      {{-- Notification Retention Period --}}
      <div
        class="flex flex-col gap-3 rounded-lg bg-gray-50 p-3 md:flex-row md:items-center md:justify-between md:gap-0 dark:bg-gray-700/50"
      >
        <label
          for="notificationRetentionSelect"
          class="flex flex-row items-center gap-1 text-sm font-medium text-gray-700 dark:text-gray-300"
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
        <x-select
          id="notificationRetentionSelect"
          wire:model.change="notificationRetentionPeriod"
          class="w-full md:w-48 md:flex-shrink-0"
        >
          @foreach ($this->notificationRetentionOptions as $days => $label)
            <option
              value="{{ $days }}"
              @selected($notificationRetentionPeriod == $days)
            >
              {{ $label }}
            </option>
          @endforeach
        </x-select>
      </div>
    @endif

    {{-- Tab Navigation --}}
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="-mb-px flex gap-6" aria-label="Notification categories">
        <button
          type="button"
          wire:click="switchTab('personal')"
          class="{{ $activeTab === 'personal' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200' }} border-b-2 px-1 py-2 text-sm font-medium whitespace-nowrap transition-colors"
        >
          Personal
        </button>
        <button
          type="button"
          wire:click="switchTab('maintenance')"
          class="{{ $activeTab === 'maintenance' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200' }} border-b-2 px-1 py-2 text-sm font-medium whitespace-nowrap transition-colors"
        >
          Maintenance
        </button>
        <button
          type="button"
          wire:click="switchTab('admin')"
          class="{{ $activeTab === 'admin' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200' }} border-b-2 px-1 py-2 text-sm font-medium whitespace-nowrap transition-colors"
        >
          Admin
        </button>
      </nav>
    </div>

    {{-- Tab Content --}}
    <div class="space-y-3">
      @foreach ($this->groupedNotificationTypes as $groupKey => $groupData)
        @if ($groupKey === $activeTab)
          <div
            wire:key="global-group-{{ $groupKey }}"
            class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-700/50"
          >
            {{-- Group Description --}}
            <div class="mb-4">
              <p class="text-sm text-gray-600 dark:text-gray-400">
                {{ $groupData['description'] }}
              </p>
            </div>

            {{-- Group Notification Types --}}
            <div class="space-y-3">
              @foreach ($groupData['types'] as $type)
                <div
                  wire:key="global-toggle-{{ $type->value }}"
                  class="flex items-center justify-between rounded-lg bg-white p-3 dark:bg-gray-800"
                >
                  <label
                    class="{{ ! $globalNotificationsEnabled ? 'text-gray-400 dark:text-gray-500' : 'text-gray-900 dark:text-gray-100' }} inline-flex items-center gap-1 text-sm font-medium"
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
        @endif
      @endforeach
    </div>
  </div>
</div>
