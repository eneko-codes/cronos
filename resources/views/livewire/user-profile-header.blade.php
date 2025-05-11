<!-- Header Section -->
<div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center">
  <h1 class="text-xl font-bold">{{ $user->name }}</h1>

  <!-- User Badges -->
  <div class="flex flex-row flex-wrap items-center gap-1">
    @foreach ($this->allBadges as $badge)
      <x-tooltip :text="$badge['tooltip']">
        <x-badge size="sm" :variant="$badge['variant']">
          @if ($badge['isMissing'] && isset($badge['icon']))
            <svg
              class="{{ $badge['variant'] === 'alert' ? 'text-red-500 dark:text-red-400' : '' }} mr-1 -ml-0.5 inline-block h-3.5 w-3.5 align-middle"
              xmlns="http://www.w3.org/2000/svg"
              fill="none"
              viewBox="0 0 24 24"
              stroke-width="1.5"
              stroke="currentColor"
            >
              <path
                stroke-linecap="round"
                stroke-linejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"
              />
            </svg>
          @endif

          {{ $badge['text'] }}
        </x-badge>
      </x-tooltip>
    @endforeach
  </div>
</div>
