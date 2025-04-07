@props([
  'disabled' => false,
])

<select
  {{ $disabled ? 'disabled' : '' }}
  {!!
    $attributes->merge([
      'class' => 'block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-blue-500 focus:outline-none focus:ring-blue-500 sm:text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 dark:focus:border-blue-400 dark:focus:ring-blue-400',
    ])
  !!}
>
  {{ $slot }}
</select>
