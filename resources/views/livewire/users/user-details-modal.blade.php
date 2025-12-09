<div>
  @if ($isOpen)
    <div
      wire:key="modal-backdrop"
      wire:click="$set('isOpen', false)"
      @keydown.escape.window="$wire.set('isOpen', false)"
      class="fixed inset-0 z-40 flex items-center justify-center bg-gray-500/75 p-2 backdrop-blur-sm transition-opacity duration-300 md:p-6 dark:bg-gray-900/75"
      role="dialog"
      aria-modal="true"
    >
      <div
        wire:key="modal-content"
        wire:click.stop
        class="relative z-50 mx-4 my-8 flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-lg border-2 border-gray-200 bg-gray-100 shadow-lg transition-transform duration-300 md:max-w-2xl lg:max-w-3xl dark:border-gray-700 dark:bg-gray-800"
      >
        {{-- Header --}}
        <div
          class="sticky top-0 z-20 flex items-start justify-between gap-2 border-b border-gray-200 bg-gray-100 px-4 py-3 backdrop-blur dark:border-gray-700 dark:bg-gray-800"
        >
          <div class="flex flex-col gap-1">
            <h2 class="m-0 text-xl font-bold">{{ $this->user->name }}</h2>
            <x-user-badges :user="$this->user" />
          </div>
          <x-button
            wire:click="$set('isOpen', false)"
            type="button"
            size="sm"
            variant="default"
            aria-label="Close modal"
          >
            <svg
              xmlns="http://www.w3.org/2000/svg"
              viewBox="0 0 24 24"
              fill="currentColor"
              class="size-6"
            >
              <path
                fill-rule="evenodd"
                d="M5.47 5.47a.75.75 0 0 1 1.06 0L12 10.94l5.47-5.47a.75.75 0 1 1 1.06 1.06L13.06 12l5.47 5.47a.75.75 0 1 1-1.06 1.06L12 13.06l-5.47 5.47a.75.75 0 0 1-1.06-1.06L10.94 12 5.47 6.53a.75.75 0 0 1 0-1.06Z"
                clip-rule="evenodd"
              />
            </svg>
          </x-button>
        </div>

        <div class="flex-1 overflow-x-auto overflow-y-auto p-4 md:p-6">
          <div class="flex flex-col gap-6" wire:loading.class="opacity-50">
            {{-- Admin Actions Section --}}
            @if (auth()->user()?->isAdmin())
              <livewire:users.user-admin-actions
                :userId="$userId"
                :key="'user-admin-actions-' . $userId"
              />
            @endif

            {{-- Profile Section --}}
            <livewire:users.user-profile-section
              :userId="$userId"
              :key="'user-profile-section-' . $userId"
            />

            {{-- Primary Email Section (Admin Only) --}}
            @if (auth()->user()?->isAdmin())
              <section>
                <h3
                  class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="size-4"
                  >
                    <path
                      d="M3 4a2 2 0 0 0-2 2v1.161l8.441 4.221a1.25 1.25 0 0 0 1.118 0L19 7.162V6a2 2 0 0 0-2-2H3Z"
                    />
                    <path
                      d="m19 8.839-7.77 3.885a2.75 2.75 0 0 1-2.46 0L1 8.839V14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V8.839Z"
                    />
                  </svg>
                  Primary Email
                </h3>
                <div
                  class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                >
                  <livewire:settings.manage-primary-email
                    :userId="$userId"
                    :key="'manage-primary-email-' . $userId"
                  />
                </div>
              </section>

              {{-- Platform Sync Links Section (Admin Only) --}}
              <section>
                <h3
                  class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
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
                  Platform Sync Links
                </h3>
                <div
                  class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                >
                  <livewire:settings.manage-platform-emails
                    :userId="$userId"
                    :key="'manage-platform-emails-' . $userId"
                  />
                </div>
              </section>

              {{-- Notification Preferences Section (Admin Only) --}}
              <section>
                <h3
                  class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
                >
                  <svg
                    xmlns="http://www.w3.org/2000/svg"
                    viewBox="0 0 20 20"
                    fill="currentColor"
                    class="size-4"
                  >
                    <path
                      d="M4 8a6 6 0 0 1 12 0v2.5c0 .648.302 1.283.783 1.755l.79.736A2 2 0 0 1 18 14.699V16a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2v-1.301a2 2 0 0 1 .427-1.708l.79-.736A2.414 2.414 0 0 0 4 10.5V8Z"
                    />
                    <path d="M12 18a2 2 0 1 1-4 0h4Z" />
                  </svg>
                  Notification Preferences
                </h3>
                <div
                  class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
                >
                  <livewire:settings.manage-notification-preferences
                    :userId="$userId"
                    :key="'manage-notification-prefs-' . $userId"
                  />
                </div>
              </section>
            @endif

            {{-- Timestamps Section --}}
            <livewire:users.user-timestamps-section
              :userId="$userId"
              :key="'user-timestamps-section-' . $userId"
            />
          </div>
        </div>
      </div>
    </div>
  @endif
</div>
