@props([
    'active' => false,
    'label' => '',
    'onClick' => null,
])

<button
    type="button"
    wire:click="{{ $onClick }}"
    class="{{ $active ? 'bg-gray-50 text-gray-800 border-gray-800 dark:bg-gray-700 dark:text-gray-100 dark:border-gray-100' : 'text-gray-600 hover:bg-gray-200/50 hover:border-gray-200 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative rounded px-2 py-1 text-xs font-semibold inline-flex items-center gap-1.5 whitespace-nowrap border border-gray-200 bg-gray-100 dark:border-gray-700 dark:bg-gray-800"
>
    <div class="flex items-center gap-1">
        {{ $slot }}
        {{ $label }}
    </div>
</button> 