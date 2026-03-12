<div class="flex flex-col space-y-4">
  {{-- Global Notification Status Indicator --}}
  @unless ($isGloballyEnabled)
    <x-alert wire:key="global-disabled-msg" variant="warning">
      <x-slot:title>
        Notifications Globally Disabled
      </x-slot>
      All user email notifications are currently turned off by an administrator.
    </x-alert>
  @endunless

  {{-- User Master Mute Toggle --}}
  <div
    @class([
      'flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-800',
      'opacity-50' => ! $isGloballyEnabled,
    ])
  >
    <div class="flex items-center gap-3">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
        @class([
          'size-5',
          'text-gray-600 dark:text-gray-400' => $isGloballyEnabled,
          'text-gray-400 dark:text-gray-600' => ! $isGloballyEnabled,
        ])
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
        />
        @if ($userNotificationsMuted)
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M 4 4 L 20 20"
          />
        @endif
      </svg>
      <div>
        <label
          @class([
            'inline-flex items-center gap-1 text-sm font-medium',
            'text-gray-900 dark:text-white' => $isGloballyEnabled,
            'text-gray-400 dark:text-gray-600' => ! $isGloballyEnabled,
          ])
        >
          Mute Personal Notifications
          <x-tooltip>
            <x-slot name="text">
              Personal switch for your email notifications. This allows you to
              control your own notification preferences independently of the
              global settings.
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
      </div>
    </div>
    <x-toggle-switch
      id="user-notifications-toggle-{{ $targetUserId ?? 'self' }}"
      wire:model.change="userNotificationsMuted"
      :checked="(bool) $userNotificationsMuted"
      :disabled="! $isGloballyEnabled"
    />
  </div>

  {{-- Tab Navigation (only show if multiple groups available) --}}
  @if (count($this->groupedPreferences) > 1)
    <div class="border-b border-gray-200 dark:border-gray-700">
      <nav class="-mb-px flex gap-6" aria-label="Notification categories">
        <button
          type="button"
          wire:click="switchTab('personal')"
          class="{{ $activeTab === 'personal' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200' }} border-b-2 px-1 py-2 text-sm font-medium whitespace-nowrap transition-colors"
        >
          Personal
        </button>
        @if (count(array_filter($this->groupedPreferences, fn ($g) => $g['group']->value === 'maintenance')) > 0)
          <button
            type="button"
            wire:click="switchTab('maintenance')"
            class="{{ $activeTab === 'maintenance' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200' }} border-b-2 px-1 py-2 text-sm font-medium whitespace-nowrap transition-colors"
          >
            Maintenance
          </button>
        @endif

        @if (count(array_filter($this->groupedPreferences, fn ($g) => $g['group']->value === 'admin')) > 0)
          <button
            type="button"
            wire:click="switchTab('admin')"
            class="{{ $activeTab === 'admin' ? 'border-blue-600 text-blue-600 dark:border-blue-400 dark:text-blue-400' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-900 dark:text-gray-400 dark:hover:border-gray-500 dark:hover:text-gray-200' }} border-b-2 px-1 py-2 text-sm font-medium whitespace-nowrap transition-colors"
          >
            Admin
          </button>
        @endif
      </nav>
    </div>
  @endif

  {{-- Notification Preferences Content --}}
  <div class="space-y-3">
    @foreach ($this->groupedPreferences as $groupKey => $groupData)
      {{-- Show content based on: single group OR matches active tab --}}
      @if (count($this->groupedPreferences) === 1 || $groupKey === $activeTab)
        <div
          wire:key="group-{{ $groupKey }}-{{ $targetUserId ?? 'self' }}"
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
            @foreach ($groupData['types'] as $key => $preference)
              <div
                wire:key="preference-toggle-{{ $key }}-{{ $targetUserId ?? 'self' }}"
                class="flex items-center justify-between rounded-lg bg-white p-3 dark:bg-gray-800"
              >
                <span class="flex items-center">
                  <label
                    for="preference-{{ $key }}-{{ $targetUserId ?? 'self' }}"
                    class="inline-flex items-center gap-1 text-sm font-medium text-gray-900 dark:text-gray-100"
                  >
                    {{ $preference['label'] }}
                    <x-tooltip>
                      <x-slot name="text">
                        {{ $preference['description'] }}
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
                </span>
                @if ($preference['isSpecificTypeGloballyOff'])
                  <x-tooltip
                    text="This notification type is currently disabled by an administrator."
                  >
                    <x-toggle-switch
                      :id="'preference-' . $key . '-' . ($targetUserId ?? 'self')"
                      wire:model.change="userNotificationStates.{{ $key }}"
                      :checked="(bool) ($userNotificationStates[$key] ?? false)"
                      :disabled="$preference['isDisabled']"
                    />
                  </x-tooltip>
                @else
                  <x-toggle-switch
                    :id="'preference-' . $key . '-' . ($targetUserId ?? 'self')"
                    wire:model.change="userNotificationStates.{{ $key }}"
                    :checked="(bool) ($userNotificationStates[$key] ?? false)"
                    :disabled="$preference['isDisabled']"
                  />
                @endif
              </div>
            @endforeach
          </div>
        </div>
      @endif
    @endforeach
  </div>
</div>
