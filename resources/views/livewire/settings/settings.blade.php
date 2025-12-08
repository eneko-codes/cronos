<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
  {{-- Monitoring Section --}}
  <section class="relative lg:col-span-1">
    <livewire:settings.monitoring-dashboards />
  </section>

  {{-- API Health Check --}}
  <section class="relative lg:col-span-1">
    <livewire:settings.api-health-check />
  </section>

  {{-- Data Synchronization Settings --}}
  <section class="relative sm:col-span-2 lg:col-span-2">
    <livewire:settings.data-sync-settings />
  </section>

  {{-- Notification Settings Section --}}
  <section class="relative sm:col-span-2 lg:col-span-2">
    <livewire:settings.global-notification-settings />
  </section>
</div>
