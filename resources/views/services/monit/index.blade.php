<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Monit (System & Service Monitoring)') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{ activeTab: 'services', showAddService: false, showAddAlert: false }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="p-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-900/30 dark:border-emerald-800 dark:text-emerald-300">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error') || isset($error))
                <div class="p-4 rounded-lg bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-900/30 dark:border-rose-800 dark:text-rose-300">
                    <div class="flex items-center gap-2">
                        <svg class="w-5 h-5 text-rose-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('error') ?? $error }}</span>
                    </div>
                </div>
            @endif

            {{-- Monit Service Status & Controls Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            Monit Daemon Status
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Autonomous monitoring, proactive health-checking, and automatic process recovery.</p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @php
                            $rawStatus = is_array($status) ? ($status['status'] ?? 'disabled') : 'disabled';
                            $isRunning = $rawStatus === 'running';
                            $isDisabled = $rawStatus === 'disabled';
                        @endphp

                        @if($isRunning)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                <span class="w-2 h-2 mr-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Running
                            </span>
                        @elseif($isDisabled)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Disabled
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                Stopped
                            </span>
                        @endif

                        @if(!auth()->user()->isReadOnly())
                            @if(!$isRunning && !$isDisabled)
                                <form action="{{ route('services.monit.action', [$firewall, 'start']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium transition shadow-sm">
                                        Start
                                    </button>
                                </form>
                            @endif

                            @if($isRunning)
                                <form action="{{ route('services.monit.action', [$firewall, 'stop']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-medium transition shadow-sm">
                                        Stop
                                    </button>
                                </form>
                            @endif

                            <form action="{{ route('services.monit.action', [$firewall, 'restart']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-medium transition shadow-sm">
                                    Restart
                                </button>
                            </form>

                            <form action="{{ route('services.monit.action', [$firewall, 'reconfigure']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded text-xs font-medium transition shadow-sm">
                                    Apply / Reconfigure
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Status Summary Grid --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service State</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ ($settings['enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled' }}
                        </p>
                    </div>
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Poll Interval</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ $settings['interval'] ?? '120' }}s
                        </p>
                    </div>
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Monitored Items</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ count($services) }}
                        </p>
                    </div>
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Alert Targets</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ count($alerts) }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Tabs Header --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-6 overflow-x-auto">
                    <button @click="activeTab = 'services'"
                            :class="activeTab === 'services' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Monitored Services ({{ count($services) }})
                    </button>
                    <button @click="activeTab = 'alerts'"
                            :class="activeTab === 'alerts' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                        Alerts ({{ count($alerts) }})
                    </button>
                    <button @click="activeTab = 'tests'"
                            :class="activeTab === 'tests' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                        </svg>
                        Test Conditions ({{ count($tests) }})
                    </button>
                    <button @click="activeTab = 'settings'"
                            :class="activeTab === 'settings' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        General Settings
                    </button>
                </nav>
            </div>

            {{-- TAB 1: Monitored Services --}}
            <div x-show="activeTab === 'services'" class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">Monitored Services & Targets</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Processes, filesystem partitions, host availability, and custom service definitions monitored by Monit.</p>
                    </div>
                    @if(!auth()->user()->isReadOnly())
                        <button @click="showAddService = !showAddService"
                                class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold flex items-center gap-1.5 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span x-text="showAddService ? 'Cancel' : 'Add Service'">Add Service</span>
                        </button>
                    @endif
                </div>

                {{-- Add Service Form --}}
                @if(!auth()->user()->isReadOnly())
                    <div x-show="showAddService" x-cloak class="bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                        <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            New Monitored Service
                        </h5>
                        <form action="{{ route('services.monit.services.store', $firewall) }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Service Name *</label>
                                    <input type="text" name="name" required class="pf-input text-xs w-full" placeholder="e.g. nginx_process, root_disk">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Service Type *</label>
                                    <select name="type" required class="pf-input text-xs w-full">
                                        <option value="process">Process (PID or pattern)</option>
                                        <option value="system">System (CPU / Memory / Load)</option>
                                        <option value="filesystem">Filesystem (Disk Usage)</option>
                                        <option value="custom">Custom (Script / Status)</option>
                                        <option value="network">Network Interface</option>
                                        <option value="host">Remote Host (Ping / Connection)</option>
                                        <option value="file">File (Size / Hash / Permission)</option>
                                        <option value="directory">Directory</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                    <input type="text" name="description" class="pf-input text-xs w-full" placeholder="Optional description">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">PID File (for Process)</label>
                                    <input type="text" name="pidfile" class="pf-input text-xs w-full" placeholder="/var/run/service.pid">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Path (Filesystem / Script)</label>
                                    <input type="text" name="path" class="pf-input text-xs w-full" placeholder="/ or /usr/local/bin/check.sh">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Address / Host</label>
                                    <input type="text" name="address" class="pf-input text-xs w-full" placeholder="192.168.1.1">
                                </div>
                            </div>

                            @if(count($tests) > 0)
                                <div class="mt-4">
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Associated Tests</label>
                                    <div class="grid grid-cols-2 md:grid-cols-4 gap-2 max-h-40 overflow-y-auto p-2 bg-white dark:bg-gray-800 rounded border border-gray-200 dark:border-gray-700">
                                        @foreach($tests as $t)
                                            <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300">
                                                <input type="checkbox" name="tests[]" value="{{ $t['uuid'] }}" class="rounded text-indigo-600 focus:ring-indigo-500">
                                                <span class="truncate" title="{{ $t['name'] }} ({{ $t['condition'] }})">{{ $t['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            <div class="mt-4 flex items-center justify-between">
                                <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="enabled" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <span>Enable this service immediately</span>
                                </label>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-semibold shadow-sm transition">
                                    Save Monitored Service
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Services Table --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Name</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-left">Target / Path / PID</th>
                                    <th class="px-4 py-3 text-left">Assigned Tests</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                @forelse($services as $svc)
                                    @php
                                        $svcEnabled = ($svc['enabled'] ?? '0') === '1';
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-4 py-3">
                                            @if($svcEnabled)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                    Enabled
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    Disabled
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                            {{ $svc['name'] }}
                                            @if(!empty($svc['description']))
                                                <span class="block text-2xs text-gray-400">{{ $svc['description'] }}</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium capitalize">
                                                {{ $svc['%type'] ?? $svc['type'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-gray-500 dark:text-gray-400">
                                            {{ ($svc['pidfile'] ?? null) ?: (($svc['path'] ?? null) ?: (($svc['address'] ?? null) ?: '—')) }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if(!empty($svc['%tests']))
                                                <span class="text-xs text-gray-700 dark:text-gray-300">{{ $svc['%tests'] }}</span>
                                            @else
                                                <span class="text-gray-400 italic">None</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            @if(!auth()->user()->isReadOnly())
                                                <div class="inline-flex items-center gap-1.5">
                                                    <form action="{{ route('services.monit.services.toggle', [$firewall, $svc['uuid']]) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" title="{{ $svcEnabled ? 'Disable' : 'Enable' }}"
                                                                class="p-1 rounded text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('services.monit.services.destroy', [$firewall, $svc['uuid']]) }}" method="POST" class="inline" onsubmit="return confirm('Delete monitored service {{ $svc['name'] }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Delete" class="p-1 rounded text-gray-400 hover:text-rose-600 transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">No monitored services configured.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 2: Alerts --}}
            <div x-show="activeTab === 'alerts'" class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">Alert Recipients</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Email addresses and channels that receive notifications when Monit tests detect service anomalies.</p>
                    </div>
                    @if(!auth()->user()->isReadOnly())
                        <button @click="showAddAlert = !showAddAlert"
                                class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold flex items-center gap-1.5 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span x-text="showAddAlert ? 'Cancel' : 'Add Recipient'">Add Recipient</span>
                        </button>
                    @endif
                </div>

                {{-- Add Alert Form --}}
                @if(!auth()->user()->isReadOnly())
                    <div x-show="showAddAlert" x-cloak class="bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                        <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            New Alert Recipient
                        </h5>
                        <form action="{{ route('services.monit.alerts.store', $firewall) }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Recipient Email / Target *</label>
                                    <input type="text" name="recipient" required class="pf-input text-xs w-full" placeholder="admin@example.com">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                    <input type="text" name="description" class="pf-input text-xs w-full" placeholder="e.g. NOC Operations Team">
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="enabled" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <span>Enable this alert recipient</span>
                                </label>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-semibold shadow-sm transition">
                                    Save Alert Recipient
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Alerts Table --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Recipient</th>
                                    <th class="px-4 py-3 text-left">Description</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                @forelse($alerts as $al)
                                    @php
                                        $alEnabled = ($al['enabled'] ?? '0') === '1';
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-4 py-3">
                                            @if($alEnabled)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                    Enabled
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    Disabled
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                            {{ $al['recipient'] }}
                                        </td>
                                        <td class="px-4 py-3 text-gray-500 dark:text-gray-400">
                                            {{ $al['description'] ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            @if(!auth()->user()->isReadOnly())
                                                <div class="inline-flex items-center gap-1.5">
                                                    <form action="{{ route('services.monit.alerts.toggle', [$firewall, $al['uuid']]) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" title="{{ $alEnabled ? 'Disable' : 'Enable' }}"
                                                                class="p-1 rounded text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('services.monit.alerts.destroy', [$firewall, $al['uuid']]) }}" method="POST" class="inline" onsubmit="return confirm('Delete alert recipient {{ $al['recipient'] }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" title="Delete" class="p-1 rounded text-gray-400 hover:text-rose-600 transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                </div>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">No alert recipients configured.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 3: Test Conditions Catalog --}}
            <div x-show="activeTab === 'tests'" class="space-y-6">
                <div>
                    <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">Configured Test Conditions</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400">Predefined test condition rules available to assign to monitored services on this firewall.</p>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Test Name</th>
                                    <th class="px-4 py-3 text-left">Type</th>
                                    <th class="px-4 py-3 text-left">Trigger Condition</th>
                                    <th class="px-4 py-3 text-left">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                @forelse($tests as $t)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $t['name'] }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 font-mono">
                                                {{ $t['type'] }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 font-mono text-indigo-600 dark:text-indigo-400">
                                            {{ $t['condition'] }}
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 capitalize">
                                                {{ $t['%action'] ?? $t['action'] }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-4 py-8 text-center text-gray-400 italic">No test conditions found on firewall.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 4: General Settings --}}
            <div x-show="activeTab === 'settings'" class="space-y-6">
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6 border border-gray-100 dark:border-gray-700">
                    <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Monit Daemon Configuration</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">Tune polling schedules, outbound alert SMTP gateway parameters, and internal web service.</p>

                    <form action="{{ route('services.monit.settings.update', $firewall) }}" method="POST">
                        @csrf
                        <div class="space-y-6">
                            <div class="flex items-center">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" name="enabled" value="1" {{ ($settings['enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Enable Monit Service</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Polling Interval (seconds) *</label>
                                    <input type="number" name="interval" required min="1" class="pf-input text-xs w-full"
                                           value="{{ old('interval', $settings['interval'] ?? 120) }}">
                                    <span class="text-2xs text-gray-400">Interval at which Monit cycles through all monitored service checks.</span>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Start Delay (seconds) *</label>
                                    <input type="number" name="startdelay" required min="0" class="pf-input text-xs w-full"
                                           value="{{ old('startdelay', $settings['startdelay'] ?? 120) }}">
                                    <span class="text-2xs text-gray-400">Delay before starting first cycle after system boot.</span>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Mail Server Configuration</h5>
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="md:col-span-2">
                                        @php
                                            $mailserverVal = is_array($settings['mailserver'] ?? null) ? (array_key_first($settings['mailserver']) ?: '') : ($settings['mailserver'] ?? '');
                                        @endphp
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Mail Server Host / IP</label>
                                        <input type="text" name="mailserver" class="pf-input text-xs w-full"
                                               value="{{ old('mailserver', $mailserverVal) }}" placeholder="127.0.0.1 or smtp.example.com">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Port</label>
                                        <input type="number" name="port" class="pf-input text-xs w-full"
                                               value="{{ old('port', $settings['port'] ?? 25) }}" placeholder="25">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                                        <input type="text" name="username" class="pf-input text-xs w-full"
                                               value="{{ old('username', $settings['username'] ?? '') }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                                        <input type="password" name="password" class="pf-input text-xs w-full"
                                               value="{{ old('password', $settings['password'] ?? '') }}">
                                    </div>
                                    <div class="flex items-center space-x-6 md:col-span-2 mt-2">
                                        <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                            <input type="checkbox" name="ssl" value="1" {{ ($settings['ssl'] ?? '0') === '1' ? 'checked' : '' }}
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>Use SSL / TLS</span>
                                        </label>
                                        <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                            <input type="checkbox" name="sslverify" value="1" {{ ($settings['sslverify'] ?? '1') === '1' ? 'checked' : '' }}
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>Verify SSL Certificate</span>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Embedded Web Service (HTTPD)</h5>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div class="flex items-center">
                                        <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                            <input type="checkbox" name="httpdEnabled" value="1" {{ ($settings['httpdEnabled'] ?? '0') === '1' ? 'checked' : '' }}
                                                   class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                            <span>Enable Monit Web Service</span>
                                        </label>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">HTTPD Port</label>
                                        <input type="number" name="httpdPort" class="pf-input text-xs w-full"
                                               value="{{ old('httpdPort', $settings['httpdPort'] ?? 2812) }}">
                                    </div>
                                </div>
                            </div>

                            @if(!auth()->user()->isReadOnly())
                                <div class="pt-6 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold shadow-sm transition">
                                        Save & Apply Monit Settings
                                    </button>
                                </div>
                            @endif
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>

