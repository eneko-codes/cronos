<!-- resources/views/components/buttons.blade.php -->

@props(['variant' => 'default', 'size' => 'md', 'disabled' => false])

@php
  $variantClasses = [
    'default' => 'bg-gray-200/75 text-gray-800 hover:bg-gray-200 dark:bg-gray-200 dark:hover:bg-gray-100',
    'primary' => 'bg-purple-200/75 text-purple-800 hover:bg-purple-200 dark:bg-purple-200 dark:hover:bg-purple-100',
    'alert' => 'bg-red-200/75 text-red-800 hover:bg-red-200 dark:bg-red-200 dark:hover:bg-red-100',
    'success' => 'bg-green-200/75 text-green-800 hover:bg-green-200 dark:bg-green-200 dark:hover:bg-green-100',
    'warning' => 'bg-yellow-200/75 text-yellow-800 hover:bg-yellow-200 dark:bg-yellow-200 dark:hover:bg-yellow-100',
    'info' => 'bg-blue-200/75 text-blue-800 hover:bg-blue-200 dark:bg-blue-200 dark:hover:bg-blue-100',
  ];

  $sizeClasses = [
    'xs' => 'px-1.5 py-1 text-xs',
    'sm' => 'px-2 py-1 text-sm',
    'md' => 'px-3 py-1.5 text-sm',
    'lg' => 'px-4 py-2 text-sm',
  ];

  $variantClass = $variantClasses[$variant] ?? $variantClasses['default'];
  $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];

  // Always apply disabled classes if the disabled prop or attribute is true
  $disabledClass = $disabled || $attributes->has('disabled') ? 'cursor-not-allowed opacity-50' : '';

  // Base classes that will be merged
  $baseClasses = "inline-flex whitespace-nowrap h-fit w-fit flex-row gap-2 shadow-sm items-center justify-center rounded-lg font-semibold {$variantClass} {$sizeClass} {$disabledClass}";
@endphp

<button
  {{
    $attributes->merge([
      'class' => $baseClasses,
      'disabled' => $disabled || $attributes->has('disabled'),
    ])
  }}
>
  {{ $slot }}
</button>
