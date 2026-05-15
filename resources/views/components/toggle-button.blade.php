@props([
  'active' => false,
  'label' => '',
  'onClick' => null,
])

<button
  type="button"
  {{ $attributes }}
  class="{{ $active ? 'border-gray-800 bg-gray-50 text-gray-800 dark:border-gray-100 dark:bg-gray-700 dark:text-gray-100' : 'text-gray-600 hover:border-gray-200 hover:bg-gray-200/50 hover:text-gray-800 dark:text-gray-400 dark:hover:bg-gray-700/50 dark:hover:text-gray-200' }} relative inline-flex items-center gap-1.5 rounded border border-gray-200 bg-gray-100 px-2 py-1 text-xs font-semibold whitespace-nowrap dark:border-gray-700 dark:bg-gray-800"
>
  <div class="flex items-center gap-1">
    {{ $slot }}
    {{ $label }}
  </div>
</button>
