<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('WireGuard') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    
                    @if(!$firewall->isOpnSense())
                    <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-md border border-blue-100 dark:border-blue-800">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Read-Only View</h3>
                                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                    <p>WireGuard configuration is currently read-only via the API. Please use the pfSense GUI for modifications.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    @endif

                    <div x-data="{ activeTab: 'tunnels' }">
                        <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                            <nav class="-mb-px flex space-x-8">
                                <button @click="activeTab = 'tunnels'" 
                                    :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'tunnels', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300': activeTab !== 'tunnels'}"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Tunnels
                                </button>
                                <button @click="activeTab = 'peers'" 
                                    :class="{'border-indigo-500 text-indigo-600 dark:text-indigo-400': activeTab === 'peers', 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300': activeTab !== 'peers'}"
                                    class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    Peers
                                </button>
                            </nav>
                        </div>

                        <div x-show="activeTab === 'tunnels'">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium">Tunnels</h3>
                            </div>
                            
                            @if(empty($tunnels))
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    No WireGuard tunnels found.
                                </div>
                            @else
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Address</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Port</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Public Key</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($tunnels as $tunnel)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $tunnel['name'] ?? 'N/A' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $tunnel['descr'] ?? '' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        @php
                                                            // Determine address field: check standard 'addresses' array (XML/JSON often nests this under 'row'),
                                                            // or fallback to legacy 'address' field.
                                                            $tunnelAddresses = $tunnel['addresses']['row'] ?? $tunnel['addresses'] ?? $tunnel['address'] ?? null;
                                                        @endphp
                                                        @if(!empty($tunnelAddresses) && is_array($tunnelAddresses))
                                                            @foreach($tunnelAddresses as $addr)
                                                                @if(is_array($addr))
                                                                     <span class="inline-block bg-gray-100 dark:bg-gray-700 px-1 rounded mr-1 mb-1">
                                                                        {{ $addr['address'] ?? json_encode($addr) }}{{ isset($addr['mask']) ? '/'.$addr['mask'] : '' }}
                                                                     </span>
                                                                @else
                                                                    <span class="inline-block bg-gray-100 dark:bg-gray-700 px-1 rounded mr-1 mb-1">{{ $addr }}</span>
                                                                @endif
                                                            @endforeach
                                                        @elseif(!empty($tunnelAddresses) && is_string($tunnelAddresses))
                                                            {{ $tunnelAddresses }}
                                                        @else
                                                            <span class="text-gray-400">-</span>
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $tunnel['listenport'] ?? $tunnel['port'] ?? '' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <div class="flex items-center group cursor-help" title="{{ $tunnel['public_key'] ?? $tunnel['publickey'] ?? '' }}">
                                                            <span class="truncate max-w-[150px]">{{ $tunnel['public_key'] ?? $tunnel['publickey'] ?? '' }}</span>
                                                            <svg class="w-4 h-4 ml-1 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                            </svg>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>

                        <div x-show="activeTab === 'peers'" style="display: none;">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-medium">Peers</h3>
                            </div>

                            @if(empty($peers))
                                <div class="text-center py-8 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                    No WireGuard peers found.
                                </div>
                            @else
                                <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Endpoint</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Allowed IPs</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Public Key</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($peers as $peer)
                                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $peer['descr'] ?? 'N/A' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $peer['endpoint'] ?? '' }}:{{ $peer['port'] ?? $peer['endpointport'] ?? '' }}</td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        @php
                                                            // Similar to addresses, allowedips can be nested under 'row' or flat array/string
                                                            $allowedIps = $peer['allowedips']['row'] ?? $peer['allowedips'] ?? $peer['allowed_ips'] ?? '';
                                                        @endphp
                                                        @if(is_array($allowedIps))
                                                            @foreach($allowedIps as $ip)
                                                                @if(is_array($ip))
                                                                    {{-- Handle nested array (e.g. ['address' => '1.2.3.4', 'mask' => '32']) --}}
                                                                    <span class="inline-block bg-gray-100 dark:bg-gray-700 px-1 rounded mr-1 mb-1">
                                                                        {{ $ip['address'] ?? json_encode($ip) }}{{ isset($ip['mask']) ? '/'.$ip['mask'] : '' }}
                                                                    </span>
                                                                @else
                                                                    <span class="inline-block bg-gray-100 dark:bg-gray-700 px-1 rounded mr-1 mb-1">{{ $ip }}</span>
                                                                @endif
                                                            @endforeach
                                                        @else
                                                            {{ $allowedIps }}
                                                        @endif
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        <div class="flex items-center group cursor-help" title="{{ $peer['public_key'] ?? $peer['publickey'] ?? '' }}">
                                                            <span class="truncate max-w-[150px]">{{ $peer['public_key'] ?? $peer['publickey'] ?? '' }}</span>
                                                            <svg class="w-4 h-4 ml-1 text-gray-400 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                                            </svg>
                                                        </div>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
