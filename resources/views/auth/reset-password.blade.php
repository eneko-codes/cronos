<x-layouts.auth title="Reset Password">
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
        Reset Password
      </h1>

      @if (! $tokenValid)
        <x-alert variant="danger">
          <x-slot:title>Reset Link Expired</x-slot>
          This password reset link has already been used or has expired. Please
          request a new password reset link.
        </x-alert>

        <a
          href="{{ route('password.request') }}"
          class="inline-flex w-full items-center justify-center rounded-lg bg-blue-200/75 px-3 py-1.5 text-sm font-semibold text-blue-800 shadow-sm hover:bg-blue-200 dark:bg-blue-200 dark:hover:bg-blue-100"
        >
          Request New Reset Link
        </a>
      @else
        @if ($email)
          <p class="text-sm text-gray-600 dark:text-gray-400">
            Please enter a new password to reset your account
            <strong>{{ $email }}</strong>
            .
          </p>
        @endif
      @endif

      @if ($tokenValid)
        <form
          method="POST"
          action="{{ route('password.update') }}"
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

          @error('rate_limit')
            <x-alert variant="warning">
              <x-slot:title>
                {{ $message }}
              </x-slot>
            </x-alert>
          @enderror

          <input type="hidden" name="token" value="{{ $token }}" />
          <input type="hidden" name="email" value="{{ $email }}" />

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
            value="{{ $email }}"
            autocomplete="username"
            readonly
            tabindex="-1"
            required
            @class([
              'w-full rounded-md border bg-gray-100 px-3 py-2 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400',
              'border-gray-300 dark:border-gray-600',
            ])
          />

          <label
            for="password"
            class="w-full text-sm font-medium text-gray-600 dark:text-gray-400"
          >
            New Password
          </label>
          <input
            type="password"
            name="password"
            id="password"
            placeholder="Enter your new password"
            autocomplete="new-password"
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
            Confirm New Password
          </label>
          <input
            type="password"
            name="password_confirmation"
            id="password_confirmation"
            placeholder="Confirm your new password"
            autocomplete="new-password"
            required
            @class([
              'w-full rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500',
              'border-red-500' => $errors->has('password_confirmation'),
              'border-gray-300' => ! $errors->has('password_confirmation'),
            ])
          />

          <x-password-strength />

          <x-button type="submit" variant="info" size="lg" class="mt-2 w-full">
            Reset Password
          </x-button>
        </form>
      @endif

      <div class="text-center">
        <a
          href="{{ route('login') }}"
          class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
        >
          Back to Login
        </a>
      </div>
    </div>
  </div>
</x-layouts.auth>
