<div>
  @if ($isOpen)
    <div
      wire:key="modal-backdrop-platform-sync"
      wire:click="closeModal"
      @keydown.escape.window="$wire.closeModal()"
      class="fixed inset-0 z-40 flex items-center justify-center bg-gray-500/75 p-2 backdrop-blur-sm transition-opacity duration-300 md:p-6 dark:bg-gray-900/75"
      role="dialog"
      aria-modal="true"
    >
      <div
        wire:key="modal-content-platform-sync"
        wire:click.stop
        class="relative z-50 mx-4 my-8 flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border-2 border-gray-200 bg-gray-100 shadow-lg transition-transform duration-300 dark:border-gray-700 dark:bg-gray-800"
      >
        {{-- Header --}}
        <div
          class="sticky top-0 z-20 flex items-start justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-3 backdrop-blur dark:border-gray-700 dark:bg-gray-800"
        >
          <div class="flex flex-col gap-1">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">
              Platform Sync Links
            </h2>
            <p class="text-xs text-gray-500 dark:text-gray-400">
              External platform identities used for data synchronization.
            </p>
          </div>
          <x-button
            wire:click="closeModal"
            type="button"
            size="sm"
            variant="default"
            aria-label="Close modal"
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

        {{-- Content --}}
        <div class="overflow-y-auto p-4">
          <livewire:settings.manage-platform-emails :userId="$userId" />
        </div>
      </div>
    </div>
  @endif
</div>
