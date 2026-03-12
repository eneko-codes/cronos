<!-- Header Section -->
<div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
  <div class="flex flex-col items-start gap-1 sm:flex-row sm:items-center">
    <!-- User Name -->
    <h1 class="text-xl font-bold">{{ $this->user->name }}</h1>
    <!-- User Badges -->
    <x-user-badges :user="$this->user" />
  </div>
  <!-- Platform Connections Button -->
  <div class="mt-2 flex justify-end sm:mt-0">
    <x-tooltip text="Manage Platform Connections">
      <x-button
        wire:click="$dispatch('openPlatformSyncLinksModal', { userId: {{ $this->user->id }} })"
        type="button"
        variant="default"
        size="sm"
        aria-label="Manage Platform Connections"
      >
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 20 20"
          fill="currentColor"
          class="size-4"
        >
          <path
            d="M12.232 4.232a2.5 2.5 0 0 1 3.536 3.536l-1.225 1.224a.75.75 0 0 0 1.061 1.06l1.224-1.224a4 4 0 0 0-5.656-5.656l-3 3a4 4 0 0 0 .225 5.865.75.75 0 0 0 .977-1.138 2.5 2.5 0 0 1-.142-3.667l3-3Z"
          />
          <path
            d="M11.603 7.963a.75.75 0 0 0-.977 1.138 2.5 2.5 0 0 1 .142 3.667l-3 3a2.5 2.5 0 0 1-3.536-3.536l1.225-1.224a.75.75 0 0 0-1.061-1.06l-1.224 1.224a4 4 0 1 0 5.656 5.656l3-3a4 4 0 0 0-.225-5.865Z"
          />
        </svg>
        Connections
      </x-button>
    </x-tooltip>
  </div>
</div>
