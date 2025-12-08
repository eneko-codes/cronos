<div
  class="flex h-full flex-col gap-4 rounded-xl bg-white p-6 shadow-md dark:bg-gray-800"
>
  <div class="flex flex-col items-start gap-1 text-lg font-bold">
    <div class="inline-flex flex-row items-center gap-2">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        class="size-4 text-gray-400 dark:text-gray-500"
        viewBox="0 0 16 16"
      >
        <path
          d="M14 13.5v-7a.5.5 0 0 0-.5-.5H12V4.5a.5.5 0 0 0-.5-.5h-1v-.5A.5.5 0 0 0 10 3H6a.5.5 0 0 0-.5.5V4h-1a.5.5 0 0 0-.5.5V6H2.5a.5.5 0 0 0-.5.5v7a.5.5 0 0 0 .5.5h11a.5.5 0 0 0 .5-.5M3.75 11h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25m2 0h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25m1.75.25a.25.25 0 0 1 .25-.25h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25m1.75.25a.25.25 0 0 1 .25-.25h.5a.25.25 0 0 1 .25.25v1.5a.25.25 0 0 1-.25.25h-.5a.25.25 0 0 1-.25-.25v-1.5a.25.25 0 0 1 .25-.25z"
        />
        <path
          d="M2 0a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V2a2 2 0 0 0-2-2zM1 2a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1z"
        />
      </svg>
      <h2>API Health Check</h2>
    </div>
    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
      Test the connection to the different APIs used by the application.
    </p>
  </div>
  <div class="space-y-2">
    <x-button
      wire:click="pingOdoo"
      wire:target="pingOdoo"
      type="button"
      size="md"
      variant="info"
      class="w-full"
    >
      <span wire:target="pingOdoo">Ping Odoo</span>
      <x-spinner size="4" wire:loading wire:target="pingOdoo" />
    </x-button>

    <x-button
      wire:click="pingDesktime"
      wire:target="pingDesktime"
      type="button"
      size="md"
      variant="info"
      class="w-full"
    >
      <span wire:target="pingDesktime">Ping Desktime</span>
      <x-spinner size="4" wire:loading wire:target="pingDesktime" />
    </x-button>

    <x-button
      wire:click="pingProofhub"
      wire:target="pingProofhub"
      type="button"
      size="md"
      variant="info"
      class="w-full"
    >
      <span wire:target="pingProofhub">Ping ProofHub</span>
      <x-spinner size="4" wire:loading wire:target="pingProofhub" />
    </x-button>

    <x-button
      wire:click="pingSystemPin"
      wire:target="pingSystemPin"
      type="button"
      size="md"
      variant="info"
      class="w-full"
    >
      <span wire:target="pingSystemPin">Ping SystemPin</span>
      <x-spinner size="4" wire:loading wire:target="pingSystemPin" />
    </x-button>
  </div>
</div>
