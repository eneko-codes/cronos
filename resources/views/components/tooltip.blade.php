<div x-data="{ open: false }" class="relative w-fit">
  <span
    x-ref="trigger"
    @mouseenter="open = true"
    @mouseleave="open = false"
    @focus="open = true"
    @blur="open = false"
    tabindex="0"
    class="cursor-help focus:outline-none"
    :aria-describedby="'tooltip-' + $id('tooltip')"
  >
    {{ $slot }}
  </span>
  <span
    :id="$id('tooltip')"
    x-show="open"
    x-anchor.bottom.offset.8="$refs.trigger"
    x-transition.opacity.duration.150ms
    class="pointer-events-none absolute z-50 w-max max-w-xs min-w-[8rem] rounded-lg border border-gray-200 bg-white/95 px-3 py-2 text-xs text-gray-900 shadow-md backdrop-blur select-none dark:border-gray-700 dark:bg-gray-900/95 dark:text-white"
    role="tooltip"
    style="display: none"
  >
    @if (isset($text))
      {{ $text }}
    @else
      {{ $attributes->get('text') }}
    @endif
  </span>
</div>
