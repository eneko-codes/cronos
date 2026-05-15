<div>
  @if ($isOpen)
    <div
      wire:key="modal-backdrop-archived-users"
      wire:click="closeModal"
      @keydown.escape.window="$wire.closeModal()"
      class="fixed inset-0 z-40 flex items-center justify-center bg-gray-500/75 p-2 backdrop-blur-sm transition-opacity duration-300 md:p-6 dark:bg-gray-900/75"
      role="dialog"
      aria-modal="true"
    >
      <div
        wire:key="modal-content-archived-users"
        wire:click.stop
        class="relative z-50 mx-4 my-8 flex max-h-[90vh] w-full max-w-4xl flex-col overflow-hidden rounded-lg border-2 border-gray-200 bg-white shadow-lg transition-transform duration-300 dark:border-gray-800 dark:bg-gray-900"
      >
        {{-- Header --}}
        <div
          class="sticky top-0 z-20 flex items-center justify-between gap-2 border-b border-gray-200 bg-white px-4 py-3 backdrop-blur dark:border-gray-700 dark:bg-gray-900"
        >
          <h2 class="m-0 text-xl font-bold text-gray-900 dark:text-gray-100">
            Archived Users
          </h2>
          <x-button
            wire:click="closeModal"
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

        {{-- Content --}}
        <div
          class="flex flex-1 flex-col gap-4 overflow-x-auto overflow-y-auto p-4 md:p-6"
          wire:loading.class="opacity-50"
        >
          @if ($users->isNotEmpty())
            <div
              class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
              <table class="w-full border-collapse">
                <tbody class="text-sm">
                  @foreach ($users as $user)
                    <tr
                      wire:key="archived-user-{{ $user->id }}"
                      class="flex flex-row items-center justify-between gap-4 border-b border-gray-200 p-4 text-gray-900 dark:border-gray-700 dark:text-gray-100"
                    >
                      {{-- User Name --}}
                      <td class="flex flex-1 items-center">
                        <p
                          class="text-base font-semibold text-gray-900 capitalize dark:text-gray-100"
                        >
                          {{ $user->name }}
                        </p>
                      </td>

                      {{-- Deactivated Timestamp --}}
                      <td
                        class="flex items-center text-sm whitespace-nowrap text-gray-500 dark:text-gray-400"
                      >
                        {{ $user->manually_archived_at?->format('Y-m-d H:i') ?? 'N/A' }}
                      </td>

                      {{-- Reactivate Button --}}
                      <td
                        class="flex flex-none items-center justify-center whitespace-nowrap"
                      >
                        @if (auth()->user()?->can('reactivateUser', $user))
                          <x-button
                            wire:click="reactivateUser({{ $user->id }})"
                            wire:confirm="Are you sure you want to reactivate {{ $user->name }}? They will regain access to their account."
                            variant="success"
                            size="xs"
                          >
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              viewBox="0 0 20 20"
                              fill="currentColor"
                              class="size-4"
                            >
                              <path
                                fill-rule="evenodd"
                                d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z"
                                clip-rule="evenodd"
                              />
                            </svg>
                            <span
                              wire:loading.remove.delay
                              wire:target="reactivateUser({{ $user->id }})"
                            >
                              Reactivate
                            </span>
                            <span
                              wire:loading.delay
                              wire:target="reactivateUser({{ $user->id }})"
                              class="flex items-center gap-1"
                            >
                              <x-spinner size="4" />
                            </span>
                          </x-button>
                        @endif
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>

              {{-- Pagination Links --}}
              <div class="mt-4 px-4 py-3">
                {{ $users->links() }}
              </div>
            </div>
          @else
            {{-- No Users Found Message --}}
            <div
              class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm dark:border-gray-700 dark:bg-gray-800"
            >
              <span
                class="text-sm font-medium text-gray-600 dark:text-gray-400"
              >
                No archived users found!
              </span>
            </div>
          @endif
        </div>
      </div>
    </div>
  @endif
</div>
