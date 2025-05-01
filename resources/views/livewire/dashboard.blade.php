<!-- resources/views/livewire/dashboard.blade.php -->
<div class="flex flex-col gap-6">
  {{-- Widgets Section --}}
  @livewire('user-dashboard-widgets')

  {{-- Detailed User Data Table Section --}}
  {{-- Load UserDashboard WITHOUT an ID, so it mounts using Auth::user() --}}
  @livewire('user-dashboard')
</div>
