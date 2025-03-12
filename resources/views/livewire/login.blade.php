<div class="flex w-full max-w-sm flex-col items-center gap-6">
  <div class="flex h-fit flex-col items-center justify-center">
    <x-logo size="xl" />
    <span class="inline-block text-center text-2xl font-bold">
            {{ config('app.name') }}
        </span>
  </div>
  <div
    class="container flex max-w-md flex-col gap-3 rounded-lg border border-gray-200 bg-gray-100 p-8 shadow-xl dark:border-gray-700 dark:bg-gray-800"
  >
    @if ($hasUsers)
      <h1 class="text-xl font-bold text-gray-800 dark:text-gray-200">Login</h1>
      @if ($tokenSentMessage)
        <div
          class="flex w-full flex-row gap-2 rounded-lg border-2 border-green-200 bg-green-200/75 p-4 text-green-900 shadow-md dark:border-green-200 dark:bg-green-200 dark:text-green-700"
        >
          <div class="rounded-full bg-green-100 p-3 shadow-sm">
            <svg
              xmlns="http://www.w3.org/2000/svg"
              width="16"
              height="16"
              fill="currentColor"
              class="size-5"
              viewBox="0 0 16 16"
            >
              <path
                d="M2 2a2 2 0 0 0-2 2v8.01A2 2 0 0 0 2 14h5.5a.5.5 0 0 0 0-1H2a1 1 0 0 1-.966-.741l5.64-3.471L8 9.583l7-4.2V8.5a.5.5 0 0 0 1 0V4a2 2 0 0 0-2-2zm3.708 6.208L1 11.105V5.383zM1 4.217V4a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v.217l-7 4.2z"
              />
              <path
                d="M14.247 14.269c1.01 0 1.587-.857 1.587-2.025v-.21C15.834 10.43 14.64 9 12.52 9h-.035C10.42 9 9 10.36 9 12.432v.214C9 14.82 10.438 16 12.358 16h.044c.594 0 1.018-.074 1.237-.175v-.73c-.245.11-.673.18-1.18.18h-.044c-1.334 0-2.571-.788-2.571-2.655v-.157c0-1.657 1.058-2.724 2.64-2.724h.04c1.535 0 2.484 1.05 2.484 2.326v.118c0 .975-.324 1.39-.639 1.39-.232 0-.41-.148-.41-.42v-2.19h-.906v.569h-.03c-.084-.298-.368-.63-.954-.63-.778 0-1.259.555-1.259 1.4v.528c0 .892.49 1.434 1.26 1.434.471 0 .896-.227 1.014-.643h.043c.118.42.617.648 1.12.648m-2.453-1.588v-.227c0-.546.227-.791.573-.791.297 0 .572.192.572.708v.367c0 .573-.253.744-.564.744-.354 0-.581-.215-.581-.8Z"
              />
            </svg>
          </div>

          <p class="text-sm font-semibold">
            {{ $tokenSentMessage }}
          </p>
        </div>
      @else
        <form
          wire:submit.prevent="sendMagicLink"
          @keydown.enter="sendMagicLink"
          class="flex w-full flex-col gap-3"
        >
          @csrf
          <label
            for="email"
            class="w-full text-sm font-medium text-gray-600 dark:text-gray-400"
          >
            Enter your Innovae email address to receive your login link.
          </label>
          <input
            type="email"
            wire:model="email"
            id="email"
            placeholder="you@innovae.com"
            required
            class="mt-2 w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500"
          />

          @error('email')
          <span class="text-sm text-red-500">{{ $message }}</span>
          @enderror

          <!-- Remember Me Checkbox -->
          <div class="flex items-center">
            <input
              type="checkbox"
              id="remember"
              wire:model="remember"
              class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-indigo-600"
            />
            <label for="remember" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
              Remember Me
            </label>
          </div>

          <x-button type="submit" variant="info" size="lg" class="w-full">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor"
                 class="size-4" viewBox="0 0 16 16">
              <path
                d="M2 2A2 2 0 0 0 .05 3.555L8 8.414l7.95-4.859A2 2 0 0 0 14 2zm-2 9.8V4.698l5.803 3.546zm6.761-2.97-6.57 4.026A2 2 0 0 0 2 14h6.256A4.5 4.5 0 0 1 8 12.5a4.49 4.49 0 0 1 1.606-3.446l-.367-.225L8 9.586zM16 9.671V4.697l-5.803 3.546.338.208A4.5 4.5 0 0 1 12.5 8c1.414 0 2.675.652 3.5 1.671" />
              <path
                d="M15.834 12.244c0 1.168-.577 2.025-1.587 2.025-.503 0-1.002-.228-1.12-.648h-.043c-.118.416-.543.643-1.015.643-.77 0-1.259-.542-1.259-1.434v-.529c0-.844.481-1.4 1.26-1.4.585 0 .87.333.953.63h.03v-.568h.905v2.19c0 .272.18.42.411.42.315 0 .639-.415.639-1.39v-.118c0-1.277-.95-2.326-2.484-2.326h-.04c-1.582 0-2.64 1.067-2.64 2.724v.157c0 1.867 1.237 2.654 2.57 2.654h.045c.507 0 .935-.07 1.18-.18v.731c-.219.1-.643.175-1.237.175h-.044C10.438 16 9 14.82 9 12.646v-.214C9 10.36 10.421 9 12.485 9h.035c2.12 0 3.314 1.43 3.314 3.034zm-4.04.21v.227c0 .586.227.8.581.8.31 0 .564-.17.564-.743v-.367c0-.516-.275-.708-.572-.708-.346 0-.573.245-.573.791" />
            </svg>
            Send Token
            <x-spinner wire:loading.delay size="4" />
          </x-button>
        </form>
      @endif
    @else
      <div class="text-center flex flex-col gap-1">
        <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">No users found</h2>
        <p class="text-gray-600 dark:text-gray-400">Click below to sync user data.</p>
        <div class="mt-4">
          <livewire:sync-button syncType="users" />
        </div>
      </div>
    @endif
  </div>
</div>
