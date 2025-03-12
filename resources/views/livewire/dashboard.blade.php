<!-- resources/views/livewire/dashboard.blade.php -->
<div>
  @livewire('user-page', ['id' => auth()->id()])
</div>
