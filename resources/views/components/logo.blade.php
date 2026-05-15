@props(['size' => 'md'])

@php
  $sizeClasses = [
    'xs' => 'text-base',
    'sm' => 'text-lg',
    'md' => 'text-xl',
    'lg' => 'text-3xl',
    'xl' => 'text-4xl',
  ];

  $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
@endphp

<span
  {{ $attributes->class([$sizeClass, 'font-semibold tracking-tight text-gray-900 dark:text-gray-100']) }}
>
  Cronos
</span>
