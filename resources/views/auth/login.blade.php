<x-layouts.auth title="Login">
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
      <h1 class="text-xl font-bold text-gray-800 dark:text-gray-200">Login</h1>

      {{-- Display success message for password reset --}}
      @if (session('password_reset_success'))
        <x-alert variant="success">
          <x-slot:title>
            Password Reset Complete!
          </x-slot>
          {{ session('password_reset_success') }}
        </x-alert>
      @endif

      {{-- Display welcome email resend message --}}
      @if (session('welcome_email_sent'))
        <x-alert variant="success">
          <x-slot:title>Welcome Email Sent!</x-slot>
          {{ session('welcome_email_sent') }}
        </x-alert>
      @endif

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
        class="flex w-full flex-col gap-3"
      >
        @csrf
        <label
          for="email"
          class="w-full text-sm font-medium text-gray-600 dark:text-gray-400"
        >
          Email Address
        </label>
        <input
          type="email"
          name="email"
          id="email"
          placeholder="you@example.com"
          autocomplete="email"
          required
          value="{{ old('email') }}"
          class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500"
        />

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
          placeholder="Enter your password"
          autocomplete="current-password"
          required
          class="w-full rounded-md border border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500"
        />

        <div class="flex items-center">
          <input
            type="checkbox"
            name="remember"
            id="remember"
            value="1"
            {{ old('remember') ? 'checked' : '' }}
            class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-800 dark:text-indigo-600"
          />
          <label
            for="remember"
            class="ml-2 block text-sm text-gray-900 dark:text-gray-300"
          >
            Remember Me
          </label>
        </div>

        <x-button type="submit" variant="info" size="lg" class="w-full">
          Login
        </x-button>
      </form>

      <div class="mt-2 text-center">
        <a
          href="{{ route('password.request') }}"
          class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
        >
          Forgot your password?
        </a>
      </div>
    </div>
  </div>
</x-layouts.auth>
