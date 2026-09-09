<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Kernel Routing Table') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($firewall->isOpnSense())
                        {{-- Summary Header --}}
                        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div>
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Routing Table (netstat -r)</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Current kernel routing table showing active next-hop gateways and interfaces.</p>
                            </div>
                            <div class="flex items-center gap-3 text-sm">
                                <span class="px-2.5 py-1 bg-indigo-50 dark:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 rounded font-medium">
                                    Total: {{ count($routes) }} routes
                                </span>
                            </div>
                        </div>

                        {{-- Routes Table --}}
                        <div class="pf-table-container">
                            <table class="pf-table">
                                <thead>
                                    <tr>
                                        <th>Proto</th>
                                        <th>Destination</th>
                                        <th>Gateway</th>
                                        <th>Flags</th>
                                        <th>Interface</th>
                                        <th>MTU</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($routes as $route)
                                        <tr>
                                            <td data-label="Proto">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold uppercase {{ ($route['proto'] ?? '') === 'ipv6' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900/50 dark:text-purple-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/50 dark:text-blue-300' }}">
                                                    {{ $route['proto'] ?? 'ipv4' }}
                                                </span>
                                            </td>
                                            <td class="font-mono text-sm font-medium" data-label="Destination">
                                                {{ $route['destination'] ?? '' }}
                                            </td>
                                            <td class="font-mono text-sm" data-label="Gateway">
                                                {{ $route['gateway'] ?? '' }}
                                            </td>
                                            <td data-label="Flags">
                                                <span class="font-mono text-xs px-1.5 py-0.5 rounded bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300">
                                                    {{ $route['flags'] ?? '-' }}
                                                </span>
                                            </td>
                                            <td data-label="Interface">
                                                <span class="font-medium text-gray-900 dark:text-gray-100">{{ $route['intf_description'] ?? ($route['netif'] ?? '') }}</span>
                                                @if(!empty($route['intf_description']) && !empty($route['netif']) && $route['intf_description'] !== $route['netif'])
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 font-mono">({{ $route['netif'] }})</span>
                                                @endif
                                            </td>
                                            <td class="font-mono text-sm text-gray-500 dark:text-gray-400" data-label="MTU">
                                                {{ $route['mtu'] ?? '-' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="text-center py-6 text-gray-500 dark:text-gray-400">
                                                No routes found in kernel routing table.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <x-api-not-supported :firewall="$firewall" urlSuffix="diag_routes.php" featureName="Routes" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
