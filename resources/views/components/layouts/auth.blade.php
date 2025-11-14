<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>{{ $title ?? 'Page Title' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
  </head>
  <body
    class="flex min-h-screen items-center justify-center bg-gray-50 font-sans text-gray-800 antialiased dark:bg-gray-900 dark:text-gray-100"
  >
    {{ $slot }}
    @persist('toast')
      @livewire('ui.toast')
    @endpersist

    @include('components.partials.toast-dispatch')

    @livewireScripts
  </body>
</html>
