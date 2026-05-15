<div class="space-y-4">
  {{-- Global Notification Status Indicator --}}
  @unless ($isGloballyEnabled)
    <x-alert wire:key="global-disabled-msg-notifications" variant="warning">
      <x-slot:title>
        Notifications Globally Disabled
      </x-slot>
      All user notifications are currently turned off by an administrator.
    </x-alert>
  @elseif ($userNotificationsMuted)
    {{-- User Notifications Status Indicator --}}
    <x-alert wire:key="user-muted-msg-notifications" variant="info">
      <x-slot:title>
        Personal Notifications Disabled
      </x-slot>
      You have currently disabled all your personal notifications.
    </x-alert>
  @else
    {{-- Only show full notification UI if notifications are enabled --}}

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
        :disabled="$this->notifications->total() === 0"
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
                border-blue-200
                bg-blue-50
                dark:border-blue-800
                dark:bg-blue-900/20
                hover:bg-blue-100
                dark:hover:bg-blue-900/30
            @else
                border-gray-200
                bg-gray-50
                dark:border-gray-700
                dark:bg-gray-800/50
                opacity-75
                hover:bg-gray-100
                dark:hover:bg-gray-700/50
            @endif relative cursor-pointer rounded-lg border p-3 transition duration-150 ease-in-out"
          >
            <div class="flex items-start justify-between">
              <div class="flex-1 space-y-1">
                {{-- Notification Title and Badge --}}
                <div class="flex items-center gap-2">
                  @php
                    $level = $notification->data["level"] ?? "info";
                    $badgeVariant = match ($level) {
                        "success" => "success",
                        "warning" => "warning",
                        "error" => "alert",
                        default => "info",
                    };
                  @endphp

                  <x-badge :variant="$badgeVariant" size="sm">
                    {{ Str::ucfirst($level) }}
                  </x-badge>
                  <p
                    class="text-sm font-medium text-gray-900 dark:text-gray-100"
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
                <x-tooltip text="Delete">
                  <x-button
                    wire:click.stop="deleteNotification('{{ $notification->id }}')"
                    type="button"
                    size="xs"
                    variant="alert"
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
                  </x-button>
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

        <p class="mt-2 text-sm font-medium text-gray-700 dark:text-gray-300">
          No notifications
        </p>
        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
          You're all caught up!
        </p>
      </div>
    @endif
  @endunless
</div>
