@props([
  'variant' => 'info',
])

@php
  $variantClasses = match ($variant) {
    'warning' => 'border-orange-300 bg-orange-50 text-orange-800 dark:border-orange-600 dark:bg-orange-900/30 dark:text-orange-200',
    'danger' => 'border-red-300 bg-red-50 text-red-800 dark:border-red-600 dark:bg-red-900/30 dark:text-red-200',
    'success' => 'border-green-300 bg-green-50 text-green-800 dark:border-green-600 dark:bg-green-900/30 dark:text-green-200',
    'info' => 'border-blue-300 bg-blue-50 text-blue-800 dark:border-blue-600 dark:bg-blue-900/30 dark:text-blue-200',
    default => 'border-gray-300 bg-gray-50 text-gray-800 dark:border-gray-600 dark:bg-gray-900/30 dark:text-gray-200', // Default/neutral
  };

  $baseClasses = 'rounded-md border p-3 text-xs';
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
