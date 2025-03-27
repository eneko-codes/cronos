@props([
    'active' => 'all',
    'counts' => [],
    'filters' => [],
    'onFilterChange' => null,
    'class' => '',
    'showCounts' => true,
])

<div {{ $attributes->merge(['class' => 'inline-flex w-fit gap-1 whitespace-nowrap rounded-lg border border-gray-200 bg-gray-100 p-1 text-xs font-semibold dark:border-gray-700 dark:bg-gray-800 ' . $class]) }}>
    @foreach ($filters as $filter => $label)
        @if (!isset($counts[$filter]) || $counts[$filter] > 0 || $filter === 'all')
            <button
                wire:click="{{ $onFilterChange ? $onFilterChange . "('" . $filter . "')" : '' }}"
                class="{{ $active === $filter ? 'bg-gray-50 text-gray-800 ring-1 ring-gray-200 dark:bg-gray-700 dark:text-gray-100 dark:ring-gray-700' : 'text-gray-600 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-3 py-1"
            >
                {{ $label }}
                @if($showCounts && isset($counts[$filter]))
                    <span class="font-light">{{ $counts[$filter] }}</span>
                @endif
            </button>
        @endif
    @endforeach
</div> 