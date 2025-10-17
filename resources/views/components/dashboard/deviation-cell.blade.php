@props([
  'deviation',
  'isFutureDate' => false,
])

@if ($deviation && $deviation->shouldDisplay && ! $isFutureDate)
  <x-tooltip :text="$deviation->tooltip">
    <span class="{{ $deviation->getTextClass() }}">
      {{ $deviation->getFormattedPercentage() }}
    </span>
  </x-tooltip>
@endif
