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

      {{-- Display token verification errors from session --}}
      @if ($errors->has('token'))
        <x-alert variant="danger">
          <x-slot:title>Error</x-slot>
          {{ $errors->first('token') }}
        </x-alert>
      @endif

      {{-- Display success message for token sent --}}
      @if (session('status'))
        <x-alert variant="success">
          <x-slot:title>
            Magic login link sent!
          </x-slot>
          {{ session('status') }}
        </x-alert>
      @else
        <form
          method="POST"
          action="{{ route('login.request') }}"
          class="flex w-full flex-col gap-3"
        >
          @csrf
          <label
            for="email"
            class="w-full text-sm font-medium text-gray-600 dark:text-gray-400"
          >
            Enter your email address to receive your login link.
          </label>
          <input
            type="email"
            name="email"
            id="email"
            placeholder="you@example.com"
            required
            value="{{ old('email') }}"
            @class([
              'mt-2 w-full rounded-md border bg-gray-50 px-3 py-2 text-sm text-gray-800 placeholder:text-gray-400 dark:border-gray-600 dark:bg-gray-800/50 dark:text-gray-200 dark:placeholder:text-gray-500',
              'border-red-500' => $errors->has('email'),
              'border-gray-300' => ! $errors->has('email'),
            ])
          />
          @error('email')
            <span class="text-sm text-red-500">{{ $message }}</span>
          @enderror

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
          @error('remember')
            <span class="text-sm text-red-500">{{ $message }}</span>
          @enderror

          <x-button type="submit" variant="info" size="lg" class="w-full">
            Send Magic Link
          </x-button>
        </form>
      @endif
    </div>
  </div>
</x-layouts.auth>
