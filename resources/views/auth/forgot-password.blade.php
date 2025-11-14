<x-layouts.auth title="Forgot Password">
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
        Forgot Password
      </h1>

      {{-- Display success message for reset link sent --}}
      @if (session('password_reset_success'))
        <x-alert variant="success">
          <x-slot:title>Reset Link Sent!</x-slot>
          {{ session('password_reset_success') }}
        </x-alert>
      @endif

      @if (! session('password_reset_success'))
        <p class="text-sm text-gray-600 dark:text-gray-400">
          Enter your email address and we'll send you instructions to reset your
          password.
        </p>

        <form
          method="POST"
          action="{{ route('password.email') }}"
          class="flex w-full flex-col gap-3"
        >
          @csrf
          @error('rate_limit')
            <x-alert variant="warning">
              <x-slot:title>
                {{ $message }}
              </x-slot>
            </x-alert>
          @enderror

          @error('email')
            <x-alert variant="warning">
              <x-slot:title>
                {{ $message }}
              </x-slot>
            </x-alert>
          @enderror

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
            @class([
              'w-full rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500',
              'border-red-500' => $errors->has('email'),
              'border-gray-300' => ! $errors->has('email'),
            ])
          />

          <x-button type="submit" variant="info" size="lg" class="mt-2 w-full">
            Send Password Reset Link
          </x-button>
        </form>
      @endif

      <div class="mt-2 text-center">
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
