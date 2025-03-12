<div>
    <x-tooltip text="System Alerts">
      <button
        wire:click="toggleAlertsSidebar"
        class="relative flex items-center justify-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
        aria-label="Toggle Alerts Sidebar"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          fill="none"
          viewBox="0 0 24 24"
          stroke-width="1.5"
          stroke="currentColor"
          class="size-6"
        >
          <path
            stroke-linecap="round"
            stroke-linejoin="round"
            d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
          />
        </svg>
        
        @if ($this->alertCount > 0)
          <span class="absolute -top-1 -right-1 flex h-5 w-5 items-center justify-center rounded-full bg-red-500 text-xs font-medium text-white">
            {{ $this->alertCount > 99 ? '99+' : $this->alertCount }}
          </span>
        @endif
      </button>
    </x-tooltip>
</div>
