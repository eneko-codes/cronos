<x-layouts.auth title="Reset Password">
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
      <h1 class="text-xl font-bold text-gray-900 dark:text-gray-100">
        Reset Password
      </h1>

      @if (! $tokenValid)
        <x-alert variant="danger">
          <x-slot:title>Reset Link Expired</x-slot>
          This password reset link has already been used or has expired. Please
          request a new password reset link.
        </x-alert>

        <x-button
          :href="route('password.request')"
          variant="primary"
          size="lg"
          class="w-full"
          as="a"
        >
          Request New Reset Link
        </x-button>
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
          class="flex w-full flex-col gap-4"
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
              :value="$email"
              autocomplete="username"
              readonly
              tabindex="-1"
              required
              class="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400"
            />
          </div>

          <div class="flex flex-col gap-1.5">
            <label
              for="password"
              class="text-sm font-medium text-gray-700 dark:text-gray-300"
            >
              New Password
            </label>
            <x-input
              type="password"
              name="password"
              id="password"
              placeholder="Enter your new password"
              autocomplete="new-password"
              required
              @class([
                'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has(
                  'password',
                ),
              ])
            />
          </div>

          <div class="flex flex-col gap-1.5">
            <label
              for="password_confirmation"
              class="text-sm font-medium text-gray-700 dark:text-gray-300"
            >
              Confirm New Password
            </label>
            <x-input
              type="password"
              name="password_confirmation"
              id="password_confirmation"
              placeholder="Confirm your new password"
              autocomplete="new-password"
              required
              @class([
                'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has(
                  'password_confirmation',
                ),
              ])
            />
          </div>

          <x-password-strength />

          <x-button
            type="submit"
            variant="primary"
            size="lg"
            class="mt-2 w-full"
          >
            Reset Password
          </x-button>
        </form>
      @endif

      <div class="text-center">
        <a
          href="{{ route('login') }}"
          class="text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
        >
          Back to Login
        </a>
      </div>
    </div>
  </div>
</x-layouts.auth>
