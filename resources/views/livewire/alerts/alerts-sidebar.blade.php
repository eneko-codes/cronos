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
                @endif
            </div>
        </div>
    @endif
</div>
