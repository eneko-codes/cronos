<x-layouts.auth title="Forgot Password">
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
          class="flex w-full flex-col gap-4"
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
              @class([
                'border-red-500 focus:border-red-500 focus:ring-red-500' => $errors->has(
                  'email',
                ),
              ])
            />
          </div>

          <x-button
            type="submit"
            variant="primary"
            size="lg"
            class="mt-2 w-full"
          >
            Send Password Reset Link
          </x-button>
        </form>
      @endif

      <div class="mt-2 text-center">
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
