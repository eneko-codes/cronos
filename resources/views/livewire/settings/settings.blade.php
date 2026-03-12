<div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
  {{-- Global Notification Settings - Left Column --}}
  <section class="relative lg:col-span-1">
    <livewire:settings.global-notification-settings />
  </section>

  {{-- Right Column - Stacked Components --}}
  <div class="flex flex-col gap-3 lg:col-span-1">
    {{-- Data Synchronization Settings --}}
    <section class="relative">
      <livewire:settings.data-sync-settings />
    </section>

    {{-- Monitoring & API Health Check - Side by Side on Desktop --}}
    <div class="grid grid-cols-1 gap-3 lg:grid-cols-2">
      {{-- Monitoring Section --}}
      <section class="relative">
        <livewire:settings.monitoring-dashboards />
      </section>

      {{-- API Health Check --}}
      <section class="relative">
        <livewire:settings.api-health-check />
      </section>
    </div>
  </div>
</div>
