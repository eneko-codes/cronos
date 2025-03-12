<div
  x-data="{
    updatePosition() {
      const tooltip = this.$refs.tooltip
      const trigger = this.$el
      const viewport = {
        width: document.documentElement.clientWidth,
        height: document.documentElement.clientHeight,
        scrollX: window.scrollX,
        scrollY: window.scrollY,
      }
      const triggerRect = trigger.getBoundingClientRect()
      const tooltipRect = tooltip.getBoundingClientRect()
      tooltip.style.position = 'fixed'
      tooltip.style.top = null
      tooltip.style.bottom = null
      tooltip.style.left = null
      tooltip.style.right = null
      tooltip.style.transform = null
      let top = triggerRect.bottom + 5
      let left = triggerRect.left + triggerRect.width / 2
      if (top + tooltipRect.height > viewport.height) {
        top = triggerRect.top - tooltipRect.height - 5
      }
      if (left + tooltipRect.width > viewport.width) {
        left = viewport.width - tooltipRect.width - 5
      }
      if (left < 5) {
        left = 5
      }
      tooltip.style.top = `${top}px`
      tooltip.style.left = `${left}px`
    },
  }"
  x-init="
    window.addEventListener('resize', updatePosition)
    document.addEventListener('scroll', updatePosition, true)
  "
  @mouseover="updatePosition()"
  class="group relative w-fit"
>
  {{ $slot }}
  <div
    x-ref="tooltip"
    x-cloak
    class="pointer-events-none invisible fixed z-[9999] h-fit w-fit max-w-sm whitespace-normal rounded border border-gray-300 bg-gray-100 p-2 text-xs font-medium text-gray-700 shadow-lg group-hover:visible dark:border-gray-500 dark:bg-gray-600 dark:text-gray-100"
  >
    @if (isset($text))
      {{ $text }}
    @else
      {{ $attributes->get('text') }}
    @endif
  </div>
</div>
