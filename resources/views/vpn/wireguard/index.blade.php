<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('WireGuard') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="wireguardApp()">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="p-4 bg-green-50 dark:bg-green-900/30 border border-green-200 dark:border-green-800 rounded-lg text-green-800 dark:text-green-300 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-red-800 dark:text-red-300 flex items-center justify-between">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg text-red-800 dark:text-red-300">
                    <ul class="list-disc pl-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(!$firewall->isOpnSense())
                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg border border-blue-100 dark:border-blue-800 flex items-start">
                    <svg class="h-5 w-5 text-blue-500 mt-0.5 mr-3 flex-shrink-0" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                    </svg>
                    <div>
                        <h3 class="text-sm font-semibold text-blue-900 dark:text-blue-200">Read-Only View</h3>
                        <p class="text-sm text-blue-700 dark:text-blue-300 mt-1">WireGuard configuration for pfSense is displayed in read-only mode via the API. Use the firewall web GUI for live updates.</p>
                    </div>
                </div>
            @else
                <!-- OPNsense WireGuard Service Status & Action Bar -->
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 p-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="flex items-center space-x-4">
                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Service Status:</span>
                            @php
                                $status = strtolower($serviceStatus['status'] ?? 'unknown');
                            @endphp
                            @if($status === 'running')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                                    <span class="w-1.5 h-1.5 mr-1.5 bg-green-500 rounded-full animate-pulse"></span>
                                    Running
                                </span>
                            @elseif($status === 'stopped')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300">
                                    <span class="w-1.5 h-1.5 mr-1.5 bg-red-500 rounded-full"></span>
                                    Stopped
                                </span>
                            @elseif($status === 'disabled')
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-yellow-100 text-yellow-800 dark:bg-yellow-900/40 dark:text-yellow-300">
                                    <span class="w-1.5 h-1.5 mr-1.5 bg-yellow-500 rounded-full"></span>
                                    Disabled
                                </span>
                            @else
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                    {{ ucfirst($status) }}
                                </span>
                            @endif
                        </div>

                        <div class="h-4 w-px bg-gray-300 dark:bg-gray-600 hidden md:block"></div>

                        <div class="flex items-center space-x-2">
                            <span class="text-sm font-medium text-gray-500 dark:text-gray-400">General Enable:</span>
                            @if(!empty($general['enabled']))
                                <span class="text-xs font-semibold text-green-600 dark:text-green-400">Enabled</span>
                            @else
                                <span class="text-xs font-semibold text-gray-500 dark:text-gray-400">Disabled</span>
                            @endif
                        </div>
                    </div>

                    @if(!auth()->user()->isReadOnly())
                    <div class="flex items-center flex-wrap gap-2">
                        <form method="POST" action="{{ route('vpn.wireguard.service.action', [$firewall, 'start']) }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-green-600 hover:bg-green-700 shadow-sm transition">
                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM9.555 7.168A1 1 0 008 8v4a1 1 0 001.555.832l3-2a1 1 0 000-1.664l-3-2z" clip-rule="evenodd"/></svg>
                                Start
                            </button>
                        </form>

                        <form method="POST" action="{{ route('vpn.wireguard.service.action', [$firewall, 'restart']) }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-gray-700 dark:text-gray-200 bg-gray-100 dark:bg-gray-700 hover:bg-gray-200 dark:hover:bg-gray-600 shadow-sm transition">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Restart
                            </button>
                        </form>

                        <form method="POST" action="{{ route('vpn.wireguard.service.action', [$firewall, 'stop']) }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-red-600 hover:bg-red-700 shadow-sm transition">
                                <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8 7a1 1 0 00-1 1v4a1 1 0 001 1h4a1 1 0 001-1V8a1 1 0 00-1-1H8z" clip-rule="evenodd"/></svg>
                                Stop
                            </button>
                        </form>

                        <form method="POST" action="{{ route('vpn.wireguard.service.action', [$firewall, 'reconfigure']) }}" class="inline">
                            @csrf
                            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 shadow-sm transition">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                                Apply Changes
                            </button>
                        </form>
                    </div>
                    @endif
                </div>
            @endif

            <!-- Main Tabs Container -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
                <div class="border-b border-gray-200 dark:border-gray-700 px-6 pt-4">
                    <nav class="-mb-px flex space-x-8">
                        <button @click="activeTab = 'tunnels'" 
                            :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'tunnels', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300': activeTab !== 'tunnels'}"
                            class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                            <span>Instances (Tunnels)</span>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ count($tunnels) }}</span>
                        </button>
                        <button @click="activeTab = 'peers'" 
                            :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'peers', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300': activeTab !== 'peers'}"
                            class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                            <span>Endpoints (Peers)</span>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ count($peers) }}</span>
                        </button>
                        @if($firewall->isOpnSense())
                        <button @click="activeTab = 'handshakes'" 
                            :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'handshakes', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300': activeTab !== 'handshakes'}"
                            class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm flex items-center gap-2">
                            <span>Diagnostics & Handshakes</span>
                            <span class="px-2 py-0.5 text-xs rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">{{ count($handshakes) }}</span>
                        </button>
                        <button @click="activeTab = 'general'" 
                            :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'general', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300': activeTab !== 'general'}"
                            class="whitespace-nowrap pb-4 px-1 border-b-2 font-medium text-sm">
                            General Settings
                        </button>
                        @endif
                    </nav>
                </div>

                <div class="p-6">
                    <!-- TAB 1: Instances (Tunnels / Servers) -->
                    <div x-show="activeTab === 'tunnels'">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">WireGuard Instances</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Configure local WireGuard interfaces and cryptographic listen endpoints.</p>
                            </div>
                            @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                            <button @click="openAddTunnelModal()" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-sm transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Instance
                            </button>
                            @endif
                        </div>

                        @if(empty($tunnels))
                            <div class="text-center py-12 bg-gray-50 dark:bg-gray-700/20 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                No WireGuard instances found.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name / Dev</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tunnel Address</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Port</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Public Key</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Peers</th>
                                            @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($tunnels as $tunnel)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                                                    <form method="POST" action="{{ route('vpn.wireguard.tunnels.toggle', [$firewall, $tunnel['id']]) }}">
                                                        @csrf
                                                        <button type="submit" title="Click to toggle" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ !empty($tunnel['enabled']) ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                            <span class="w-1.5 h-1.5 mr-1.5 rounded-full {{ !empty($tunnel['enabled']) ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                                            {{ !empty($tunnel['enabled']) ? 'Enabled' : 'Disabled' }}
                                                        </button>
                                                    </form>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ !empty($tunnel['enabled']) ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                            {{ !empty($tunnel['enabled']) ? 'Enabled' : 'Disabled' }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-medium text-gray-900 dark:text-white">{{ $tunnel['name'] ?? 'N/A' }}</div>
                                                    @if(!empty($tunnel['interface']))
                                                        <div class="text-xs font-mono text-gray-500 dark:text-gray-400">{{ $tunnel['interface'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300 font-mono">
                                                    {{ $tunnel['address'] ?? ($tunnel['tunneladdress'] ?? '-') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ $tunnel['listenport'] ?? ($tunnel['port'] ?? '51820') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-500 dark:text-gray-400">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="truncate max-w-[130px]" title="{{ $tunnel['public_key'] ?? ($tunnel['pubkey'] ?? '') }}">{{ $tunnel['public_key'] ?? ($tunnel['pubkey'] ?? '-') }}</span>
                                                        @if(!empty($tunnel['public_key'] ?? $tunnel['pubkey']))
                                                        <button @click="copyText('{{ $tunnel['public_key'] ?? $tunnel['pubkey'] }}', $event)" class="text-gray-400 hover:text-indigo-600 transition" title="Copy public key">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                        </button>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 dark:text-gray-300">
                                                    @php
                                                        $assignedPeers = is_array($tunnel['peers'] ?? null) ? $tunnel['peers'] : array_filter(explode(',', $tunnel['peers'] ?? ''));
                                                    @endphp
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                        {{ count($assignedPeers) }} peers
                                                    </span>
                                                </td>
                                                @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                    <button @click='openEditTunnelModal(@json($tunnel))' class="text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                                    <form method="POST" action="{{ route('vpn.wireguard.tunnels.destroy', [$firewall, $tunnel['id']]) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this WireGuard instance?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                                    </form>
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <!-- TAB 2: Endpoints (Peers / Clients) -->
                    <div x-show="activeTab === 'peers'" style="display: none;">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">WireGuard Endpoints (Peers)</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Configure remote peers, public keys, and routable tunnel addresses.</p>
                            </div>
                            @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                            <button @click="openAddPeerModal()" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-sm transition">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                                Add Endpoint
                            </button>
                            @endif
                        </div>

                        @if(empty($peers))
                            <div class="text-center py-12 bg-gray-50 dark:bg-gray-700/20 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                No WireGuard endpoints found.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name / Descr</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Endpoint</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Allowed IPs</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Public Key</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Keepalive</th>
                                            @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($peers as $peer)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                                                    <form method="POST" action="{{ route('vpn.wireguard.peers.toggle', [$firewall, $peer['id']]) }}">
                                                        @csrf
                                                        <button type="submit" title="Click to toggle" class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ !empty($peer['enabled']) ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                            <span class="w-1.5 h-1.5 mr-1.5 rounded-full {{ !empty($peer['enabled']) ? 'bg-green-500' : 'bg-gray-400' }}"></span>
                                                            {{ !empty($peer['enabled']) ? 'Enabled' : 'Disabled' }}
                                                        </button>
                                                    </form>
                                                    @else
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ !empty($peer['enabled']) ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                            {{ !empty($peer['enabled']) ? 'Enabled' : 'Disabled' }}
                                                        </span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-white">
                                                    {{ $peer['name'] ?? ($peer['descr'] ?? 'N/A') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    @if(!empty($peer['endpoint']) || !empty($peer['serveraddress']))
                                                        {{ $peer['endpoint'] ?? $peer['serveraddress'] }}{{ !empty($peer['port'] ?? $peer['serverport']) ? ':' . ($peer['port'] ?? $peer['serverport']) : '' }}
                                                    @else
                                                        <span class="text-gray-400 font-italic">Dynamic</span>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600 dark:text-gray-300">
                                                    {{ $peer['allowedips'] ?? ($peer['tunneladdress'] ?? '-') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-500 dark:text-gray-400">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="truncate max-w-[130px]" title="{{ $peer['public_key'] ?? ($peer['pubkey'] ?? '') }}">{{ $peer['public_key'] ?? ($peer['pubkey'] ?? '-') }}</span>
                                                        @if(!empty($peer['public_key'] ?? $peer['pubkey']))
                                                        <button @click="copyText('{{ $peer['public_key'] ?? $peer['pubkey'] }}', $event)" class="text-gray-400 hover:text-indigo-600 transition" title="Copy public key">
                                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                                        </button>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-500 dark:text-gray-400">
                                                    {{ !empty($peer['keepalive']) ? $peer['keepalive'] . 's' : '-' }}
                                                </td>
                                                @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                                    <button @click='openEditPeerModal(@json($peer))' class="text-indigo-600 dark:text-indigo-400 hover:underline">Edit</button>
                                                    <form method="POST" action="{{ route('vpn.wireguard.peers.destroy', [$firewall, $peer['id']]) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this WireGuard endpoint?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:underline">Delete</button>
                                                    </form>
                                                </td>
                                                @endif
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    @if($firewall->isOpnSense())
                    <!-- TAB 3: Diagnostics & Handshakes (Live Sessions) -->
                    <div x-show="activeTab === 'handshakes'" style="display: none;">
                        <div class="flex justify-between items-center mb-6">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Active Sessions & Handshakes</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Real-time status of WireGuard peer handshakes and traffic counters from the kernel.</p>
                            </div>
                            <button onclick="window.location.reload();" class="inline-flex items-center px-3 py-1.5 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 transition">
                                <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                                Refresh
                            </button>
                        </div>

                        @if(empty($handshakes))
                            <div class="text-center py-12 bg-gray-50 dark:bg-gray-700/20 rounded-lg border border-dashed border-gray-300 dark:border-gray-600 text-gray-500 dark:text-gray-400">
                                <svg class="mx-auto h-10 w-10 text-gray-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                No active handshakes recorded. Service may be idle or stopped.
                            </div>
                        @else
                            <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700 shadow-sm">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700/50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Interface</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Peer Public Key</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Endpoint</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Allowed IPs</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Latest Handshake</th>
                                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Transfer (Rx / Tx)</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        @foreach($handshakes as $row)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40 transition">
                                                <td class="px-6 py-4 whitespace-nowrap font-mono text-sm text-gray-900 dark:text-white font-medium">
                                                    {{ $row['if'] ?? ($row['interface'] ?? 'wg') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-500 dark:text-gray-400">
                                                    <span class="truncate max-w-[140px] inline-block" title="{{ $row['public-key'] ?? ($row['pubkey'] ?? '') }}">{{ $row['public-key'] ?? ($row['pubkey'] ?? '-') }}</span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 dark:text-gray-300">
                                                    {{ $row['endpoint'] ?? '-' }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-600 dark:text-gray-300">
                                                    {{ $row['allowed-ips'] ?? ($row['allowed_ips'] ?? '-') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-600 dark:text-gray-300">
                                                    {{ $row['latest-handshake'] ?? ($row['latest_handshake'] ?? 'Never') }}
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-xs font-mono text-gray-600 dark:text-gray-300">
                                                    <span class="text-green-600 dark:text-green-400">↓ {{ $row['transfer-rx'] ?? ($row['rx'] ?? '0 B') }}</span> / 
                                                    <span class="text-blue-600 dark:text-blue-400">↑ {{ $row['transfer-tx'] ?? ($row['tx'] ?? '0 B') }}</span>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>

                    <!-- TAB 4: General Settings -->
                    <div x-show="activeTab === 'general'" style="display: none;">
                        <div class="max-w-2xl">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">Master Settings</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">Manage global WireGuard subsystem operation on this firewall.</p>

                            <form method="POST" action="{{ route('vpn.wireguard.general.update', $firewall) }}" class="space-y-6">
                                @csrf
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input id="general_enabled" name="enabled" type="checkbox" value="1" {{ !empty($general['enabled']) ? 'checked' : '' }} {{ auth()->user()->isReadOnly() ? 'disabled' : '' }} class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="general_enabled" class="font-medium text-gray-700 dark:text-gray-300">Enable WireGuard</label>
                                        <p class="text-gray-500 dark:text-gray-400">Enable the WireGuard kernel module and service daemon on OPNsense.</p>
                                    </div>
                                </div>

                                @if(!auth()->user()->isReadOnly())
                                <div>
                                    <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 shadow-sm transition">
                                        Save Configuration
                                    </button>
                                </div>
                                @endif
                            </form>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- MODAL 1: Add/Edit Instance (Tunnel) -->
            <div x-show="tunnelModal.isOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="tunnelModal.isOpen = false"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full sm:p-6">
                        <form :action="tunnelModal.actionUrl" method="POST">
                            @csrf
                            <template x-if="tunnelModal.isEdit">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div class="flex justify-between items-center pb-3 border-b border-gray-200 dark:border-gray-700 mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" x-text="tunnelModal.isEdit ? 'Edit WireGuard Instance' : 'Add WireGuard Instance'"></h3>
                                <button type="button" @click="tunnelModal.isOpen = false" class="text-gray-400 hover:text-gray-500">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="space-y-4 text-sm">
                                <div class="flex items-center">
                                    <input id="t_enabled" type="checkbox" name="enabled" value="1" :checked="tunnelModal.form.enabled" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="t_enabled" class="ml-2 font-medium text-gray-700 dark:text-gray-300">Enabled</label>
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Name / Device</label>
                                    <input type="text" name="name" x-model="tunnelModal.form.name" required placeholder="e.g. wg0 or Roadwarrior" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-medium text-gray-700 dark:text-gray-300">Listen Port</label>
                                        <input type="number" name="listenport" x-model="tunnelModal.form.listenport" placeholder="51820" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block font-medium text-gray-700 dark:text-gray-300">MTU (Optional)</label>
                                        <input type="number" name="mtu" x-model="tunnelModal.form.mtu" placeholder="1420" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Tunnel Address (CIDR)</label>
                                    <input type="text" name="tunneladdress" x-model="tunnelModal.form.tunneladdress" required placeholder="10.10.10.1/24" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                                </div>

                                <div>
                                    <div class="flex justify-between items-center mb-1">
                                        <label class="block font-medium text-gray-700 dark:text-gray-300">Public Key</label>
                                        <button type="button" @click="generateKeyPair()" :disabled="tunnelModal.isGeneratingKeys" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-semibold flex items-center">
                                            <span x-show="!tunnelModal.isGeneratingKeys">⚡ Generate Key Pair</span>
                                            <span x-show="tunnelModal.isGeneratingKeys">Generating...</span>
                                        </button>
                                    </div>
                                    <input type="text" name="pubkey" x-model="tunnelModal.form.pubkey" required placeholder="Base64 public key" class="block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono">
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Private Key</label>
                                    <input type="password" name="privkey" x-model="tunnelModal.form.privkey" required placeholder="Base64 private key" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono">
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">DNS Servers (Optional)</label>
                                    <input type="text" name="dns" x-model="tunnelModal.form.dns" placeholder="e.g. 1.1.1.1, 8.8.8.8" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <div class="flex items-center">
                                    <input id="t_disableroutes" type="checkbox" name="disableroutes" value="1" :checked="tunnelModal.form.disableroutes" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="t_disableroutes" class="ml-2 font-medium text-gray-700 dark:text-gray-300">Disable Routes (Manual routing only)</label>
                                </div>

                                @if(!empty($peers))
                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Assign Endpoints (Peers)</label>
                                    <div class="max-h-32 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md p-2 space-y-1 dark:bg-gray-700/50">
                                        @foreach($peers as $peer)
                                            <div class="flex items-center">
                                                <input id="p_check_{{ $peer['id'] }}" type="checkbox" name="peers[]" value="{{ $peer['id'] }}" :checked="tunnelModal.form.peers.includes('{{ $peer['id'] }}')" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                <label for="p_check_{{ $peer['id'] }}" class="ml-2 text-xs text-gray-700 dark:text-gray-300 font-mono">
                                                    {{ $peer['name'] ?? $peer['descr'] }} ({{ $peer['allowedips'] ?? '-' }})
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button type="button" @click="tunnelModal.isOpen = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 shadow-sm" x-text="tunnelModal.isEdit ? 'Update Instance' : 'Create Instance'"></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- MODAL 2: Add/Edit Endpoint (Peer) -->
            <div x-show="peerModal.isOpen" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                    <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" @click="peerModal.isOpen = false"></div>
                    <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                    <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg px-4 pt-5 pb-4 text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full sm:p-6">
                        <form :action="peerModal.actionUrl" method="POST">
                            @csrf
                            <template x-if="peerModal.isEdit">
                                <input type="hidden" name="_method" value="PUT">
                            </template>

                            <div class="flex justify-between items-center pb-3 border-b border-gray-200 dark:border-gray-700 mb-4">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" x-text="peerModal.isEdit ? 'Edit WireGuard Endpoint' : 'Add WireGuard Endpoint'"></h3>
                                <button type="button" @click="peerModal.isOpen = false" class="text-gray-400 hover:text-gray-500">
                                    <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                                </button>
                            </div>

                            <div class="space-y-4 text-sm">
                                <div class="flex items-center">
                                    <input id="pe_enabled" type="checkbox" name="enabled" value="1" :checked="peerModal.form.enabled" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                    <label for="pe_enabled" class="ml-2 font-medium text-gray-700 dark:text-gray-300">Enabled</label>
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Name / Description</label>
                                    <input type="text" name="name" x-model="peerModal.form.name" required placeholder="e.g. laptop-alice or branch-router" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Public Key</label>
                                    <input type="text" name="pubkey" x-model="peerModal.form.pubkey" required placeholder="Remote peer base64 public key" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono">
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Pre-shared Key (Optional)</label>
                                    <input type="password" name="psk" x-model="peerModal.form.psk" placeholder="Optional pre-shared key (PSK)" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-xs font-mono">
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Allowed IPs / Tunnel Address</label>
                                    <input type="text" name="tunneladdress" x-model="peerModal.form.tunneladdress" required placeholder="10.10.10.2/32" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block font-medium text-gray-700 dark:text-gray-300">Endpoint Address (Optional)</label>
                                        <input type="text" name="serveraddress" x-model="peerModal.form.serveraddress" placeholder="vpn.example.com" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                    <div>
                                        <label class="block font-medium text-gray-700 dark:text-gray-300">Endpoint Port</label>
                                        <input type="number" name="serverport" x-model="peerModal.form.serverport" placeholder="51820" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                    </div>
                                </div>

                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300">Persistent Keepalive (Seconds, Optional)</label>
                                    <input type="number" name="keepalive" x-model="peerModal.form.keepalive" placeholder="e.g. 25" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                </div>

                                @if(!empty($tunnels))
                                <div>
                                    <label class="block font-medium text-gray-700 dark:text-gray-300 mb-1">Assign to Instances (Tunnels)</label>
                                    <div class="max-h-32 overflow-y-auto border border-gray-300 dark:border-gray-600 rounded-md p-2 space-y-1 dark:bg-gray-700/50">
                                        @foreach($tunnels as $tunnel)
                                            <div class="flex items-center">
                                                <input id="t_check_{{ $tunnel['id'] }}" type="checkbox" name="servers[]" value="{{ $tunnel['id'] }}" :checked="peerModal.form.servers.includes('{{ $tunnel['id'] }}')" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                <label for="t_check_{{ $tunnel['id'] }}" class="ml-2 text-xs text-gray-700 dark:text-gray-300 font-mono">
                                                    {{ $tunnel['name'] }} ({{ $tunnel['address'] ?? '-' }})
                                                </label>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                @endif
                            </div>

                            <div class="mt-6 flex justify-end space-x-3">
                                <button type="button" @click="peerModal.isOpen = false" class="px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">Cancel</button>
                                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md text-sm font-semibold hover:bg-indigo-700 shadow-sm" x-text="peerModal.isEdit ? 'Update Endpoint' : 'Create Endpoint'"></button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <script>
        function wireguardApp() {
            return {
                activeTab: 'tunnels',
                keypairUrl: '{{ route("vpn.wireguard.keypair", $firewall) }}',
                tunnelModal: {
                    isOpen: false,
                    isEdit: false,
                    isGeneratingKeys: false,
                    actionUrl: '',
                    form: {
                        name: '',
                        listenport: '51820',
                        tunneladdress: '',
                        pubkey: '',
                        privkey: '',
                        mtu: '',
                        dns: '',
                        disableroutes: false,
                        peers: [],
                        enabled: true
                    }
                },
                peerModal: {
                    isOpen: false,
                    isEdit: false,
                    actionUrl: '',
                    form: {
                        name: '',
                        pubkey: '',
                        psk: '',
                        tunneladdress: '',
                        serveraddress: '',
                        serverport: '51820',
                        keepalive: '',
                        servers: [],
                        enabled: true
                    }
                },
                openAddTunnelModal() {
                    this.tunnelModal.isEdit = false;
                    this.tunnelModal.actionUrl = '{{ route("vpn.wireguard.tunnels.store", $firewall) }}';
                    this.tunnelModal.form = {
                        name: '',
                        listenport: '51820',
                        tunneladdress: '',
                        pubkey: '',
                        privkey: '',
                        mtu: '',
                        dns: '',
                        disableroutes: false,
                        peers: [],
                        enabled: true
                    };
                    this.tunnelModal.isOpen = true;
                },
                openEditTunnelModal(tunnel) {
                    this.tunnelModal.isEdit = true;
                    this.tunnelModal.actionUrl = '{{ url("/firewall/{$firewall->id}/vpn/wireguard/tunnels") }}/' + tunnel.id;
                    const peersList = Array.isArray(tunnel.peers) ? tunnel.peers : (tunnel.peers ? tunnel.peers.split(',').map(s => s.trim()) : []);
                    this.tunnelModal.form = {
                        name: tunnel.name || '',
                        listenport: tunnel.listenport || tunnel.port || '51820',
                        tunneladdress: tunnel.address || tunnel.tunneladdress || '',
                        pubkey: tunnel.public_key || tunnel.pubkey || '',
                        privkey: tunnel.privkey || '',
                        mtu: tunnel.mtu || '',
                        dns: tunnel.dns || '',
                        disableroutes: !!tunnel.disableroutes,
                        peers: peersList,
                        enabled: tunnel.enabled !== false && tunnel.enabled !== '0'
                    };
                    this.tunnelModal.isOpen = true;
                },
                async generateKeyPair() {
                    this.tunnelModal.isGeneratingKeys = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const res = await fetch(this.keypairUrl, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token || '{{ csrf_token() }}'
                            }
                        });
                        const data = await res.json();
                        if (data.status === 'success') {
                            this.tunnelModal.form.pubkey = data.pubkey;
                            this.tunnelModal.form.privkey = data.privkey;
                        } else {
                            alert('Failed to generate key pair: ' + (data.message || 'Unknown error'));
                        }
                    } catch (e) {
                        alert('Network error while generating keys: ' + e.message);
                    } finally {
                        this.tunnelModal.isGeneratingKeys = false;
                    }
                },
                openAddPeerModal() {
                    this.peerModal.isEdit = false;
                    this.peerModal.actionUrl = '{{ route("vpn.wireguard.peers.store", $firewall) }}';
                    this.peerModal.form = {
                        name: '',
                        pubkey: '',
                        psk: '',
                        tunneladdress: '',
                        serveraddress: '',
                        serverport: '51820',
                        keepalive: '25',
                        servers: [],
                        enabled: true
                    };
                    this.peerModal.isOpen = true;
                },
                openEditPeerModal(peer) {
                    this.peerModal.isEdit = true;
                    this.peerModal.actionUrl = '{{ url("/firewall/{$firewall->id}/vpn/wireguard/peers") }}/' + peer.id;
                    const serversList = Array.isArray(peer.servers) ? peer.servers : (peer.servers ? peer.servers.split(',').map(s => s.trim()) : []);
                    this.peerModal.form = {
                        name: peer.name || peer.descr || '',
                        pubkey: peer.public_key || peer.pubkey || '',
                        psk: peer.psk || '',
                        tunneladdress: peer.allowedips || peer.tunneladdress || '',
                        serveraddress: peer.endpoint || peer.serveraddress || '',
                        serverport: peer.port || peer.serverport || '51820',
                        keepalive: peer.keepalive || '',
                        servers: serversList,
                        enabled: peer.enabled !== false && peer.enabled !== '0'
                    };
                    this.peerModal.isOpen = true;
                },
                copyText(text, event) {
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text).then(() => {
                            this.showToast('Copied to clipboard!');
                        });
                    } else {
                        const ta = document.createElement('textarea');
                        ta.value = text;
                        document.body.appendChild(ta);
                        ta.select();
                        document.execCommand('copy');
                        document.body.removeChild(ta);
                        this.showToast('Copied to clipboard!');
                    }
                },
                showToast(msg) {
                    if (window.Swal) {
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: msg,
                            showConfirmButton: false,
                            timer: 2000
                        });
                    } else {
                        alert(msg);
                    }
                }
            };
        }
    </script>
</x-app-layout>
