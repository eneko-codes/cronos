{{-- User Sidebar (Notifications & Preferences) --}}
<div>
  @if ($isOpen)
    {{-- Backdrop --}}
    <div
      wire:key="sidebar-backdrop"
      wire:click="closeSidebar"
      @keydown.escape.window="$wire.closeSidebar()"
      class="fixed inset-0 z-40 bg-gray-500/75 backdrop-blur-sm transition-opacity duration-300 dark:bg-gray-900/80"
    ></div>

    {{-- Sidebar Panel --}}
    <div
      wire:key="sidebar-content"
      class="fixed top-0 right-0 z-50 flex h-full w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-xl transition-transform dark:border-gray-800 dark:bg-gray-900"
    >
      {{-- Header --}}
      <div
        class="flex h-12 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-700"
      >
        {{-- Title --}}
        <div class="flex items-center gap-2">
          <svg
            viewBox="0 0 24 25"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            class="size-6 rounded-full bg-gray-100 p-1 text-gray-500 dark:bg-gray-700 dark:text-gray-400"
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
          <h2 class="text-base font-semibold text-gray-900 dark:text-gray-100">
            Your notifications
          </h2>
        </div>

        {{-- Close Button --}}
        <div class="flex items-center gap-2">
          <x-button
            wire:click="closeSidebar"
            type="button"
            size="sm"
            variant="default"
            aria-label="Close sidebar"
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
          </x-button>
        </div>
      </div>

      {{-- Content --}}
      <div class="flex flex-1 flex-col gap-4 overflow-y-auto p-4">
        {{-- Tabs: Notifications / Preferences --}}
        <x-tabs
          :active="$activeTab"
          :filters="[
            'notifications' => 'Notifications',
            'preferences' => 'Preferences',
          ]"
          onFilterChange="changeTab"
          :showCounts="false"
        />

        {{-- Notifications Tab Content --}}
        <div
          wire:key="notifications-tab"
          x-show="$wire.activeTab === 'notifications'"
        >
          <livewire:sidebar.notifications-list
            :userId="$userId"
            wire:key="notifications-list-{{ $userId }}"
          />
        </div>

        {{-- Preferences Tab Content --}}
        <div
          wire:key="preferences-tab"
          x-show="$wire.activeTab === 'preferences'"
        >
          {{-- Primary Email Section --}}
          <div
            class="mb-4 flex flex-col space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
          >
            <livewire:settings.manage-primary-email
              wire:key="primary-email-{{ $userId }}"
            />
          </div>

          {{-- Notification Settings Section --}}
          <div
            class="flex flex-col space-y-4 rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-800"
          >
            <livewire:settings.manage-notification-preferences
              :userId="$userId"
              wire:key="notification-preferences-{{ $userId }}"
            />
          </div>
        </div>
      </div>
    </div>
  @endif

  {{-- Notification Details Modal (always available for event dispatch) --}}
  <livewire:notifications.notification-details-modal />
</div>
