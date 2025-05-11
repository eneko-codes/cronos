@props([
  'badges',
])

@if (! empty($badges))
  <div class="flex flex-row flex-wrap items-center gap-1">
    @foreach ($badges as $badge)
      <x-tooltip :text="$badge['tooltip']">
        <x-badge size="sm" :variant="$badge['variant']">
          {{ $badge['text'] }}
        </x-badge>
      </x-tooltip>
    @endforeach
  </div>
@endif
