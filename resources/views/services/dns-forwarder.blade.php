<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('DNS Forwarder') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="pf-alert pf-alert-success mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="pf-alert pf-alert-error mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($firewall->isOpnSense())
                        {{-- Service Status & Summary --}}
                        <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-700">
                            <div class="flex flex-wrap items-center justify-between gap-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Dnsmasq Service</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">DNS forwarder and DHCP server for local network naming.</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</span>
                                    @php
                                        $isRunning = ($serviceStatus['status'] ?? '') === 'running';
                                    @endphp
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $isRunning ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' }}">
                                        <span class="w-2 h-2 mr-1.5 rounded-full {{ $isRunning ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                        {{ ucfirst($serviceStatus['status'] ?? 'unknown') }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Port: <span class="font-mono">{{ $settings['port'] ?? ($settings['dns_port'] ?? '53') }}</span>
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Add New Host Override --}}
                        @if(!auth()->user()->isReadOnly())
                        <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg border border-gray-200 dark:border-gray-700">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Add Host Override</h3>
                            <form action="{{ route('services.dns-forwarder.hosts.store', $firewall) }}" method="POST">
                                @csrf
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Host</label>
                                        <input type="text" name="host" class="pf-input" required placeholder="myhost" value="{{ old('host') }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Domain</label>
                                        <input type="text" name="domain" class="pf-input" required placeholder="example.com" value="{{ old('domain') }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">IP Address</label>
                                        <input type="text" name="ip" class="pf-input" required placeholder="192.168.1.50" value="{{ old('ip') }}">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                        <input type="text" name="descr" class="pf-input" placeholder="Optional description" value="{{ old('descr') }}">
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button type="submit" class="btn-primary">Add Host Override</button>
                                </div>
                            </form>
                        </div>
                        @endif

                        {{-- Host Overrides List --}}
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Host Overrides</h3>
                                <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($hostOverrides) }} configured</span>
                            </div>

                            <div class="pf-table-container">
                                <table class="pf-table">
                                    <thead>
                                        <tr>
                                            <th>Host</th>
                                            <th>Domain</th>
                                            <th>IP Address</th>
                                            <th>Description</th>
                                            @if(!auth()->user()->isReadOnly())
                                            <th class="text-right">Actions</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($hostOverrides as $override)
                                            <tr>
                                                <td class="font-medium" data-label="Host">{{ $override['host'] ?? '' }}</td>
                                                <td data-label="Domain">{{ $override['domain'] ?? '' }}</td>
                                                <td class="font-mono text-sm" data-label="IP">{{ $override['ip'] ?? '' }}</td>
                                                <td class="text-gray-500 dark:text-gray-400" data-label="Description">{{ $override['descr'] ?? '' }}</td>
                                                @if(!auth()->user()->isReadOnly())
                                                <td class="text-right" data-label="Actions">
                                                    <form action="{{ route('services.dns-forwarder.hosts.destroy', ['firewall' => $firewall, 'uuid' => $override['uuid'] ?? '']) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this host override?');" class="inline">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 font-medium text-sm">Delete</button>
                                                    </form>
                                                </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="{{ auth()->user()->isReadOnly() ? 4 : 5 }}" class="text-center py-6 text-gray-500 dark:text-gray-400">
                                                    No host overrides configured.
                                                </td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <x-api-not-supported :firewall="$firewall" urlSuffix="services_dnsmasq.php" featureName="DNS Forwarder" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
