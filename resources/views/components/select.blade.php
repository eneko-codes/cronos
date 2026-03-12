{{-- resources/views/components/select.blade.php --}}

@props([
  'size' => 'md',
])

@php
  $sizeClasses = match ($size) {
    'sm' => 'h-8 px-2.5 py-1 text-xs',
    'md' => 'h-9 px-3 py-2 text-sm',
    'lg' => 'h-10 px-4 py-2.5 text-sm',
  };

  $baseClasses = 'rounded-lg border border-gray-300 bg-white text-gray-900 
                                focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none
                                disabled:cursor-not-allowed disabled:bg-gray-100 disabled:opacity-60
                                dark:border-gray-600 dark:bg-gray-800 dark:text-gray-100 
                                dark:focus:border-blue-400 dark:focus:ring-blue-400
                                dark:disabled:bg-gray-700';
@endphp

<select {{ $attributes->merge(['class' => "$baseClasses $sizeClasses"]) }}>
  {{ $slot }}
</select>
