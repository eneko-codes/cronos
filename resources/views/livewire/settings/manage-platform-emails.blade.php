<div class="flex flex-col gap-4">
  {{-- Header --}}
  <div>
    <p class="text-sm text-gray-600 dark:text-gray-400">
      External platform identities used for data synchronization.
    </p>
  </div>

  {{-- Platform List --}}
  <div class="space-y-2">
    @foreach ($this->platformLinks as $link)
      <div
        @class([
          'rounded-lg border p-3 transition-colors',
          'border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/30' =>
            $link['isConnected'],
          'border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/30' =>
            $link['isManualLink'],
          'border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-gray-800' =>
            ! $link['isConnected'] && ! $link['isManualLink'],
        ])
      >
        @if ($editingPlatform === $link['platform'])
          {{-- Edit Form --}}
          <div class="space-y-3">
            <div class="flex items-center justify-between">
              <h4
                class="text-sm font-semibold text-gray-900 dark:text-gray-100"
              >
                Edit {{ $link['label'] }} Link
              </h4>
              <button
                wire:click="cancelEditing"
                type="button"
                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
                aria-label="Cancel"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 16 16"
                  fill="currentColor"
                  class="size-4"
                >
                  <path
                    d="M5.28 4.22a.75.75 0 0 0-1.06 1.06L6.94 8l-2.72 2.72a.75.75 0 1 0 1.06 1.06L8 9.06l2.72 2.72a.75.75 0 1 0 1.06-1.06L9.06 8l2.72-2.72a.75.75 0 0 0-1.06-1.06L8 6.94 5.28 4.22Z"
                  />
                </svg>
              </button>
            </div>

            <div class="flex flex-col gap-1.5">
              <label
                for="external-email-{{ $link['platform'] }}"
                class="text-sm font-medium text-gray-700 dark:text-gray-300"
              >
                External Email *
              </label>
              <x-input
                wire:model="externalEmail"
                type="email"
                id="external-email-{{ $link['platform'] }}"
                placeholder="user@example.com"
              />
              @error('externalEmail')
                <p class="text-xs text-red-600 dark:text-red-400">
                  {{ $message }}
                </p>
              @enderror
            </div>

            <div class="flex flex-col gap-1.5">
              <label
                for="external-id-{{ $link['platform'] }}"
                class="text-sm font-medium text-gray-700 dark:text-gray-300"
              >
                External ID (Optional)
              </label>
              <x-input
                wire:model="externalId"
                type="text"
                id="external-id-{{ $link['platform'] }}"
                placeholder="123456"
              />
              @error('externalId')
                <p class="text-xs text-red-600 dark:text-red-400">
                  {{ $message }}
                </p>
              @enderror
            </div>

            <div class="flex gap-2">
              <x-button
                type="button"
                wire:click="saveLink"
                size="sm"
                variant="primary"
              >
                <span wire:loading.remove wire:target="saveLink">
                  Save Link
                </span>
                <span
                  wire:loading
                  wire:target="saveLink"
                  class="flex items-center gap-1"
                >
                  <x-spinner size="4" />
                </span>
              </x-button>
              <x-button
                type="button"
                wire:click="cancelEditing"
                size="sm"
                variant="secondary"
              >
                Cancel
              </x-button>
            </div>
          </div>
        @else
          {{-- Display Mode --}}
          <div class="flex items-center justify-between gap-3">
            {{-- Platform Info --}}
            <div class="flex items-center gap-3">
              {{-- Platform Details --}}
              <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                  <span
                    class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                  >
                    {{ $link['label'] }}
                  </span>
                  @if ($link['isConnected'])
                    <x-badge variant="success" size="sm">Connected</x-badge>
                  @elseif ($link['isManualLink'])
                    <x-badge variant="info" size="sm">Manual</x-badge>
                  @else
                    <x-badge variant="default" size="sm">Not Linked</x-badge>
                  @endif
                </div>

                @if ($link['externalEmail'] || $link['externalId'])
                  <div class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                    @if ($link['externalEmail'])
                      <span>
                        {{ $link['externalEmail'] }}
                      </span>
                    @endif

                    @if ($link['externalId'] && ! $link['isManualLink'])
                      <span class="ml-2 text-gray-400 dark:text-gray-500">
                        ID: {{ $link['externalId'] }}
                      </span>
                    @endif
                  </div>
                @else
                  <div
                    class="mt-0.5 text-xs text-gray-400 italic dark:text-gray-500"
                  >
                    No link established
                  </div>
                @endif
              </div>
            </div>

            {{-- Action Buttons (Admin/Maintenance) --}}
            @if (auth()->user()?->isAdmin() ||auth()->user()?->isMaintenance())
              <div class="flex gap-2">
                {{-- Edit/Create Button --}}
                @if (! $link['isConnected'])
                  <x-button
                    type="button"
                    wire:click="startEditing('{{ $link['platform'] }}', '{{ $link['externalEmail'] }}', '{{ $link['externalId'] }}')"
                    size="sm"
                    variant="secondary"
                    title="{{ $link['isManualLink'] ? 'Edit link' : 'Create link' }}"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 16 16"
                      fill="currentColor"
                      class="size-4"
                    >
                      @if ($link['isManualLink'])
                        {{-- Edit icon --}}
                        <path
                          d="M13.488 2.513a1.75 1.75 0 0 0-2.475 0L6.75 6.774a2.75 2.75 0 0 0-.596.892l-.848 2.047a.75.75 0 0 0 .98.98l2.047-.848a2.75 2.75 0 0 0 .892-.596l4.261-4.262a1.75 1.75 0 0 0 0-2.474Z"
                        />
                        <path
                          d="M4.75 3.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h6.5c.69 0 1.25-.56 1.25-1.25V9A.75.75 0 0 1 14 9v2.25A2.75 2.75 0 0 1 11.25 14h-6.5A2.75 2.75 0 0 1 2 11.25v-6.5A2.75 2.75 0 0 1 4.75 2H7a.75.75 0 0 1 0 1.5H4.75Z"
                        />
                      @else
                        {{-- Plus icon --}}
                        <path
                          d="M8.75 3.75a.75.75 0 0 0-1.5 0v3.5h-3.5a.75.75 0 0 0 0 1.5h3.5v3.5a.75.75 0 0 0 1.5 0v-3.5h3.5a.75.75 0 0 0 0-1.5h-3.5v-3.5Z"
                        />
                      @endif
                    </svg>
                  </x-button>
                @endif

                {{-- Delete Button (for manual links only) --}}
                @if ($link['isManualLink'] && $link['identityId'])
                  <x-button
                    type="button"
                    wire:click="deleteLink({{ $link['identityId'] }})"
                    wire:confirm="Are you sure you want to delete this manual platform link?"
                    size="sm"
                    variant="alert"
                    title="Delete manual link"
                  >
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 16 16"
                      fill="currentColor"
                      class="size-4"
                      wire:loading.remove
                      wire:target="deleteLink({{ $link['identityId'] }})"
                    >
                      <path
                        fill-rule="evenodd"
                        d="M5 3.25V4H2.75a.75.75 0 0 0 0 1.5h.3l.815 8.15A1.5 1.5 0 0 0 5.357 15h5.285a1.5 1.5 0 0 0 1.493-1.35l.815-8.15h.3a.75.75 0 0 0 0-1.5H11v-.75A2.25 2.25 0 0 0 8.75 1h-1.5A2.25 2.25 0 0 0 5 3.25Zm2.25-.75a.75.75 0 0 0-.75.75V4h3v-.75a.75.75 0 0 0-.75-.75h-1.5ZM6.05 6a.75.75 0 0 1 .787.713l.275 5.5a.75.75 0 0 1-1.498.075l-.275-5.5A.75.75 0 0 1 6.05 6Zm3.9 0a.75.75 0 0 1 .712.787l-.275 5.5a.75.75 0 0 1-1.498-.075l.275-5.5a.75.75 0 0 1 .786-.711Z"
                        clip-rule="evenodd"
                      />
                    </svg>
                    <span
                      wire:loading
                      wire:target="deleteLink({{ $link['identityId'] }})"
                      class="flex items-center gap-1"
                    >
                      <x-spinner size="4" />
                    </span>
                  </x-button>
                @endif
              </div>
            @endif
          </div>
        @endif
      </div>
    @endforeach
  </div>
</div>
