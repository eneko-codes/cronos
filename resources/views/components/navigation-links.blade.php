@php
  $baseClass = "flex h-full items-center gap-2 transition md:px-2 md:py-1.5";
  $desktopClass =
      "md:border-b-2 md:hover:border-blue-500 dark:md:hover:border-blue-400";
  $mobileClass =
      "rounded-md px-3 py-2 hover:bg-gray-100 md:rounded-none md:px-0 md:py-0 md:hover:bg-transparent dark:hover:bg-gray-800 dark:md:hover:bg-transparent";
  $activeClass =
      "text-blue-600 md:border-b-2 md:border-blue-500 dark:text-blue-400 dark:md:border-blue-400";
  $inactiveClass = "border-transparent text-gray-600 dark:text-gray-300";
@endphp

<nav
  class="flex h-full flex-col space-y-1 text-sm font-semibold md:flex-row md:items-center md:space-x-4 md:space-y-0"
>
  <a
    wire:navigate
    href="{{ route("dashboard") }}"
    class="{{ request()->routeIs("dashboard") ? $activeClass : $inactiveClass }} {{ $baseClass }} {{ $desktopClass }} {{ $mobileClass }}"
  >
    <svg
      xmlns="http://www.w3.org/2000/svg"
      class="size-4"
      fill="none"
      viewBox="0 0 24 24"
      stroke-width="1.5"
      stroke="currentColor"
    >
      <path
        stroke-linecap="round"
        stroke-linejoin="round"
        d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z"
      />
    </svg>
    Dashboard
  </a>

  @if (Auth::user()->is_admin)
    <a
      wire:navigate
      href="{{ route("users.list") }}"
      class="{{ Str::contains(request()->path(), "users") ? $activeClass : $inactiveClass }} {{ $baseClass }} {{ $desktopClass }} {{ $mobileClass }}"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="size-4"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z"
        />
      </svg>
      Users
    </a>

    <a
      @if (auth()->user()?->is_admin)
          href="{{ route("projects-tasks") }}"
          class="{{ request()->routeIs("projects-tasks") ? $activeClass : $inactiveClass }} {{ $baseClass }} {{ $desktopClass }} {{ $mobileClass }}"
      @endif
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        fill="currentColor"
        class="size-4"
        viewBox="0 0 16 16"
      >
        <path
          fill-rule="evenodd"
          d="M2 2.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5V3a.5.5 0 0 0-.5-.5zM3 3H2v1h1z"
        />
        <path
          d="M5 3.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5M5.5 7a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1zm0 4a.5.5 0 0 0 0 1h9a.5.5 0 0 0 0-1z"
        />
        <path
          fill-rule="evenodd"
          d="M1.5 7a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H2a.5.5 0 0 1-.5-.5zM2 7h1v1H2zm0 3.5a.5.5 0 0 0-.5.5v1a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5v-1a.5.5 0 0 0-.5-.5zm1 .5H2v1h1z"
        />
      </svg>
      Projects & Tasks
    </a>

    <a
      wire:navigate
      href="{{ route("settings") }}"
      class="{{ Str::contains(request()->path(), "settings") ? $activeClass : $inactiveClass }} {{ $baseClass }} {{ $desktopClass }} {{ $mobileClass }}"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        class="size-4"
        fill="none"
        viewBox="0 0 24 24"
        stroke-width="1.5"
        stroke="currentColor"
      >
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z"
        />
        <path
          stroke-linecap="round"
          stroke-linejoin="round"
          d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z"
        />
      </svg>
      Settings
    </a>
  @endif
</nav>
