<x-layouts.auth title="Set Up Your Password">
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
      <h1 class="text-xl font-bold text-gray-800 dark:text-gray-200">
        Set Up Your Password
      </h1>

      <p class="text-sm text-gray-600 dark:text-gray-400">
        You need to set up a secure password to access your account
        <strong>{{ $email }}</strong>
        .
      </p>

      @if (session('password_setup_success'))
        <x-alert variant="success">
          <x-slot:title>
            Password Setup Complete!
          </x-slot>
          {{ session('password_setup_success') }}
        </x-alert>
      @endif

      <form
        method="POST"
        action="{{ route('password.setup') }}"
        class="flex w-full flex-col gap-3"
      >
        @csrf
        @error('email')
          <x-alert variant="warning">
            <x-slot:title>
              {{ $message }}
            </x-slot>
          </x-alert>
        @enderror

        @error('token')
          <x-alert variant="warning">
            <x-slot:title>
              {{ $message }}
            </x-slot>
          </x-alert>
        @enderror

        @error('password')
          <x-alert variant="warning">
            <x-slot:title>
              {{ $message }}
            </x-slot>
          </x-alert>
        @enderror

        @error('password_confirmation')
          <x-alert variant="warning">
            <x-slot:title>
              {{ $message }}
            </x-slot>
          </x-alert>
        @enderror

        <input type="hidden" name="email" value="{{ $email }}" />
        <input type="hidden" name="token" value="{{ $token }}" />

        <label
          for="password"
          class="w-full text-sm font-medium text-gray-600 dark:text-gray-400"
        >
          Password
        </label>
        <input
          type="password"
          name="password"
          id="password"
          placeholder="Enter your new password"
          autocomplete="new-password"
          data-lpignore="true"
          required
          @class([
            'w-full rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500',
            'border-red-500' => $errors->has('password'),
            'border-gray-300' => ! $errors->has('password'),
          ])
        />

        <label
          for="password_confirmation"
          class="w-full text-sm font-medium text-gray-600 dark:text-gray-400"
        >
          Confirm Password
        </label>
        <input
          type="password"
          name="password_confirmation"
          id="password_confirmation"
          placeholder="Confirm your new password"
          autocomplete="new-password"
          data-lpignore="true"
          required
          @class([
            'w-full rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500',
            'border-red-500' => $errors->has('password_confirmation'),
            'border-gray-300' => ! $errors->has('password_confirmation'),
          ])
        />
        <x-password-strength />

        <x-button type="submit" variant="info" size="lg" class="w-full">
          Set Up Password
        </x-button>
      </form>

      <div class="mt-4 text-center">
        <a
          href="{{ route('login') }}"
          class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
        >
          Already have a password? Sign in here
        </a>
      </div>
    </div>
  </div>
</x-layouts.auth>
