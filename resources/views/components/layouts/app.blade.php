<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $title ?? 'Page Title' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
  </head>
  <body
    x-data="{
      navOpen: false,
      isMobile: window.innerWidth < 1024,
      handleResize() {
        this.isMobile = window.innerWidth < 1024
        if (! this.isMobile) this.navOpen = false
      },
    }"
    x-init="window.addEventListener('resize', handleResize)"
    @resize.window="handleResize"
    class="min-h-screen bg-gray-100 font-sans text-gray-800 antialiased dark:bg-gray-900 dark:text-gray-200"
  >
    <!-- Navigation Header -->
    <header
      class="fixed inset-x-0 top-0 z-40 h-14 border-b border-gray-100 bg-white shadow-sm dark:border-gray-800 dark:bg-gray-900"
    >
      <div class="mx-auto h-full max-w-screen-2xl px-4">
        <div class="flex h-full items-center justify-between">
          <div class="flex h-full items-center gap-8">
            <x-logo size="md" />
            <!-- Navigation links: hidden below 1000px (lg) -->
            <div class="hidden h-full lg:block">
              <x-navigation-links />
            </div>
          </div>

          @auth
            <div class="flex flex-shrink-0 items-center gap-3">
              <!-- Settings button (visible to all logged in users) -->
              <livewire:ui.sidebar-toggle />

              <!-- User dropdown: hidden below 1000px (lg) -->
              <div x-show="!isMobile" x-cloak class="hidden lg:block">
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

                  <div class="flex flex-col gap-2">
                    <!-- Last Synced Component -->
                    @livewire('ui.last-synced')

                    <!-- App Time Component -->
                    @livewire('ui.app-time')

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

              <!-- Burger menu: visible below 1000px (lg) -->
              <button
                @click="navOpen = !navOpen"
                x-show="isMobile"
                class="lg:hidden"
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
                      <span class="font-semibold">
                        {{ Auth::user()->name }}
                      </span>
                      <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ Auth::user()->email }}
                      </span>
                    </div>

                    <!-- Last Synced Component (Mobile) -->
                    <div
                      class="w-full border-b border-gray-200 py-2 dark:border-gray-700"
                    >
                      @livewire('ui.last-synced')
                    </div>

                    <!-- App Time Component (Mobile) -->
                    <div class="w-full py-2">
                      @livewire('ui.app-time')
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
    <main class="mx-auto w-full max-w-screen-2xl pt-14">
      <div class="px-4 py-6">
        {{ $slot }}
      </div>
    </main>

    @auth
      @livewire('ui.sidebar')
    @endauth

    @persist('toast')
      @livewire('ui.toast')
    @endpersist

    @livewire('users.user-details-modal')
    @livewireScripts

    <noscript>
      <div
        class="fixed top-0 right-0 bottom-0 left-0 z-50 block h-screen w-screen bg-red-400/50 text-center backdrop-blur dark:bg-red-600/50"
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
