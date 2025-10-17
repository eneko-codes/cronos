<!-- Header Section -->
<div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center">
  <h1 class="text-xl font-bold">{{ $this->user->name }}</h1>

  <!-- User Badges -->
  <x-user-badges :badges="$this->allBadges" />
</div>
