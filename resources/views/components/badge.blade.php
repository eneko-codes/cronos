<!-- resources/views/components/badge.blade.php -->

@props(['variant' => 'default', 'size' => 'md'])

@php
  $variantClass = match ($variant) {
    'primary' => 'border-purple-300 bg-purple-100/75 text-purple-700 dark:bg-purple-100',
    'alert' => 'border-red-300 bg-red-100/75 text-red-700 dark:bg-red-100',
    'warning' => 'border-yellow-300 bg-yellow-100/75 text-yellow-700 dark:bg-yellow-100',
    'info' => 'border-blue-300 bg-blue-100/75 text-blue-700 dark:bg-blue-100',
    'success' => 'border-green-300 bg-green-100/75 text-green-700 dark:bg-green-100',
    default => 'border-gray-300 bg-gray-100/75 text-gray-700 dark:bg-gray-100',
  };

  $sizeClass = match ($size) {
    'sm' => 'px-1.5 py-0.5 text-xs',
    'md' => 'px-2 py-1 text-sm',
    'lg' => 'px-2.5 py-1.5 text-base',
  };

  $globalClass = 'inline-flex h-fit w-fit items-center rounded-xl border leading-none font-semibold whitespace-nowrap';
@endphp

<span
  {{ $attributes->merge(['class' => "$globalClass $variantClass $sizeClass"]) }}
>
  {{ $slot }}
</span>
