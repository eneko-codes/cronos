<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Page Title' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
    <script>
      document.addEventListener('livewire:init', () => {
        Livewire.on('timezone-changed', ({ timezone }) => {
          document
            .querySelectorAll('[data-timezone-display]')
            .forEach((el) => (el.textContent = timezone));
        });
      });
    </script>
  </head>
  <body
    x-data="{
      navOpen: false,
      isMobile: window.innerWidth < 768,
      handleResize() {
        this.isMobile = window.innerWidth < 768
        if (! this.isMobile) this.navOpen = false
      },
    }"
    x-init="window.addEventListener('resize', handleResize)"
    @resize.window="handleResize"
    class="min-h-screen bg-gray-50 font-sans text-gray-800 antialiased dark:bg-gray-900 dark:text-gray-200"
  >
    <!-- Navigation Header -->
    <header
      class="fixed inset-x-0 top-0 z-40 h-14 border-b bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
      <div class="mx-auto h-full max-w-screen-2xl px-4">
        <div class="flex h-full items-center justify-between">
          <div class="flex h-full items-center gap-8">
            <x-logo size="md" />
            <div class="hidden h-full md:block">
              <x-navigation-links />
            </div>
          </div>

          @auth
            <div class="flex items-center gap-3">
              <!-- Notification bell button -->
              <livewire:notification-toggle />
              
              <!-- Alerts button (only visible if user can see alerts) -->
              @if(auth()->user()->isAdmin())
                <livewire:alerts.alerts-toggle />
              @endif
              
              <div x-show="!isMobile" x-cloak class="hidden md:block">
                <x-dropdown-menu>
                  <x-slot:trigger>
                    <div class="flex items-center gap-3">
                      <div class="flex flex-col items-start">
                        <span class="text-xs font-semibold">
                          {{ Auth::user()->name }}
                        </span>
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                          {{ Auth::user()->email }}
                        </span>
                      </div>
                    </div>
                  </x-slot>

                  <div class="space-y-2">
                    <div class="flex flex-col">
                      <div class="flex flex-col">
                        <div class="flex flex-row items-center gap-1">
                          <span
                            class="text-xs font-bold text-gray-700 dark:text-gray-200"
                          >
                            Your timezone
                          </span>
                          <x-tooltip
                            text="The default timezone is fetched from your Odoo employee data. Changes are stored in your session."
                          >
                            <svg
                              xmlns="http://www.w3.org/2000/svg"
                              fill="none"
                              viewBox="0 0 24 24"
                              stroke-width="1.5"
                              stroke="currentColor"
                              class="size-3"
                            >
                              <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                              />
                            </svg>
                          </x-tooltip>
                        </div>
                        <livewire:timezone-selector />
                      </div>
                    </div>

                    <form
                      action="{{ route('logout') }}"
                      method="POST"
                      wire:submit.prevent="logout"
                    >
                      @csrf
                      <x-button
                        type="submit"
                        variant="alert"
                        size="sm"
                        class="flex w-full items-center justify-center gap-2"
                        wire:target="logout"
                      >
                        <span wire:target="logout">Signout</span>
                        <x-spinner
                          size="4"
                          wire:loading.delay
                          wire:target="logout"
                        />
                      </x-button>
                    </form>
                  </div>
                </x-dropdown-menu>
              </div>

              <button
                @click="navOpen = !navOpen"
                x-show="isMobile"
                class="md:hidden"
              >
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="size-6"
                  :class="{ 'hidden': navOpen }"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M3.75 5.25h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5m-16.5 4.5h16.5"
                  />
                </svg>
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  fill="none"
                  viewBox="0 0 24 24"
                  stroke-width="1.5"
                  stroke="currentColor"
                  class="size-6"
                  :class="{ 'hidden': !navOpen }"
                >
                  <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    d="M6 18 18 6M6 6l12 12"
                  />
                </svg>
              </button>
            </div>
          @endauth
        </div>

        <!-- Mobile Navigation -->
        <div
          x-show="navOpen && isMobile"
          x-cloak
          @click.outside="navOpen = false"
          class="absolute inset-x-0 top-14 bg-white shadow-lg dark:bg-gray-900"
          x-transition:enter="transition duration-200 ease-out"
          x-transition:enter-start="opacity-0"
          x-transition:enter-end="opacity-100"
          x-transition:leave="transition duration-150 ease-in"
          x-transition:leave-start="opacity-100"
          x-transition:leave-end="opacity-0"
        >
          <div class="divide-y divide-gray-100/80 dark:divide-gray-800/80">
            <div class="px-4 py-2">
              <x-navigation-links />
            </div>
            @auth
              <div class="space-y-4 p-4">
                <div class="flex flex-col gap-4">
                  <!-- User profile info -->
                  <div class="flex flex-col items-start gap-3">
                    <div class="flex flex-col">
                      <span class="text-xs font-semibold">
                        {{ Auth::user()->name }}
                      </span>
                      <span class="text-xs text-gray-500 dark:text-gray-400">
                        {{ Auth::user()->email }}
                      </span>
                    </div>
                    <div class="flex flex-col">
                      <div class="flex flex-row items-center gap-1">
                        <span
                          class="text-xs font-bold text-gray-700 dark:text-gray-200"
                        >
                          Your timezone
                        </span>
                        <x-tooltip
                          text="The default timezone is fetched from your Odoo employee data. Changes are stored in your session."
                        >
                          <svg
                            xmlns="http://www.w3.org/2000/svg"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke-width="1.5"
                            stroke="currentColor"
                            class="size-3"
                          >
                            <path
                              stroke-linecap="round"
                              stroke-linejoin="round"
                              d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z"
                            />
                          </svg>
                        </x-tooltip>
                      </div>
                      <livewire:timezone-selector />
                    </div>
                  </div>
                  <!-- logout form -->
                  <form
                    action="{{ route('logout') }}"
                    method="POST"
                    wire:submit.prevent="logout"
                  >
                    @csrf
                    <x-button
                      type="submit"
                      variant="alert"
                      size="sm"
                      class="flex w-full items-center justify-center gap-2"
                      wire:target="logout"
                    >
                      <span wire:target="logout">Signout</span>
                      <x-spinner
                        size="4"
                        wire:loading.delay
                        wire:target="logout"
                      />
                    </x-button>
                  </form>
                </div>
              </div>
            @endauth
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="w-full pt-14">
      <div class="mx-auto max-w-screen-2xl px-4 py-6">
        <div class="flex flex-col md:flex-row gap-6">
          <div class="flex-1">
            {{ $slot }}
          </div>
        </div>
      </div>
    </main>

    <!-- Alerts Sidebar - Floating panel -->
    @auth
      @livewire('alerts.alerts-sidebar')
    @endauth

    @persist('toast')
      @livewire('toast')
    @endpersist

    @livewire('user-details-modal')
    @livewireScripts

    <noscript>
      <div
        class="fixed bottom-0 left-0 right-0 top-0 z-50 block h-screen w-screen bg-red-400/50 text-center backdrop-blur dark:bg-red-600/50"
      >
        <div
          class="fixed inset-0 m-auto flex h-fit w-fit flex-col items-center gap-4 rounded-lg bg-red-900 p-8 text-white shadow-xl dark:bg-red-800"
        >
          <!-- SVG Icon -->
          <h1 class="text-2xl font-bold">
            JavaScript is disabled in your browser!
          </h1>
          <p class="text-xl font-semibold">
            Please, enable it to use this website.
          </p>
        </div>
      </div>
    </noscript>
  </body>
</html>
