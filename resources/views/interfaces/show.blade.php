<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="Interface: {{ $interfaceId }}" :firewall="$firewall">
            <x-slot name="actions">
                <a href="{{ route('firewall.interfaces.index', $firewall) }}"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Back to Interfaces
                </a>
            </x-slot>
        </x-firewall-header>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- Basic Information --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Basic Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Interface ID</dt>
                            <dd class="mt-1 text-sm font-mono">{{ $interface['id'] ?? $interface['if'] ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Description</dt>
                            <dd class="mt-1 text-sm">{{ $interface['descr'] ?? $interface['description'] ?? '-' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                            <dd class="mt-1 text-sm">
                                @php
                                    $isEnabled = !empty($interface['enable'])
                                        || !empty($interface['enabled'])
                                        || (strtolower($interface['status'] ?? '') === 'up');
                                @endphp
                                @if($isEnabled)
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Enabled
                                    </span>
                                @else
                                    <span
                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                        Disabled
                                    </span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                            <dd class="mt-1 text-sm">{{ $interface['type'] ?? $interface['if_type'] ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- IP Configuration --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">IP Configuration</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IPv4 Address</dt>
                            <dd class="mt-1 text-sm font-mono">
                                {{ $interface['ipaddr'] ?? $interface['ipaddrv4'] ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IPv4 Subnet</dt>
                            <dd class="mt-1 text-sm font-mono">
                                {{ $interface['subnet'] ?? $interface['subnetv4'] ?? 'N/A' }}</dd>
                        </div>
                        @if(isset($interface['ipaddrv6']) || isset($interface['ipaddr6']))
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IPv6 Address</dt>
                                <dd class="mt-1 text-sm font-mono">
                                    {{ $interface['ipaddrv6'] ?? $interface['ipaddr6'] ?? 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">IPv6 Subnet</dt>
                                <dd class="mt-1 text-sm font-mono">
                                    {{ $interface['subnetv6'] ?? $interface['subnet6'] ?? 'N/A' }}</dd>
                            </div>
                        @endif
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gateway</dt>
                            <dd class="mt-1 text-sm font-mono">{{ $interface['gateway'] ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- Hardware Information --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Hardware Information</h3>
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">MAC Address</dt>
                            <dd class="mt-1 text-sm font-mono">{{ $interface['macaddr'] ?? $interface['mac'] ?? 'N/A' }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Media</dt>
                            <dd class="mt-1 text-sm">{{ $interface['media'] ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">MTU</dt>
                            <dd class="mt-1 text-sm">{{ $interface['mtu'] ?? 'N/A' }}</dd>
                        </div>
                        <div>
                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Speed</dt>
                            <dd class="mt-1 text-sm">{{ $interface['speed'] ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            {{-- All Raw Data (for debugging) --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <details>
                        <summary class="text-sm font-semibold cursor-pointer hover:text-blue-600">Show Raw Data
                        </summary>
                        <pre
                            class="mt-4 text-xs bg-gray-100 dark:bg-gray-900 p-4 rounded overflow-x-auto">{{ json_encode($interface, JSON_PRETTY_PRINT) }}</pre>
                    </details>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
