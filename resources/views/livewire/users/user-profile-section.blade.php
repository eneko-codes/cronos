<div
  class="rounded-lg border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-800"
>
  <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
    {{-- Status --}}
    <div class="flex flex-col gap-1">
      <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Status
      </span>
      <div>
        @if ($this->isActive)
          <x-badge variant="success" size="sm">Active</x-badge>
        @else
          <x-badge variant="alert" size="sm">Inactive</x-badge>
        @endif
      </div>
    </div>

    @foreach ($this->details as $label => $value)
      <div class="flex flex-col gap-1">
        <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
          {{ $label }}
        </span>
        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
          {{ $value }}
        </span>
      </div>
    @endforeach

    {{-- Created At with tooltip --}}
    <div class="flex flex-col gap-1">
      <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Created At
      </span>
      <x-tooltip text="{{ $this->createdAt->format('Y-m-d H:i') }}">
        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
          {{ $this->createdAt->diffForHumans() }}
        </span>
      </x-tooltip>
    </div>

    {{-- Updated At with tooltip --}}
    <div class="flex flex-col gap-1">
      <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
        Updated At
      </span>
      <x-tooltip text="{{ $this->updatedAt->format('Y-m-d H:i') }}">
        <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
          {{ $this->updatedAt->diffForHumans() }}
        </span>
      </x-tooltip>
    </div>
  </div>
</div>
