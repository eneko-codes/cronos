<x-layouts.auth title="Login">
  <div class="flex w-full max-w-sm flex-col items-center gap-6">
    <div class="flex h-fit flex-col items-center justify-center">
      <x-logo size="xl" />
      <span class="inline-block text-center text-2xl font-bold">
        {{ config('app.name') }}
      </span>
    </div>
    <div
      class="container flex max-w-md flex-col gap-4 rounded-xl border border-gray-200 bg-white p-8 shadow-lg dark:border-gray-700 dark:bg-gray-800"
    >
      <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">Login</h1>

      {{-- Display rate limit error --}}
      @error('rate_limit')
        <x-alert variant="warning">
          <x-slot:title>
            Too many login attempts
          </x-slot>
          Please try again in {{ (int) $message }}
          {{ (int) $message === 1 ? 'minute' : 'minutes' }}.
        </x-alert>
      @enderror

      {{-- Display authentication error (generic credentials error) --}}
      @error('credentials')
        <x-alert variant="warning">
          <x-slot:title>{{ $message }}</x-slot>
        </x-alert>
      @enderror

      {{-- Display validation errors for individual fields --}}
      @error('email')
        @if ($message !== 'These credentials do not match our records.')
          <x-alert variant="warning">
            <x-slot:title>
              {{ $message }}
            </x-slot>
          </x-alert>
        @endif
      @enderror

      @error('password')
        @if ($message !== 'These credentials do not match our records.')
          <x-alert variant="warning">
            <x-slot:title>
              {{ $message }}
            </x-slot>
          </x-alert>
        @endif
      @enderror

      <form
        method="POST"
        action="{{ route('login') }}"
        class="flex w-full flex-col gap-4"
      >
        @csrf
        <div class="flex flex-col gap-1.5">
          <label
            for="email"
            class="text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Email Address
          </label>
          <x-input
            type="email"
            name="email"
            id="email"
            placeholder="you@example.com"
            autocomplete="email"
            required
            :value="old('email')"
          />
        </div>

        <div class="flex flex-col gap-1.5">
          <label
            for="password"
            class="text-sm font-medium text-gray-700 dark:text-gray-300"
          >
            Password
          </label>
          <x-input
            type="password"
            name="password"
            id="password"
            placeholder="Enter your password"
            autocomplete="current-password"
            required
          />
        </div>

        <div class="flex items-center">
          <input
            type="checkbox"
            name="remember"
            id="remember"
            value="1"
            {{ old('remember') ? 'checked' : '' }}
            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-blue-500 dark:focus:ring-blue-400"
          />
          <label
            for="remember"
            class="ml-2 block text-sm text-gray-700 dark:text-gray-300"
          >
            Remember Me
          </label>
        </div>

        <x-button type="submit" variant="primary" size="lg" class="mt-2 w-full">
          Login
        </x-button>
      </form>

      <div class="mt-2 text-center">
        <a
          href="{{ route('password.request') }}"
          class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
        >
          Forgot your password?
        </a>
      </div>
    </div>
  </div>
</x-layouts.auth>
