@props([
    "active" => "all",
    "counts" => [],
    "filters" => [],
    "onFilterChange" => null,
    "class" => "",
    "showCounts" => true,
])

<div
  {{ $attributes->merge(["class" => "inline-flex w-fit gap-1 whitespace-nowrap rounded-lg border border-gray-200 bg-gray-100 p-1 text-xs font-medium dark:border-gray-700 dark:bg-gray-800 " . $class]) }}
>
  @foreach ($filters as $filter => $label)
    @if (! isset($counts[$filter]) || $counts[$filter] > 0 || $filter === "all")
      <button
        @if ($onFilterChange)
            wire:click="{{ $onFilterChange . "('" . $filter . "')" }}"
        @endif
        class="{{ $active === $filter ? "bg-white text-blue-600 shadow-sm dark:bg-gray-700 dark:text-blue-400" : "text-gray-600 hover:bg-gray-200/50 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200" }} relative rounded-md px-3 py-1.5 transition-colors"
      >
        {{ $label }}
        @if ($showCounts && isset($counts[$filter]))
          <span class="font-light">{{ $counts[$filter] }}</span>
        @endif
      </button>
    @endif
  @endforeach
</div>
