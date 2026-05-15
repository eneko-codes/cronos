<div wire:poll.20s="updateCount">
  <x-tooltip text="Your notifications">
    <button
      wire:click="toggleSidebar"
      class="relative flex items-center justify-center text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200"
      aria-label="Toggle Settings Sidebar"
    >
      <svg
        viewBox="0 0 24 25"
        fill="none"
        xmlns="http://www.w3.org/2000/svg"
        class="size-6"
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

      @if ($unreadCount > 0)
        <span
          class="absolute -top-0.5 -right-0.5 flex size-2"
          title="{{ $unreadCount }} unread notification{{ $unreadCount > 1 ? 's' : '' }}"
        >
          <span
            class="absolute inline-flex size-full animate-ping rounded-full bg-blue-400 opacity-75 dark:bg-blue-500"
          ></span>
          <span
            class="relative inline-flex size-2 rounded-full bg-blue-500 dark:bg-blue-600"
          ></span>
        </span>
      @endif
    </button>
  </x-tooltip>
</div>
