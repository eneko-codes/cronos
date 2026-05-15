@props([
  'variant' => 'info',
])

@php
  $variantClasses = match ($variant) {
    'warning' => 'border-yellow-400 bg-yellow-50 text-yellow-800 dark:border-yellow-600 dark:bg-yellow-900/30 dark:text-yellow-200',
    'danger' => 'border-red-400 bg-red-50 text-red-800 dark:border-red-600 dark:bg-red-900/30 dark:text-red-200',
    'success' => 'border-green-400 bg-green-50 text-green-800 dark:border-green-600 dark:bg-green-900/30 dark:text-green-200',
    'info' => 'border-blue-400 bg-blue-50 text-blue-800 dark:border-blue-600 dark:bg-blue-900/30 dark:text-blue-200',
    default => 'border-gray-300 bg-gray-50 text-gray-800 dark:border-gray-600 dark:bg-gray-800 dark:text-gray-200',
  };

  $baseClasses = 'rounded-lg border p-3 text-sm';
@endphp

<div
  {{ $attributes->merge(['class' => $baseClasses . ' ' . $variantClasses]) }}
>
  @if (! $title->isEmpty())
    <p class="font-medium">{{ $title }}</p>
  @endif

  @if (! $slot->isEmpty())
    <p>
      {{ $slot }}
    </p>
  @endif
</div>
