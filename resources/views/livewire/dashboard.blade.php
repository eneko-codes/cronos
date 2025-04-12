<!-- resources/views/livewire/dashboard.blade.php -->
<div>
  @livewire('user-dashboard', ['id' => auth()->id()])
</div>
