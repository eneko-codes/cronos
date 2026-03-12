<x-layouts.auth title="Verify Email">
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
        Verify your email address
      </h1>

      {{-- Success message if verification link was resent --}}
      @if (session('status') == 'verification-link-sent')
        <x-alert variant="success">
          <x-slot:title>Email sent!</x-slot>
          A new verification link has been sent.
        </x-alert>
      @endif

      {{-- Rate limit warning --}}
      @if (session('toast') && session('toast')['variant'] === 'warning')
        <x-alert variant="warning">
          <x-slot:title>Too many requests</x-slot>
          {{ session('toast')['message'] }}
        </x-alert>
      @endif

      <p class="text-sm text-gray-600 dark:text-gray-400">
        We've sent a verification link to
        <strong class="text-gray-800 dark:text-gray-200">
          {{ Auth::user()->email }}
        </strong>
        . Please check your email and click the link to continue.
      </p>

      <p class="text-xs text-gray-500 dark:text-gray-500">
        The link expires in {{ config('auth.verification.expire', 60) }}
        minutes. Didn't receive it? Click the button below to resend.
      </p>

      <form
        method="POST"
        action="{{ route('verification.send') }}"
        class="flex w-full flex-col gap-3"
      >
        @csrf
        <x-button type="submit" variant="info" size="lg" class="mt-2 w-full">
          Resend verification email
        </x-button>
      </form>

      <div class="mt-2 text-center">
        <form method="POST" action="{{ route('logout') }}" class="inline">
          @csrf
          <button
            type="submit"
            class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400 dark:hover:text-blue-300"
          >
            Sign out
          </button>
        </form>
      </div>
    </div>
  </div>
</x-layouts.auth>
