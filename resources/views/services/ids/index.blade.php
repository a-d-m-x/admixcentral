<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Intrusion Detection (IDS / IPS)') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{ activeTab: 'alerts', showAddRule: false, rulesetSearch: '' }">
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

            {{-- Service Status Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100 flex items-center gap-2">
                            <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            Suricata Engine Status
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">High-performance real-time intrusion detection and inline prevention system (IDS/IPS).</p>
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
                            @if(!$isRunning)
                                <form action="{{ route('services.ids.action', [$firewall, 'start']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium transition shadow-sm">
                                        Start
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('services.ids.action', [$firewall, 'restart']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-medium transition shadow-sm">
                                        Restart
                                    </button>
                                </form>
                                <form action="{{ route('services.ids.action', [$firewall, 'stop']) }}" method="POST" class="inline">
                                    @csrf
                                    <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-medium transition shadow-sm">
                                        Stop
                                    </button>
                                </form>
                            @endif

                            <form action="{{ route('services.ids.action', [$firewall, 'reconfigure']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-gray-700 hover:bg-gray-800 text-white rounded text-xs font-medium transition shadow-sm">
                                    Reconfigure
                                </button>
                            </form>

                            <form action="{{ route('services.ids.action', [$firewall, 'update-rules']) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-medium transition shadow-sm">
                                    Update Rulesets
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Configuration overview --}}
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Engine State</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ ($settings['enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled' }}
                        </p>
                    </div>
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Detection Mode</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            @php
                                $selectedMode = 'PCAP (IDS)';
                                foreach ($settings['mode'] ?? [] as $mKey => $mVal) {
                                    if (!empty($mVal['selected'])) {
                                        $selectedMode = $mVal['value'] ?? $mKey;
                                        break;
                                    }
                                }
                            @endphp
                            {{ $selectedMode }}
                        </p>
                    </div>
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Available Rulesets</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ count($rulesets) }} Total
                        </p>
                    </div>
                    <div class="p-3.5 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Live Alerts</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ count($alerts) }} Recorded
                        </p>
                    </div>
                </div>
            </div>

            {{-- Tabs Header --}}
            <div class="border-b border-gray-200 dark:border-gray-700">
                <nav class="-mb-px flex space-x-6 overflow-x-auto">
                    <button @click="activeTab = 'alerts'"
                            :class="activeTab === 'alerts' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        Live Alerts ({{ count($alerts) }})
                    </button>
                    <button @click="activeTab = 'rulesets'"
                            :class="activeTab === 'rulesets' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10" />
                        </svg>
                        Rulesets ({{ count($rulesets) }})
                    </button>
                    <button @click="activeTab = 'userrules'"
                            :class="activeTab === 'userrules' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'"
                            class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                        Custom User Rules ({{ count($userRules) }})
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

            {{-- TAB 1: Live Alerts --}}
            <div x-show="activeTab === 'alerts'" class="space-y-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 border border-gray-100 dark:border-gray-700">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Live Security Alerts</h3>
                            <p class="text-xs text-gray-500 dark:text-gray-400">Events and signature detections recorded by Suricata in EVE log / syslog.</p>
                        </div>
                    </div>

                    @if(empty($alerts))
                        <div class="text-center py-12 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                            <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                            <p class="mt-3 text-sm font-medium">No intrusion alerts recorded.</p>
                            <p class="text-xs text-gray-400 mt-1">Suricata has not detected suspicious traffic or is currently disabled.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                                <thead class="bg-gray-50 dark:bg-gray-700 text-2xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    <tr>
                                        <th class="px-4 py-3 text-left">Timestamp</th>
                                        <th class="px-4 py-3 text-left">Severity</th>
                                        <th class="px-4 py-3 text-left">Source</th>
                                        <th class="px-4 py-3 text-left">Destination</th>
                                        <th class="px-4 py-3 text-left">Alert / Signature</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($alerts as $alert)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                            <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $alert['timestamp'] ?? 'N/A' }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="inline-flex px-2 py-0.5 rounded text-2xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300">
                                                    {{ $alert['alert']['severity'] ?? ($alert['severity'] ?? 'Alert') }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $alert['src_ip'] ?? 'N/A' }}:{{ $alert['src_port'] ?? '' }}</td>
                                            <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $alert['dest_ip'] ?? 'N/A' }}:{{ $alert['dest_port'] ?? '' }}</td>
                                            <td class="px-4 py-3 text-gray-900 dark:text-gray-100 font-medium">{{ $alert['alert']['signature'] ?? ($alert['message'] ?? 'Signature match') }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            {{-- TAB 2: Rulesets --}}
            <div x-show="activeTab === 'rulesets'" class="space-y-6">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">Detection Rulesets Catalog</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Community and commercial signature feeds (Abuse.ch, Emerging Threats, etc.) installed on this firewall.</p>
                    </div>
                    <div class="w-full sm:w-64">
                        <input type="text" x-model="rulesetSearch" placeholder="Filter rulesets..." class="pf-input text-xs w-full">
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Description</th>
                                    <th class="px-4 py-3 text-left">Filename</th>
                                    <th class="px-4 py-3 text-left">Documentation</th>
                                    <th class="px-4 py-3 text-right">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                @forelse($rulesets as $rs)
                                    @php
                                        $rsEnabled = ($rs['enabled'] ?? '0') === '1';
                                    @endphp
                                    <tr x-show="!rulesetSearch || '{{ strtolower(addslashes($rs['description'] . ' ' . $rs['filename'])) }}'.includes(rulesetSearch.toLowerCase())"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-4 py-3">
                                            @if($rsEnabled)
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
                                            {{ $rs['description'] }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-gray-500 dark:text-gray-400">
                                            {{ $rs['filename'] }}
                                        </td>
                                        <td class="px-4 py-3">
                                            @if(!empty($rs['documentation_url']))
                                                <a href="{{ $rs['documentation_url'] }}" target="_blank" class="text-indigo-600 dark:text-indigo-400 hover:underline flex items-center gap-1">
                                                    <span>Documentation</span>
                                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                    </svg>
                                                </a>
                                            @else
                                                <span class="text-gray-400">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-right">
                                            @if(!auth()->user()->isReadOnly())
                                                <form action="{{ route('services.ids.rulesets.toggle', [$firewall, $rs['filename']]) }}" method="POST" class="inline">
                                                    @csrf
                                                    <button type="submit" class="px-2.5 py-1 text-2xs font-semibold rounded {{ $rsEnabled ? 'bg-gray-200 hover:bg-gray-300 text-gray-700 dark:bg-gray-700 dark:hover:bg-gray-600 dark:text-gray-300' : 'bg-indigo-600 hover:bg-indigo-700 text-white' }} transition">
                                                        {{ $rsEnabled ? 'Disable' : 'Enable' }}
                                                    </button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-4 py-8 text-center text-gray-400 italic">No rulesets available.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- TAB 3: Custom User Rules --}}
            <div x-show="activeTab === 'userrules'" class="space-y-6">
                <div class="flex justify-between items-center">
                    <div>
                        <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100">User Defined Rules</h4>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Custom user-created Snort/Suricata rules, fingerprint overrides, and pass/drop actions.</p>
                    </div>
                    @if(!auth()->user()->isReadOnly())
                        <button @click="showAddRule = !showAddRule"
                                class="px-3.5 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold flex items-center gap-1.5 transition shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                            </svg>
                            <span x-text="showAddRule ? 'Cancel' : 'Add User Rule'">Add User Rule</span>
                        </button>
                    @endif
                </div>

                {{-- Add User Rule Form --}}
                @if(!auth()->user()->isReadOnly())
                    <div x-show="showAddRule" x-cloak class="bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700 rounded-lg p-5">
                        <h5 class="text-sm font-semibold text-gray-900 dark:text-gray-100 mb-4 flex items-center gap-2">
                            <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                            New Custom User Rule
                        </h5>
                        <form action="{{ route('services.ids.rules.store', $firewall) }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Action *</label>
                                    <select name="action" required class="pf-input text-xs w-full">
                                        <option value="alert">Alert (Trigger detection alert)</option>
                                        <option value="drop">Drop (Block packet in IPS mode)</option>
                                        <option value="pass">Pass (Whitelist / Bypass)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                    <input type="text" name="description" class="pf-input text-xs w-full" placeholder="e.g. Block suspicious test traffic">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">TLS / SSL Fingerprint</label>
                                    <input type="text" name="fingerprint" class="pf-input text-xs w-full" placeholder="Optional JA3 or TLS SHA256">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Source IP / Network</label>
                                    <input type="text" name="source" class="pf-input text-xs w-full" placeholder="any or 192.168.1.0/24">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Destination IP / Network</label>
                                    <input type="text" name="destination" class="pf-input text-xs w-full" placeholder="any or 10.0.0.0/8">
                                </div>
                            </div>
                            <div class="mt-4 flex items-center justify-between">
                                <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300">
                                    <input type="checkbox" name="enabled" value="1" checked class="rounded text-indigo-600 focus:ring-indigo-500">
                                    <span>Enable this rule immediately</span>
                                </label>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-semibold shadow-sm transition">
                                    Save User Rule
                                </button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- User Rules Table --}}
                <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden border border-gray-100 dark:border-gray-700">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-gray-500 dark:text-gray-400 font-semibold uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Status</th>
                                    <th class="px-4 py-3 text-left">Action</th>
                                    <th class="px-4 py-3 text-left">Description</th>
                                    <th class="px-4 py-3 text-left">Source</th>
                                    <th class="px-4 py-3 text-left">Destination</th>
                                    <th class="px-4 py-3 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 text-gray-700 dark:text-gray-300">
                                @forelse($userRules as $ur)
                                    @php
                                        $urEnabled = ($ur['enabled'] ?? '0') === '1';
                                        $actionType = $ur['action'] ?? ($ur['%action'] ?? 'alert');
                                    @endphp
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/30 transition">
                                        <td class="px-4 py-3">
                                            @if($urEnabled)
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                                    Enabled
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-semibold bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-400">
                                                    Disabled
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3">
                                            @if($actionType === 'drop')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300 uppercase">
                                                    Drop
                                                </span>
                                            @elseif($actionType === 'pass')
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300 uppercase">
                                                    Pass
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-2xs font-bold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 uppercase">
                                                    Alert
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-gray-100">
                                            {{ $ur['description'] ?: '—' }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-gray-500 dark:text-gray-400">
                                            {{ $ur['source'] ?: 'any' }}
                                        </td>
                                        <td class="px-4 py-3 font-mono text-gray-500 dark:text-gray-400">
                                            {{ $ur['destination'] ?: 'any' }}
                                        </td>
                                        <td class="px-4 py-3 text-right whitespace-nowrap">
                                            @if(!auth()->user()->isReadOnly())
                                                <div class="inline-flex items-center gap-1.5">
                                                    <form action="{{ route('services.ids.rules.toggle', [$firewall, $ur['uuid']]) }}" method="POST" class="inline">
                                                        @csrf
                                                        <button type="submit" title="{{ $urEnabled ? 'Disable' : 'Enable' }}"
                                                                class="p-1 rounded text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 transition">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4" />
                                                            </svg>
                                                        </button>
                                                    </form>
                                                    <form action="{{ route('services.ids.rules.destroy', [$firewall, $ur['uuid']]) }}" method="POST" class="inline" onsubmit="return confirm('Delete this user rule?');">
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
                                        <td colspan="6" class="px-4 py-8 text-center text-gray-400 italic">No custom user rules defined.</td>
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
                    <h4 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-1">Suricata Engine Configuration</h4>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mb-6">Tune intrusion detection modes, network interface bindings, promiscuous mode, and logging targets.</p>

                    <form action="{{ route('services.ids.settings.update', $firewall) }}" method="POST">
                        @csrf
                        <div class="space-y-6">
                            <div class="flex items-center">
                                <label class="flex items-center space-x-3 cursor-pointer">
                                    <input type="checkbox" name="enabled" value="1" {{ ($settings['enabled'] ?? '0') === '1' ? 'checked' : '' }}
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Enable Intrusion Detection Service</span>
                                </label>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100 dark:border-gray-700">
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Operation Mode *</label>
                                    <select name="mode" class="pf-input text-xs w-full">
                                        @foreach($settings['mode'] ?? [] as $mKey => $mVal)
                                            <option value="{{ $mKey }}" {{ !empty($mVal['selected']) ? 'selected' : '' }}>
                                                {{ $mVal['value'] ?? $mKey }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <span class="text-2xs text-gray-400">PCAP runs passive detection (IDS); Netmap and Divert allow active inline blocking (IPS).</span>
                                </div>
                                <div class="flex items-center pt-5">
                                    <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" name="promisc" value="1" {{ ($settings['promisc'] ?? '0') === '1' ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>Promiscuous Mode (inspect all traffic on segment)</span>
                                    </label>
                                </div>
                            </div>

                            <div class="pt-4 border-t border-gray-100 dark:border-gray-700">
                                <h5 class="text-xs font-bold uppercase tracking-wider text-gray-500 dark:text-gray-400 mb-4">Logging & Export Options</h5>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" name="syslog" value="1" {{ ($settings['syslog'] ?? '0') === '1' ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>Send alerts to System Log</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" name="syslog_eve" value="1" {{ ($settings['syslog_eve'] ?? '0') === '1' ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>Export EVE JSON log to syslog</span>
                                    </label>
                                    <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 cursor-pointer">
                                        <input type="checkbox" name="LogPayload" value="1" {{ ($settings['LogPayload'] ?? '0') === '1' ? 'checked' : '' }}
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                        <span>Log Full Packet Payload</span>
                                    </label>
                                </div>
                            </div>

                            @if(!auth()->user()->isReadOnly())
                                <div class="pt-6 border-t border-gray-100 dark:border-gray-700 flex justify-end">
                                    <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-md text-xs font-semibold shadow-sm transition">
                                        Save & Apply IDS Settings
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

