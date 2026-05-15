<div class="flex flex-col gap-4">
  {{-- Header --}}
  <p class="text-xs text-gray-500 dark:text-gray-400">
    This email is used for login and all notifications.
  </p>

  {{-- Current Email Display --}}
  <div
    class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-800"
  >
    @if ($isEditing)
      {{-- Edit Form --}}
      <form wire:submit="saveEmail" class="space-y-3">
        <div>
          <label
            for="newEmail"
            class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Email Address
          </label>
          <input
            type="email"
            id="newEmail"
            wire:model.blur="newEmail"
            placeholder="email@example.com"
            class="block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm transition-colors focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200"
            autofocus
            autocomplete="email"
          />
          @error('newEmail')
            <p class="mt-1 text-xs text-red-600 dark:text-red-400" role="alert">
              {{ $message }}
            </p>
          @enderror
        </div>

        <div class="flex items-center gap-2">
          <x-button
            type="submit"
            size="sm"
            variant="success"
            wire:loading.attr="disabled"
            wire:target="saveEmail"
          >
            <span wire:loading.remove wire:target="saveEmail">Save</span>
            <span
              wire:loading
              wire:target="saveEmail"
              class="flex items-center gap-1"
            >
              <x-spinner size="4" />
              <span class="sr-only">Saving email...</span>
            </span>
          </x-button>
          <x-button
            type="button"
            wire:click="cancelEditing"
            size="sm"
            variant="default"
            wire:loading.attr="disabled"
            wire:target="saveEmail"
          >
            Cancel
          </x-button>
        </div>
      </form>
    @else
      <div class="flex items-center justify-between gap-4">
        <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
          {{-- Email Icon --}}
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 20 20"
            fill="currentColor"
            class="size-4 shrink-0 text-gray-400 dark:text-gray-500"
            aria-hidden="true"
          >
            <path
              d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"
            />
            <path
              d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"
            />
          </svg>

          <span
            class="text-sm font-medium break-all text-gray-800 dark:text-gray-200"
          >
            {{ $this->targetUser?->email }}
          </span>

          {{-- Verification Status --}}
          @if ($this->targetUser?->hasVerifiedEmail())
            <x-badge variant="success" size="sm">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                viewBox="0 0 16 16"
                fill="currentColor"
                class="mr-0.5 size-3"
                aria-hidden="true"
              >
                <path
                  fill-rule="evenodd"
                  d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z"
                  clip-rule="evenodd"
                />
              </svg>
              Verified
            </x-badge>
          @else
            <x-badge variant="warning" size="sm">Unverified</x-badge>
          @endif
        </div>

        {{-- Actions --}}
        <div class="flex shrink-0 items-center gap-1">
          {{-- Edit Button --}}
          <x-button
            type="button"
            wire:click="startEditing"
            size="sm"
            variant="default"
            title="Edit email"
            aria-label="Edit email address"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 16 16"
              fill="currentColor"
              class="size-4"
              aria-hidden="true"
            >
              <path
                d="M13.488 2.513a1.75 1.75 0 0 0-2.475 0L6.75 6.774a2.75 2.75 0 0 0-.596.892l-.848 2.047a.75.75 0 0 0 .98.98l2.047-.848a2.75 2.75 0 0 0 .892-.596l4.261-4.262a1.75 1.75 0 0 0 0-2.474Z"
              />
              <path
                d="M4.75 3.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h6.5c.69 0 1.25-.56 1.25-1.25V9A.75.75 0 0 1 14 9v2.25A2.75 2.75 0 0 1 11.25 14h-6.5A2.75 2.75 0 0 1 2 11.25v-6.5A2.75 2.75 0 0 1 4.75 2H7a.75.75 0 0 1 0 1.5H4.75Z"
              />
            </svg>
            Edit
          </x-button>

          {{-- Send/Resend Verification Email Button (Only show if email is not verified) --}}
          @if (! $this->targetUser?->hasVerifiedEmail())
            <x-button
              type="button"
              wire:click="resendVerification"
              size="sm"
              variant="info"
              title="Resend verification email"
              aria-label="Resend verification email"
              wire:loading.attr="disabled"
              wire:target="resendVerification"
            >
              <span
                wire:loading.remove
                wire:target="resendVerification"
                class="flex items-center gap-1"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 20 20"
                  fill="currentColor"
                  class="size-4"
                  aria-hidden="true"
                >
                  <path
                    d="M2.94 4.44A2 2 0 0 1 4.33 4h11.34a2 2 0 0 1 1.39.44l-7.06 5.65-7.06-5.65ZM18 6.08l-7.26 5.79a1 1 0 0 1-1.48 0L2 6.08V14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6.08Z"
                  />
                </svg>
                Verify
              </span>
              <span
                wire:loading
                wire:target="resendVerification"
                class="flex items-center gap-1"
              >
                <x-spinner size="4" />
                <span class="sr-only">Sending verification email...</span>
              </span>
            </x-button>
          @endif
        </div>
      </div>
    @endif
  </div>
</div>
