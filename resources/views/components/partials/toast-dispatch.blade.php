@if (session('toast'))
  <script>
    (function () {
      const dispatchToast = function () {
        if (window.Livewire && typeof window.Livewire.dispatch === 'function') {
          window.Livewire.dispatch('add-toast', {
            message: @js(session('toast')['message']),
            variant: @js(session('toast')['variant'] ?? 'default'),
          });
        }
      };

      // Check if Livewire is already initialized
      if (window.Livewire && window.Livewire.initialRenderIsFinished) {
        // Livewire is fully initialized, dispatch immediately
        dispatchToast();
      } else if (window.Livewire) {
        // Livewire exists but might not be fully ready, wait for initialization
        document.addEventListener('livewire:initialized', dispatchToast, {
          once: true,
        });
        // Also try immediately in case it's ready
        setTimeout(dispatchToast, 100);
      } else {
        // Livewire not loaded yet, wait for init
        document.addEventListener(
          'livewire:init',
          function () {
            // Wait a bit for Livewire to be fully ready
            setTimeout(dispatchToast, 100);
          },
          { once: true }
        );
      }
    })();
  </script>
@endif
