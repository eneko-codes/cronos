<!-- resources/views/components/logo.blade.php -->

@props(['size' => 'md'])

@php
  $sizeClasses = [
    'xs' => 'size-8',
    'sm' => 'size-10',
    'md' => 'size-14',
    'lg' => 'size-16',
    'xl' => 'size-20',
  ];

  $sizeClass = $sizeClasses[$size] ?? $sizeClasses['md'];
@endphp

<img
  src="{{ asset('logo.png') }}"
  class="{{ $sizeClass }} {{ $attributes->get('class') }} block object-contain"
  alt="Cronos Logo"
/>
