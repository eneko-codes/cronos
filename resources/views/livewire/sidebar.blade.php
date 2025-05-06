<!-- Settings sidebar -->
<div>
  @if ($isOpen)
    <!-- Backdrop -->
    <div
      wire:key="sidebar-backdrop"
      wire:click="$set('isOpen', false)"
      @keydown.escape.window="$wire.set('isOpen', false)"
      class="fixed inset-0 z-40 bg-gray-500/75 backdrop-blur-sm transition-opacity duration-300 dark:bg-gray-900/80"
    ></div>

    <!-- Sidebar -->
    <div
      wire:key="sidebar-content"
      class="fixed top-0 right-0 z-50 flex h-full w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-xl transition-transform dark:border-gray-800 dark:bg-gray-900"
    >
      <!-- Header -->
      <div
        class="flex h-12 items-center justify-between border-b border-gray-100 px-4 dark:border-gray-800"
      >
        <!-- Title -->
        <div class="flex items-center gap-2">
          <svg
            viewBox="0 0 24 25"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            class="size-6 rounded-full bg-gray-100 p-1 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
          >
            <path
              d="M12.7516 3.00098C12.7516 2.58676 12.4158 2.25098 12.0016 2.25098C11.5874 2.25098 11.2516 2.58676 11.2516 3.00098V3.78801C7.46161 4.1643 4.5016 7.36197 4.5016 11.251V14.365L3.80936 16.2109C3.25776 17.6819 4.34514 19.251 5.9161 19.251H18.0871C19.658 19.2509 20.7454 17.6819 20.1938 16.2109L19.5016 14.365V11.251C19.5016 7.36197 16.5416 4.1643 12.7516 3.78801V3.00098Z"
              fill="currentColor"
            />
            <path
              d="M14.8735 20.251H9.1261C9.55865 21.418 10.6823 22.2495 11.9998 22.2495C13.3173 22.2495 14.441 21.418 14.8735 20.251Z"
              fill="currentColor"
            />
          </svg>
          <h2 class="text-md font-semibold text-gray-900 dark:text-white">
            Your notifications
          </h2>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <button
            wire:click="$set('isOpen', false)"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
          >
            <svg
              class="size-5"
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 20 20"
              fill="currentColor"
            >
              <path
                d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"
              />
            </svg>
          </button>
        </div>
      </div>

      <!-- Content -->
      <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
        {{-- Tabs --}}
        <x-tabs
          :active="$activeTab"
          :filters="[
              'notifications' => 'Notifications',
              'settings' => 'Settings',
          ]"
          onFilterChange="changeTab"
          :showCounts="false"
        />

        {{-- Notifications Tab Content --}}
        <div
          wire:key="notifications-tab"
          x-show="$wire.activeTab === 'notifications'"
        >
          <div class="space-y-4">
            {{-- Global Notification Status Indicator --}}
            @unless ($isGloballyEnabled)
              <div
                wire:key="global-disabled-msg-notifications"
                class="rounded-md border border-orange-300 bg-orange-50 p-3 text-xs text-orange-800 dark:border-orange-600 dark:bg-orange-900/30 dark:text-orange-200"
              >
                <p class="font-medium">Notifications Globally Disabled</p>
                <p>
                  All user email notifications are currently turned off by an
                  administrator.
                </p>
              </div>
            @else
              {{-- User Mute Status Indicator --}}
              @if ($muteAll)
                <div
                  wire:key="user-muted-msg-notifications"
                  class="rounded-md border border-blue-300 bg-blue-50 p-3 text-xs text-blue-800 dark:border-blue-600 dark:bg-blue-900/30 dark:text-blue-200"
                >
                  <p class="font-medium">Personal Notifications Muted</p>
                  <p>
                    You have currently muted all your personal email
                    notifications.
                  </p>
                </div>
              @endif
            @endunless

            {{-- Notification Filters --}}
            <div class="pb-2">
              <x-tabs
                wire:key="notification-filter-tabs"
                :active="$notificationFilter"
                :filters="[
                    'all' => 'All',
                    'unread' => 'Unread',
                    'read' => 'Read',
                ]"
                :counts="$this->notificationCounts"
                onFilterChange="setNotificationFilter"
                variant="underline"
              />
            </div>

            {{-- Bulk Actions --}}
            <div class="flex justify-end gap-2">
              <x-button
                wire:click="markAllAsRead"
                size="xs"
                variant="info"
                :disabled="! $this->notifications->contains(fn ($n) => $n->unread())"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="currentColor"
                  class="size-3"
                  viewBox="0 0 16 16"
                >
                  <path
                    d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"
                  />
                  <path
                    d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"
                  />
                </svg>
                Mark All Read
              </x-button>
              <x-button
                wire:click="deleteAllNotifications"
                size="xs"
                variant="alert"
                wire:confirm="Are you sure you want to delete ALL notifications? This cannot be undone."
                :disabled="! $this->notifications->total() > 0"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="currentColor"
                  class="size-3"
                  viewBox="0 0 16 16"
                >
                  <path
                    d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5m-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5M4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06m6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528M8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5"
                  />
                </svg>
                Delete All
              </x-button>
            </div>

            {{-- Notification List --}}
            @if ($this->notifications->total() > 0)
              <ul class="space-y-2" wire:key="notification-list">
                @foreach ($this->notifications as $notification)
                  <li
                    wire:key="notification-{{ $notification->id }}"
                    wire:click="showNotificationDetails('{{ $notification->id }}')"
                    class="@if ($notification->unread())
                        bg-gray-100
                        dark:bg-gray-700/60
                        hover:bg-gray-200
                        dark:hover:bg-gray-700/80
                    @else
                        bg-gray-50
                        dark:bg-gray-800/50
                        opacity-70
                        hover:bg-gray-100
                        dark:hover:bg-gray-700/50
                    @endif relative cursor-pointer rounded-md p-3 shadow transition duration-150 ease-in-out"
                  >
                    <div class="flex items-start justify-between">
                      <div class="flex-1 space-y-1">
                        {{-- Notification Title and Badge --}}
                        <div class="flex items-center gap-2">
                          <p
                            class="text-sm font-medium text-gray-900 dark:text-white"
                          >
                            {{-- Show subject or limited message as fallback --}}
                            {{ $notification->data["subject"] ?? Str::limit($notification->data["message"] ?? "", 50) }}
                          </p>
                        </div>

                        {{-- Timestamp --}}
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                          {{ $notification->created_at->diffForHumans() }}
                        </p>
                      </div>
                      <div class="ml-2 flex flex-shrink-0 gap-1">
                        @if ($notification->unread())
                          <x-tooltip text="Mark as Read">
                            <button
                              wire:click.stop="markAsRead('{{ $notification->id }}')"
                              class="inline-flex items-center rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                            >
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                fill="currentColor"
                                class="size-3"
                                viewBox="0 0 16 16"
                              >
                                <path
                                  d="M2.5 8a5.5 5.5 0 0 1 8.25-4.764.5.5 0 0 0 .5-.866A6.5 6.5 0 1 0 14.5 8a.5.5 0 0 0-1 0 5.5 5.5 0 1 1-11 0"
                                />
                                <path
                                  d="M15.354 3.354a.5.5 0 0 0-.708-.708L8 9.293 5.354 6.646a.5.5 0 1 0-.708.708l3 3a.5.5 0 0 0 .708 0z"
                                />
                              </svg>
                            </button>
                          </x-tooltip>
                        @endif

                        <x-tooltip text="Delete">
                          <button
                            wire:click.stop="deleteNotification('{{ $notification->id }}')"
                            class="inline-flex items-center rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/50 dark:hover:text-red-400"
                          >
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="currentColor"
                              class="size-3"
                              viewBox="0 0 16 16"
                            >
                              <path
                                d="M11 1.5v1h3.5a.5.5 0 0 1 0 1h-.538l-.853 10.66A2 2 0 0 1 11.115 16h-6.23a2 2 0 0 1-1.994-1.84L2.038 3.5H1.5a.5.5 0 0 1 0-1H5v-1A1.5 1.5 0 0 1 6.5 0h3A1.5 1.5 0 0 1 11 1.5m-5 0v1h4v-1a.5.5 0 0 0-.5-.5h-3a.5.5 0 0 0-.5.5M4.5 5.029l.5 8.5a.5.5 0 1 0 .998-.06l-.5-8.5a.5.5 0 1 0-.998.06m6.53-.528a.5.5 0 0 0-.528.47l-.5 8.5a.5.5 0 0 0 .998.058l.5-8.5a.5.5 0 0 0-.47-.528M8 4.5a.5.5 0 0 0-.5.5v8.5a.5.5 0 0 0 1 0V5a.5.5 0 0 0-.5-.5"
                              />
                            </svg>
                          </button>
                        </x-tooltip>
                      </div>
                    </div>
                  </li>
                @endforeach
              </ul>

              {{-- Pagination Links --}}
              <div class="mt-4">
                {{ $this->notifications->links() }}
              </div>
            @else
              <div
                class="flex flex-col items-center justify-center rounded-lg border border-dashed border-gray-300 p-6 text-center dark:border-gray-700"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="size-10 text-gray-400 dark:text-gray-500"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0M3.124 7.5A8.969 8.969 0 0 1 5.292 3m13.416 0a8.969 8.969 0 0 1 2.168 4.5"
                  />
                </svg>

                <p
                  class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300"
                >
                  No notifications
                </p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                  You're all caught up!
                </p>
              </div>
            @endif
          </div>
        </div>

        {{-- Settings Tab Content --}}
        <div wire:key="settings-tab" x-show="$wire.activeTab === 'settings'">
          <!-- Settings Content -->
          <div
            class="flex flex-col space-y-4 rounded-lg border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-800"
          >
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">
              Email Notification Settings
            </h3>

            {{-- Global Notification Status Indicator --}}
            @unless ($isGloballyEnabled)
              <div
                wire:key="global-disabled-msg"
                class="rounded-md border border-orange-300 bg-orange-50 p-3 text-xs text-orange-800 dark:border-orange-600 dark:bg-orange-900/30 dark:text-orange-200"
              >
                <p class="font-medium">Notifications Globally Disabled</p>
                <p>
                  All user email notifications are currently turned off by an
                  administrator.
                </p>
              </div>
            @endunless

            {{-- User Master Mute Toggle --}}
            <div
              class="{{ ! $isGloballyEnabled ? "opacity-50" : "" }} flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
            >
              <div class="flex items-center gap-3">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="{{ ! $isGloballyEnabled ? "text-gray-400 dark:text-gray-600" : "text-gray-600 dark:text-gray-400" }} size-5"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0"
                  />
                  @if ($muteAll)
                    <path
                      stroke-linecap="round"
                      stroke-linejoin="round"
                      d="M 4 4 L 20 20"
                    />
                  @endif
                </svg>
                <div>
                  <p
                    class="{{ ! $isGloballyEnabled ? "text-gray-400 dark:text-gray-600" : "text-sm font-medium text-gray-900 dark:text-white" }}"
                  >
                    Mute All Personal Notifications
                  </p>
                  <p
                    class="{{ ! $isGloballyEnabled ? "text-gray-400 dark:text-gray-600" : "text-gray-500 dark:text-gray-400" }} text-xs"
                  >
                    {{ $muteAll ? "Currently muted" : "Currently active" }}
                  </p>
                </div>
              </div>
              <label
                class="{{ ! $isGloballyEnabled ? "cursor-not-allowed" : "cursor-pointer" }} relative inline-flex items-center"
              >
                <input
                  type="checkbox"
                  class="peer sr-only"
                  wire:model.change="muteAll"
                  @disabled(! $isGloballyEnabled)
                />
                <div
                  class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-disabled:opacity-50 after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
                ></div>
              </label>
            </div>

            {{-- Individual Preference Toggles --}}
            <div class="ml-2 space-y-3">
              {{-- Loop through preference keys passed from component --}}
              @foreach ($this->preferenceKeys as $key => $label)
                {{-- Conditionally skip non-admin toggles for non-admins --}}
                @if (($key === "api_down_warning" || $key === "admin_promotion_email") && ! (Auth::user() && Auth::user()->is_admin))
                  @continue
                @endif

                {{-- Determine disabled state based on global master, user mute, and specific global toggle --}}
                @php
                  // Check if the specific notification type is globally disabled by an admin setting
                  // $isTypeGloballyDisabled = $isGloballyEnabled && isset($globalPreferenceStates[$key]) ? ! $globalPreferenceStates[$key] : false;
                  // Determine the final disabled state for the UI toggle
                  // $isDisabled = ! $isGloballyEnabled || $muteAll || $isTypeGloballyDisabled;

                  // Revised logic for clarity and correctness
                  $isSpecificTypeGloballyOff = isset($globalPreferenceStates[$key]) && $globalPreferenceStates[$key] === false;

                  if (! $isGloballyEnabled) {
                      // If master global is OFF, everything is effectively disabled regardless of specific type state or user mute
                      $isTypeGloballyDisabled = true; // Indicates a global reason for disablement
                      $isDisabled = true;
                  } elseif ($isSpecificTypeGloballyOff) {
                      // If master global is ON, but this specific type is globally OFF
                      $isTypeGloballyDisabled = true; // Indicates a global reason for disablement (type-specific)
                      $isDisabled = true;
                  } elseif ($muteAll) {
                      // If master global is ON, and specific type is globally ON (or not set, defaulting to ON), but user has muted all
                      $isTypeGloballyDisabled = false; // Not disabled due to a global admin setting
                      $isDisabled = true; // Disabled due to user's muteAll
                  } else {
                      // Master global ON, specific type ON (or default ON), user muteAll OFF
                      $isTypeGloballyDisabled = false;
                      $isDisabled = false;
                  }
                @endphp

                {{-- Row for the toggle --}}
                <div
                  wire:key="preference-toggle-{{ $key }}"
                  class="@if($isDisabled) opacity-50 @endif flex items-center justify-between"
                >
                  {{-- Label and Badges/Tooltips --}}
                  <span class="flex items-center">
                    <label
                      for="preference-{{ $key }}"
                      class="{{ $isDisabled ? "text-gray-400 dark:text-gray-600" : "text-gray-900 dark:text-white" }} text-sm font-medium"
                    >
                      {{ $label }}
                    </label>

                    {{-- Admin Only Badge --}}
                    @if ($key === "api_down_warning" || $key === "admin_promotion_email")
                      <x-badge variant="primary" size="sm" class="ml-1">
                        Admin
                      </x-badge>
                    @endif

                    {{-- Globally Disabled Warning Tooltip --}}
                    {{-- Show if the reason for disablement is global (either master OR type-specific) AND the specific type toggle is indeed off globally --}}
                    @if ($isTypeGloballyDisabled && $isSpecificTypeGloballyOff && $isGloballyEnabled)
                      <x-tooltip
                        text="This notification type is currently disabled by an administrator."
                      >
                        <svg
                          xmlns="http://www.w3.org/2000/svg"
                          viewBox="0 0 20 20"
                          fill="currentColor"
                          class="ml-1 size-4 text-yellow-500"
                        >
                          <path
                            fill-rule="evenodd"
                            d="M18 10a8 8 0 1 1-16 0 8 8 0 0 1 16 0Zm-7-4a1 1 0 1 1-2 0 1 1 0 0 1 2 0ZM9 9a.75.75 0 0 0 0 1.5h.253a.25.25 0 0 1 .244.304l-.459 2.066A1.75 1.75 0 0 0 10.747 15H11a.75.75 0 0 0 0-1.5h-.253a.25.25 0 0 1-.244-.304l.459-2.066A1.75 1.75 0 0 0 9.253 9H9Z"
                            clip-rule="evenodd"
                          />
                        </svg>
                      </x-tooltip>
                    @endif
                  </span>

                  {{-- Toggle Switch --}}
                  <label
                    class="{{ $isDisabled ? "cursor-not-allowed" : "cursor-pointer" }} relative inline-flex items-center"
                  >
                    <input
                      type="checkbox"
                      id="preference-{{ $key }}"
                      class="peer sr-only"
                      wire:model.change="individualPreferences.{{ $key }}"
                      @disabled($isDisabled)
                    />
                    <div
                      class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:top-[2px] after:left-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"
                    ></div>
                  </label>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- Include the Notification Details Modal --}}
  <livewire:notification-details-modal />
</div>
