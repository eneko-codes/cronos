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
      class="fixed right-0 top-0 z-50 flex h-full w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-xl transition-transform dark:border-gray-800 dark:bg-gray-900"
    >
      <!-- Header -->
      <div
        class="flex h-12 items-center justify-between border-b border-gray-100 px-4 dark:border-gray-800"
      >
        <!-- Title -->
        <div class="flex items-center gap-2">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            fill="none"
            viewBox="0 0 24 24"
            stroke-width="1.5"
            stroke="currentColor"
            class="size-6 rounded-full bg-gray-100 p-1 text-gray-500 dark:bg-gray-800 dark:text-gray-400"
          >
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
            />
            <path
              stroke-linecap="round"
              stroke-linejoin="round"
              d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
            />
          </svg>
          <h2 class="text-md font-semibold text-gray-900 dark:text-white">
            Settings
          </h2>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <button
            wire:click="$set('isOpen', false)"
            class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
          >
            <svg
              class="h-5 w-5"
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
        <div class="mb-4">
          <x-tabs
            :active="$activeTab"
            :filters="[
              'notifications' => 'Notifications',
              'settings' => 'Settings',
            ]"
            onFilterChange="changeTab"
            :showCounts="false"
            class="w-full"
          />
        </div>

        {{-- Notifications Tab Content --}}
        <div
          wire:key="notifications-tab"
          x-show="$wire.activeTab === 'notifications'"
        >
          <div class="space-y-4">
            {{-- Bulk Actions --}}
            <div class="flex justify-end gap-2">
              <x-button
                wire:click="markAllAsRead"
                size="xs"
                variant="outline"
                :disabled="! $this->notifications->contains(fn ($n) => $n->unread())"
              >
                Mark All Read
              </x-button>
              <x-button
                wire:click="deleteAllNotifications"
                size="xs"
                variant="danger-outline"
                wire:confirm="Are you sure you want to delete ALL notifications? This cannot be undone."
                :disabled="! $this->notifications->total() > 0"
              >
                Delete All
              </x-button>
            </div>

            {{-- Notification List --}}
            @if ($this->notifications->total() > 0)
              <ul
                class="divide-y divide-gray-200 dark:divide-gray-700"
                wire:key="notification-list"
              >
                @foreach ($this->notifications as $notification)
                  <li
                    wire:key="notification-{{ $notification->id }}"
                    class="{{ $notification->read_at ? 'opacity-70' : '' }} relative py-3"
                  >
                    {{-- Unread Indicator --}}
                    @if ($notification->unread())
                      <span
                        class="absolute left-0 top-1/2 -translate-x-3 -translate-y-1/2 transform"
                        title="Unread"
                      >
                        <span
                          class="block size-1.5 rounded-full bg-blue-500"
                        ></span>
                      </span>
                    @endif

                    <div class="flex items-start justify-between">
                      <div class="flex-1 space-y-1">
                        {{-- Notification Content (Adjust based on your data structure) --}}
                        <p
                          class="text-sm font-medium text-gray-900 dark:text-white"
                        >
                          {{-- Try to get a message, fallback to type --}}
                          {{ $notification->data['message'] ?? Str::headline(Str::snake($notification->type)) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                          {{ $notification->created_at->diffForHumans() }}
                        </p>
                      </div>
                      <div class="ml-2 flex flex-shrink-0 gap-1">
                        @if ($notification->unread())
                          <x-tooltip text="Mark as Read">
                            <button
                              wire:click="markAsRead('{{ $notification->id }}')"
                              class="inline-flex items-center rounded p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-500 dark:hover:bg-gray-800 dark:hover:text-gray-300"
                            >
                              <svg
                                xmlns="http://www.w3.org/2000/svg"
                                viewBox="0 0 16 16"
                                fill="currentColor"
                                class="size-4"
                              >
                                <path
                                  fill-rule="evenodd"
                                  d="M1 8a7 7 0 1 1 14 0A7 7 0 0 1 1 8Zm10.744-2.023a.75.75 0 0 0-1.06-.018L6.999 9.16l-1.69-1.78a.75.75 0 0 0-1.118 1.004l2.25 2.375a.75.75 0 0 0 1.086.017l3.75-4a.75.75 0 0 0-.018-1.06Z"
                                  clip-rule="evenodd"
                                />
                              </svg>
                            </button>
                          </x-tooltip>
                        @endif

                        <x-tooltip text="Delete">
                          <button
                            wire:click="deleteNotification('{{ $notification->id }}')"
                            class="inline-flex items-center rounded p-1 text-gray-400 hover:bg-red-50 hover:text-red-600 dark:hover:bg-red-900/50 dark:hover:text-red-400"
                          >
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              viewBox="0 0 16 16"
                              fill="currentColor"
                              class="size-4"
                            >
                              <path
                                fill-rule="evenodd"
                                d="M5 3.25V4H2.75a.75.75 0 0 0 0 1.5h.3l.815 8.15A1.5 1.5 0 0 0 5.357 15h5.285a1.5 1.5 0 0 0 1.493-1.35l.815-8.15h.3a.75.75 0 0 0 0-1.5H11v-.75A2.25 2.25 0 0 0 8.75 1h-1.5A2.25 2.25 0 0 0 5 3.25Zm2.25-.75a.75.75 0 0 0-.75.75V4h3v-.75a.75.75 0 0 0-.75-.75h-1.5ZM6.05 6a.75.75 0 0 1 .787.713l.275 5.5a.75.75 0 0 1-1.498.075l-.275-5.5A.75.75 0 0 1 6.05 6Zm3.9 0a.75.75 0 0 1 .712.787l-.275 5.5a.75.75 0 0 1-1.498-.075l.275-5.5a.75.75 0 0 1 .786-.711Z"
                                clip-rule="evenodd"
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
              class="{{ ! $isGloballyEnabled ? 'opacity-50' : '' }} flex items-center justify-between rounded-md bg-gray-50 p-3 dark:bg-gray-700"
            >
              <div class="flex items-center gap-3">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="{{ ! $isGloballyEnabled ? 'text-gray-400 dark:text-gray-600' : 'text-gray-600 dark:text-gray-400' }} size-5"
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
                    class="{{ ! $isGloballyEnabled ? 'text-gray-400 dark:text-gray-600' : 'text-sm font-medium text-gray-900 dark:text-white' }}"
                  >
                    Mute All Personal Notifications
                  </p>
                  <p
                    class="{{ ! $isGloballyEnabled ? 'text-gray-400 dark:text-gray-600' : 'text-gray-500 dark:text-gray-400' }} text-xs"
                  >
                    {{ $muteAll ? 'Currently muted' : 'Currently active' }}
                  </p>
                </div>
              </div>
              <label
                class="{{ ! $isGloballyEnabled ? 'cursor-not-allowed' : 'cursor-pointer' }} relative inline-flex items-center"
              >
                <input
                  type="checkbox"
                  class="peer sr-only"
                  wire:model.change="muteAll"
                  @disabled(! $isGloballyEnabled)
                />
                <div
                  class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-disabled:opacity-50 dark:bg-gray-700"
                ></div>
              </label>
            </div>

            {{-- Individual Preference Toggles --}}
            <div
              class="{{ ! $isGloballyEnabled ? 'opacity-50' : '' }} ml-2 space-y-3"
            >
              @foreach ($this->preferenceKeys as $key => $label)
                <div
                  wire:key="preference-toggle-{{ $key }}"
                  class="@if (!$isGloballyEnabled || $muteAll) opacity-50 @endif flex items-center justify-between"
                >
                  <label
                    class="{{ ! $isGloballyEnabled || $muteAll ? 'text-gray-400 dark:text-gray-600' : 'text-gray-900 dark:text-white' }} text-sm font-medium"
                  >
                    {{ $label }}
                  </label>
                  <label
                    class="{{ ! $isGloballyEnabled || $muteAll ? 'cursor-not-allowed' : 'cursor-pointer' }} relative inline-flex items-center"
                  >
                    <input
                      type="checkbox"
                      class="peer sr-only"
                      wire:model.change="individualPreferences.{{ $key }}"
                      @disabled(! $isGloballyEnabled || $muteAll)
                    />
                    <div
                      class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white peer-disabled:opacity-50 dark:bg-gray-700"
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
</div>
