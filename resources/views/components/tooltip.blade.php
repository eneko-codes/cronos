<div
  x-data="{
    open: false,
    tooltipId: $id('tooltip'),
    debouncedUpdatePosition: null,
    debounce(func, wait) {
      let timeout
      return (...args) => {
        clearTimeout(timeout)
        timeout = setTimeout(() => func.apply(this, args), wait)
      }
    },
    initPositionUpdater() {
      // Initialize debounced function here so 'this' context is correct
      this.debouncedUpdatePosition = this.debounce(this.updatePosition, 150)
      window.addEventListener('resize', this.debouncedUpdatePosition)
      // Use capture phase for scroll to catch events in nested scroll containers
      document.addEventListener('scroll', this.debouncedUpdatePosition, true)
    },
    destroyPositionUpdater() {
      window.removeEventListener('resize', this.debouncedUpdatePosition)
      document.removeEventListener('scroll', this.debouncedUpdatePosition, true)
    },
    showTooltip() {
      this.open = true
      // Ensure position is calculated when shown
      this.$nextTick(() => this.updatePosition())
    },
    hideTooltip() {
      this.open = false
    },
    updatePosition() {
      if (! this.open || ! this.$refs.tooltip) return

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
  x-init="initPositionUpdater()"
  x-destroy="destroyPositionUpdater()"
  @mouseenter="showTooltip()"
  @mouseleave="hideTooltip()"
  @focus="showTooltip()"
  @blur="hideTooltip()"
  :aria-describedby="tooltipId"
  tabindex="0"
  class="relative w-fit"
>
  {{ $slot }}
  <div
    x-ref="tooltip"
    :id="tooltipId"
    role="tooltip"
    x-show="open"
    x-transition:enter="transition duration-100 ease-out"
    x-transition:enter-start="scale-95 opacity-0"
    x-transition:enter-end="scale-100 opacity-100"
    x-transition:leave="transition duration-75 ease-in"
    x-transition:leave-start="scale-100 opacity-100"
    x-transition:leave-end="scale-95 opacity-0"
    x-cloak
    class="pointer-events-none fixed z-[9999] h-fit w-fit max-w-sm rounded border border-gray-300 bg-gray-100 p-2 text-xs font-medium whitespace-normal text-gray-700 shadow-lg dark:border-gray-500 dark:bg-gray-600 dark:text-gray-100"
  >
    @if (isset($text))
      {{ $text }}
    @else
      {{ $attributes->get('text') }}
    @endif
  </div>
</div>
