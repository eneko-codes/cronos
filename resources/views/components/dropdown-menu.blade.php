<div x-data="{ open: false }" class="relative">
  <button
    @click="open = !open"
    @click.outside="open = false"
    class="flex items-center gap-2 rounded-md border border-gray-300 px-3 py-1.5 text-sm font-medium text-gray-700 hover:bg-gray-100 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-800"
  >
    <span class="flex items-center gap-2">{{ $trigger }}</span>
    <svg
      xmlns="http://www.w3.org/2000/svg"
      class="size-4 transition-transform duration-200"
      :class="{ 'rotate-180': open }"
      viewBox="0 0 20 20"
      fill="currentColor"
    >
      <path
        fill-rule="evenodd"
        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
      />
    </svg>
  </button>

  <div
    x-show="open"
    x-cloak
    x-transition:enter="transition duration-100 ease-out"
    x-transition:enter-start="scale-95 opacity-0"
    x-transition:enter-end="scale-100 opacity-100"
    x-transition:leave="transition duration-75 ease-in"
    x-transition:leave-start="scale-100 opacity-100"
    x-transition:leave-end="scale-95 opacity-0"
    class="absolute right-0 z-50 mt-1 w-auto max-w-sm min-w-[12rem] rounded-md border border-gray-200 bg-white p-2 shadow-lg dark:border-gray-700 dark:bg-gray-800"
  >
    {{ $slot }}
  </div>
</div>
