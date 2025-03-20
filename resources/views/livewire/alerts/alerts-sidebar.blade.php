<!-- Alerts sidebar -->
<div>
    @if ($isOpen)
        <!-- Backdrop -->
        <div 
            wire:key="sidebar-backdrop"
            wire:click="$set('isOpen', false)"
            @keydown.escape.window="$wire.set('isOpen', false)"
            class="fixed inset-0 z-40 bg-gray-500/75 backdrop-blur-sm transition-opacity duration-300 dark:bg-gray-900/80"
        ></div>

        <!-- Sidebar -->
        <div 
            wire:key="sidebar-content"
            class="fixed top-0 right-0 z-50 flex h-full w-full max-w-md flex-col border-l border-gray-200 bg-white shadow-xl transition-transform dark:border-gray-800 dark:bg-gray-900"
        >
            <!-- Header -->
            <div class="flex h-12 items-center justify-between border-b border-gray-100 px-4 dark:border-gray-800">
                <!-- Title -->
                <div class="flex items-center gap-2">
                  <svg class="size-6 text-gray-500 dark:text-gray-400 bg-gray-100 rounded-full p-1 dark:bg-gray-800" xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 16 16">
                    <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2M8 1.918l-.797.161A4 4 0 0 0 4 6c0 .628-.134 2.197-.459 3.742-.16.767-.376 1.566-.663 2.258h10.244c-.287-.692-.502-1.49-.663-2.258C12.134 8.197 12 6.628 12 6a4 4 0 0 0-3.203-3.92zM14.22 12c.223.447.481.801.78 1H1c.299-.199.557-.553.78-1C2.68 10.2 3 6.88 3 6c0-2.42 1.72-4.44 4.005-4.901a1 1 0 1 1 1.99 0A5 5 0 0 1 13 6c0 .88.32 4.2 1.22 6"/>
                  </svg>
                  <h2 class="text-md font-semibold text-gray-900 dark:text-white">Your Alerts</h2>
                </div>
                
                <!-- Actions -->
                <div class="flex items-center gap-2">
                    @if($this->hasAlerts)
                        <button 
                            wire:click="resolveAllAlerts"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-200 dark:hover:text-indigo-100"
                        >
                            Resolve All
                        </button>
                    @endif
                    
                    <button 
                        wire:click="$set('isOpen', false)"
                        class="rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    >
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Tabs -->
            <div class="flex border-b text-xs font-semibold border-gray-100 bg-gray-50 dark:border-gray-800 dark:bg-gray-900">
                <button 
                    wire:click="switchTab('active')"
                    class="flex flex-1 items-center gap-2 justify-center transition-colors {{ $activeTab === 'active' ? 'border-b-2 border-indigo-500 bg-white text-indigo-600 dark:bg-gray-800 dark:text-indigo-200' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}"
                >
                    <span>Active</span>
                    @if($this->alertCount > 0)
                        <!-- Active alerts count with rounded background -->
                        <span class="rounded-full bg-indigo-100 px-2 py-0.5 text-indigo-600 dark:bg-indigo-900/50 dark:text-indigo-200">
                            {{ $this->alertCount }}
                        </span>
                    @endif
                </button>
                <button 
                    wire:click="switchTab('resolved')"
                    class="flex flex-1 gap-2 items-center justify-center px-4 py-3 transition-colors {{ $activeTab === 'resolved' ? 'border-b-2 border-indigo-500 bg-white text-indigo-600 dark:bg-gray-800 dark:text-indigo-200' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}"
                >
                    <span>Resolved</span>
                    @if($this->resolvedAlertCount > 0)
                        <!-- Resolved alerts count with rounded background -->
                        <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-gray-800/50 dark:text-gray-200">
                            {{ $this->resolvedAlertCount }}
                        </span>
                    @endif
                </button>
                <button 
                    wire:click="switchTab('settings')"
                    class="flex flex-1 gap-2 items-center justify-center px-4 py-3 transition-colors {{ $activeTab === 'settings' ? 'border-b-2 border-indigo-500 bg-white text-indigo-600 dark:bg-gray-800 dark:text-indigo-200' : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-300 dark:hover:bg-gray-800 dark:hover:text-gray-100' }}"
                >
                    <span>Settings</span>
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 0 1 0-.255c.007-.378-.138-.75-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </button>
            </div>

            <!-- Content -->
            <div class="flex-1 overflow-y-auto p-4">
                @if($activeTab === 'active')
                    @if($this->hasAlerts)
                        <div class="space-y-4">
                            @foreach($this->alerts as $alert)
                                <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm transition-all hover:shadow dark:border-gray-800 dark:bg-gray-800">
                                    <div class="flex flex-col items-start gap-1">
                                          <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $alert->title }}</h3>
                                          <p class="text-xs text-gray-500 dark:text-gray-400">{{ $alert->message }}</p>
                                          <div class="flex items-center justify-between w-full">
                                            <span class="block text-xs text-gray-400 dark:text-gray-500">{{ $alert->created_at->diffForHumans() }}</span>
                                              <button 
                                                  wire:click="resolveAlert({{ $alert->id }})"
                                                  class="text-xs font-medium text-indigo-600 hover:bg-indigo-50 hover:text-indigo-700 dark:text-indigo-200 dark:hover:bg-indigo-900/50 dark:hover:text-indigo-100"
                                              >
                                                  Resolve
                                              </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <span class="flex items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                            No active alerts
                        </span>
                    @endif
                @else
                    @if($activeTab === 'resolved')
                        @if($this->hasResolvedAlerts)
                            <div class="space-y-4">
                                @foreach($this->resolvedAlerts as $alert)
                                    <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm transition-all hover:shadow dark:border-gray-800 dark:bg-gray-800">
                                        <div class="flex flex-col items-start gap-1">
                                            <h3 class="text-sm font-medium text-gray-900 dark:text-white">{{ $alert->title }}</h3>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $alert->message }}</p>
                                            <div class="flex items-center justify-between w-full">
                                                <span class="block text-xs text-gray-400 dark:text-gray-500">Resolved {{ $alert->resolved_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <div class="flex h-40 items-center justify-center text-sm text-gray-500 dark:text-gray-400">
                                No resolved alerts
                            </div>
                        @endif
                    @else
                        <!-- Settings Tab Content -->
                        <div class="space-y-6">
                            <div class="rounded-lg border border-gray-100 bg-white p-4 shadow-sm dark:border-gray-800 dark:bg-gray-800">
                                <h3 class="mb-4 text-sm font-semibold text-gray-900 dark:text-white">Notification Settings</h3>
                                
                                <!-- Notification Toggle -->
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5 text-gray-600 dark:text-gray-400">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                                            @if ($this->isNotificationsMuted)
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M 4 4 L 20 20" />
                                            @endif
                                        </svg>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">Notifications</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ $this->isNotificationsMuted ? 'Currently muted' : 'Currently active' }}
                                            </p>
                                        </div>
                                    </div>
                                    <label class="relative inline-flex cursor-pointer items-center">
                                        <input
                                            type="checkbox"
                                            class="peer sr-only"
                                            wire:click="toggleNotifications"
                                            {{ !$this->isNotificationsMuted ? 'checked' : '' }}
                                        />
                                        <div
                                            class="peer h-6 w-11 rounded-full bg-gray-200 after:absolute after:left-[2px] after:top-[2px] after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:bg-blue-600 peer-checked:after:translate-x-full peer-checked:after:border-white dark:bg-gray-700"
                                        ></div>
                                    </label>
                                </div>
                            </div>
                        </div>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
