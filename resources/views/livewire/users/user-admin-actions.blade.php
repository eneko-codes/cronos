<div class="grid grid-cols-1 gap-2 md:grid-cols-2">
  {{-- Admin Role Management --}}
  @if ($isAdmin && $canDemoteAdmin)
    <x-button
      wire:click="demoteFromAdmin"
      wire:confirm="Are you sure you want to remove admin rights from {{ $name }}?"
      class="w-full"
      size="md"
      variant="alert"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        class="size-4"
        viewBox="0 0 16 16"
      >
        <path
          d="M13.879 10.414a2.501 2.501 0 0 0-3.465 3.465zm.707.707-3.465 3.465a2.501 2.501 0 0 0 3.465-3.465m-4.56-1.096a3.5 3.5 0 1 1 4.949 4.95 3.5 3.5 0 0 1-4.95-4.95ZM11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"
        />
      </svg>
      <span wire:loading.remove.delay wire:target="demoteFromAdmin">
        Remove Admin Rights
      </span>
      <span
        wire:loading.delay
        wire:target="demoteFromAdmin"
        class="flex items-center gap-1"
      >
        <x-spinner size="4" />
      </span>
    </x-button>
  @elseif ($canPromoteToAdmin)
    <x-button
      wire:click="promoteToAdmin"
      wire:confirm="Are you sure you want to promote {{ $name }} to admin?"
      class="w-full"
      size="md"
      variant="primary"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        class="size-4"
        viewBox="0 0 16 16"
      >
        <path
          d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4m9.886-3.54c.18-.613 1.048-.613 1.229 0l.043.148a.64.64 0 0 0 .921.382l.136-.074c.561-.306 1.175.308.87.869l-.075.136a.64.64 0 0 0 .382.92l.149.045c.612.18.612 1.048 0 1.229l-.15.043a.64.64 0 0 0-.38.921l.074.136c.305.561-.309 1.175-.87.87l-.136-.075a.64.64 0 0 0-.92.382l-.045.149c-.18.612-1.048.612-1.229 0l-.043-.15a.64.64 0 0 0-.921-.38l-.136.074c-.561.305-1.175-.309-.87-.87l.075-.136a.64.64 0 0 0-.382-.92l-.148-.045c-.613-.18-.613-1.048 0-1.229l.148-.043a.64.64 0 0 0 .382-.921l-.074-.136c-.306-.561.308-1.175.869-.87l.136.075a.64.64 0 0 0 .92-.382zM14 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0"
        />
      </svg>
      <span wire:loading.remove.delay wire:target="promoteToAdmin">
        Promote to Admin
      </span>
      <span
        wire:loading.delay
        wire:target="promoteToAdmin"
        class="flex items-center gap-1"
      >
        <x-spinner size="4" />
      </span>
    </x-button>
  @endif

  {{-- Maintenance Role Management --}}
  @if ($canDemoteFromMaintenance)
    <x-button
      wire:click="demoteFromMaintenance"
      wire:confirm="Are you sure you want to remove maintenance role from {{ $name }}?"
      class="w-full"
      size="md"
      variant="alert"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        class="size-4"
        viewBox="0 0 16 16"
      >
        <path
          d="M13.879 10.414a2.501 2.501 0 0 0-3.465 3.465zm.707.707-3.465 3.465a2.501 2.501 0 0 0 3.465-3.465m-4.56-1.096a3.5 3.5 0 1 1 4.949 4.95 3.5 3.5 0 0 1-4.95-4.95ZM11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"
        />
      </svg>
      <span wire:loading.remove.delay wire:target="demoteFromMaintenance">
        Remove Maintenance Role
      </span>
      <span
        wire:loading.delay
        wire:target="demoteFromMaintenance"
        class="flex items-center gap-1"
      >
        <x-spinner size="4" />
      </span>
    </x-button>
  @elseif ($canPromoteToMaintenance)
    <x-button
      wire:click="promoteToMaintenance"
      wire:confirm="Are you sure you want to promote {{ $name }} to maintenance role?"
      class="w-full"
      size="md"
      variant="primary"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        fill="currentColor"
        class="size-4"
        viewBox="0 0 16 16"
      >
        <path
          d="M11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4m9.886-3.54c.18-.613 1.048-.613 1.229 0l.043.148a.64.64 0 0 0 .921.382l.136-.074c.561-.306 1.175.308.87.869l-.075.136a.64.64 0 0 0 .382.92l.149.045c.612.18.612 1.048 0 1.229l-.15.043a.64.64 0 0 0-.38.921l.074.136c.305.561-.309 1.175-.87.87l-.136-.075a.64.64 0 0 0-.92.382l-.045.149c-.18.612-1.048.612-1.229 0l-.043-.15a.64.64 0 0 0-.921-.38l-.136.074c-.561.305-1.175-.309-.87-.87l.075-.136a.64.64 0 0 0-.382-.92l-.148-.045c-.613-.18-.613-1.048 0-1.229l.148-.043a.64.64 0 0 0 .382-.921l-.074-.136c-.306-.561.308-1.175.869-.87l.136.075a.64.64 0 0 0 .92-.382zM14 12.5a1.5 1.5 0 1 0-3 0 1.5 1.5 0 0 0 3 0"
        />
      </svg>
      <span wire:loading.remove.delay wire:target="promoteToMaintenance">
        Promote to Maintenance
      </span>
      <span
        wire:loading.delay
        wire:target="promoteToMaintenance"
        class="flex items-center gap-1"
      >
        <x-spinner size="4" />
      </span>
    </x-button>
  @endif

  {{-- Tracking Management --}}
  @if ($isDoNotTrack && $canEnableTracking)
    <x-button
      wire:click="enableTracking"
      wire:confirm="Are you sure you want to enable tracking for {{ $name }}?"
      class="w-full"
      size="md"
      variant="success"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="currentColor"
        class="size-4"
      >
        <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" />
        <path
          fill-rule="evenodd"
          d="M1.323 11.447C2.811 6.976 7.028 3.75 12.001 3.75c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113-1.487 4.471-5.705 7.697-10.677 7.697-4.97 0-9.186-3.223-10.675-7.69a1.762 1.762 0 0 1 0-1.113ZM17.25 12a5.25 5.25 0 1 1-10.5 0 5.25 5.25 0 0 1 10.5 0Z"
          clip-rule="evenodd"
        />
      </svg>
      <span wire:loading.remove.delay wire:target="enableTracking">
        Enable Tracking
      </span>
      <span
        wire:loading.delay
        wire:target="enableTracking"
        class="flex items-center gap-1"
      >
        <x-spinner size="4" />
      </span>
    </x-button>
  @elseif ($canNotTrack)
    <x-button
      wire:click="doNotTrackUser"
      wire:confirm="Are you sure you want to disable tracking for {{ $name }}?"
      class="w-full"
      size="md"
      variant="warning"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="currentColor"
        class="size-4"
      >
        <path
          d="M3.53 2.47a.75.75 0 0 0-1.06 1.06l18 18a.75.75 0 1 0 1.06-1.06l-18-18ZM22.676 12.553a11.249 11.249 0 0 1-2.631 4.31l-3.099-3.099a5.25 5.25 0 0 0-6.71-6.71L7.759 4.577a11.217 11.217 0 0 1 4.242-.827c4.97 0 9.185 3.223 10.675 7.69.12.362.12.752 0 1.113Z"
        />
        <path
          d="M15.75 12c0 .18-.013.357-.037.53l-4.244-4.243A3.75 3.75 0 0 1 15.75 12ZM12.53 15.713l-4.243-4.244a3.75 3.75 0 0 0 4.244 4.243Z"
        />
        <path
          d="M6.75 12c0-.619.107-1.213.304-1.764l-3.1-3.1a11.25 11.25 0 0 0-2.63 4.31c-.12.362-.12.752 0 1.114 1.489 4.467 5.704 7.69 10.675 7.69 1.5 0 2.933-.294 4.242-.827l-2.477-2.477A5.25 5.25 0 0 1 6.75 12Z"
        />
      </svg>
      <span wire:loading.remove.delay wire:target="doNotTrackUser">
        Do Not Track User
      </span>
      <span
        wire:loading.delay
        wire:target="doNotTrackUser"
        class="flex items-center gap-1"
      >
        <x-spinner size="4" />
      </span>
    </x-button>
  @endif

  {{-- Archive User --}}
  @if ($canArchiveUser)
    <x-button
      wire:click="archiveUser"
      wire:confirm="Are you sure you want to archive {{ $name }}? This action cannot be undone. All their data (schedules, leaves, attendances, time entries, projects, tasks, and categories) will be permanently removed, and they will no longer have access to their account."
      class="w-full"
      size="md"
      variant="alert"
      type="button"
    >
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="16"
        height="16"
        fill="currentColor"
        class="bi bi-person-fill-slash"
        viewBox="0 0 16 16"
      >
        <path
          d="M13.879 10.414a2.501 2.501 0 0 0-3.465 3.465zm.707.707-3.465 3.465a2.501 2.501 0 0 0 3.465-3.465m-4.56-1.096a3.5 3.5 0 1 1 4.949 4.95 3.5 3.5 0 0 1-4.95-4.95ZM11 5a3 3 0 1 1-6 0 3 3 0 0 1 6 0m-9 8c0 1 1 1 1 1h5.256A4.5 4.5 0 0 1 8 12.5a4.5 4.5 0 0 1 1.544-3.393Q8.844 9.002 8 9c-5 0-6 3-6 4"
        />
      </svg>
      <span wire:loading.remove.delay wire:target="archiveUser">
        Archive User
      </span>
      <span
        wire:loading.delay
        wire:target="archiveUser"
        class="flex items-center gap-1"
      >
        <x-spinner size="4" />
      </span>
    </x-button>
  @endif
</div>
