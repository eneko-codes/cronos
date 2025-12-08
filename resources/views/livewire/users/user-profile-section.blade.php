<section>
  <h3
    class="mb-3 flex items-center gap-2 text-sm font-semibold tracking-wide text-gray-500 uppercase dark:text-gray-400"
  >
    <svg
      xmlns="http://www.w3.org/2000/svg"
      viewBox="0 0 20 20"
      fill="currentColor"
      class="size-4"
    >
      <path
        d="M10 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM3.465 14.493a1.23 1.23 0 0 0 .41 1.412A9.957 9.957 0 0 0 10 18c2.31 0 4.438-.784 6.131-2.1.43-.333.604-.903.408-1.41a7.002 7.002 0 0 0-13.074.003Z"
      />
    </svg>
    Profile
  </h3>
  <div
    class="rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900"
  >
    {{-- Email with verification badge --}}
    <div class="mb-3 flex items-center gap-2">
      <span class="text-sm font-medium text-gray-500 dark:text-gray-400">
        Email:
      </span>
      <span class="text-sm text-gray-800 dark:text-gray-200">
        {{ $this->email }}
      </span>
      @if ($this->isEmailVerified)
        <x-badge variant="success" size="sm">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 16 16"
            fill="currentColor"
            class="mr-1 size-3"
          >
            <path
              fill-rule="evenodd"
              d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z"
              clip-rule="evenodd"
            />
          </svg>
          Verified
        </x-badge>
      @else
        <x-badge variant="warning" size="sm">
          <svg
            xmlns="http://www.w3.org/2000/svg"
            viewBox="0 0 16 16"
            fill="currentColor"
            class="mr-1 size-3"
          >
            <path
              fill-rule="evenodd"
              d="M6.701 2.25c.577-1 2.02-1 2.598 0l5.196 9a1.5 1.5 0 0 1-1.299 2.25H2.804a1.5 1.5 0 0 1-1.3-2.25l5.197-9ZM8 4a.75.75 0 0 1 .75.75v3a.75.75 0 1 1-1.5 0v-3A.75.75 0 0 1 8 4Zm0 8a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z"
              clip-rule="evenodd"
            />
          </svg>
          Unverified
        </x-badge>
      @endif
    </div>

    <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
      @foreach ($this->details as $label => $value)
        <div class="flex flex-col">
          <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
            {{ $label }}
          </span>
          <span class="text-sm text-gray-800 dark:text-gray-200">
            {{ $value }}
          </span>
        </div>
      @endforeach
    </div>
  </div>
</section>
