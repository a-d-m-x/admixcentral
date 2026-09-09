<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System Update') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">System Information</h3>

                    @if(!empty($version))
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="block text-sm text-gray-500 dark:text-gray-400">Version</span>
                                <span class="block text-xl font-semibold">{{ $version['version'] ?? 'Unknown' }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="block text-sm text-gray-500 dark:text-gray-400">Base System</span>
                                <span class="block text-xl font-semibold">{{ $version['base_system'] ?? 'Unknown' }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="block text-sm text-gray-500 dark:text-gray-400">Platform</span>
                                <span class="block text-xl font-semibold">{{ $version['platform'] ?? 'Unknown' }}</span>
                            </div>
                            <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                <span class="block text-sm text-gray-500 dark:text-gray-400">Architecture</span>
                                <span class="block text-xl font-semibold">{{ $version['architecture'] ?? 'Unknown' }}</span>
                            </div>
                        </div>

                        @if($firewall->isOpnSense())
                            @php
                                $product = $firmwareStatus['product'] ?? [];
                                $statusMsg = $firmwareStatus['status_msg'] ?? 'Status not checked yet.';
                                $upgradePkgs = $firmwareStatus['upgrade_packages'] ?? [];
                                $consoleLog = $upgradeStatus['log'] ?? '';
                            @endphp
                            <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-4">
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">OPNsense Firmware Management</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Manage release series, mirror status, security vulnerability audits, and system updates.</p>
                                    </div>
                                    @if(!auth()->user()->isReadOnly())
                                    <div class="flex flex-wrap items-center gap-2">
                                        <form method="POST" action="{{ route('system.update.check', $firewall) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                Check Updates
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('system.update.audit', $firewall) }}">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-2 bg-purple-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-purple-700 active:bg-purple-900 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                                                Security Audit
                                            </button>
                                        </form>

                                        @if(!empty($upgradePkgs) || ($firmwareStatus['status'] ?? '') === 'update')
                                        <form method="POST" action="{{ route('system.update.upgrade', $firewall) }}" onsubmit="return confirm('Are you sure you want to trigger a firmware upgrade? The firewall may restart services or reboot.');">
                                            @csrf
                                            <button type="submit" class="inline-flex items-center px-3 py-2 bg-amber-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-amber-700 active:bg-amber-900 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"></path></svg>
                                                Upgrade Firmware
                                            </button>
                                        </form>
                                        @endif
                                    </div>
                                    @endif
                                </div>

                                <div class="p-4 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-lg mb-6">
                                    <div class="flex">
                                        <div class="flex-shrink-0">
                                            <svg class="h-5 w-5 text-indigo-500" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                            </svg>
                                        </div>
                                        <div class="ml-3">
                                            <h5 class="text-sm font-medium text-indigo-900 dark:text-indigo-200">Status</h5>
                                            <p class="mt-1 text-sm text-indigo-700 dark:text-indigo-300">{{ $statusMsg }}</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <span class="block text-sm text-gray-500 dark:text-gray-400">Release Series</span>
                                        <span class="block text-lg font-semibold">{{ $product['product_series'] ?? 'Unknown' }} ({{ $product['product_nickname'] ?? '' }})</span>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <span class="block text-sm text-gray-500 dark:text-gray-400">Package Version</span>
                                        <span class="block text-lg font-semibold font-mono">{{ $product['product_version'] ?? 'Unknown' }}</span>
                                    </div>
                                    <div class="p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                        <span class="block text-sm text-gray-500 dark:text-gray-400">Repository Mirror</span>
                                        <span class="block text-xs font-mono truncate" :title="'{{ $product['product_mirror'] ?? '' }}'">{{ $product['product_mirror'] ?? 'Official Mirror' }}</span>
                                    </div>
                                </div>

                                @if(!empty($upgradePkgs))
                                    <div class="mb-6">
                                        <h5 class="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300 mb-3">Available Package Upgrades ({{ count($upgradePkgs) }})</h5>
                                        <div class="overflow-x-auto border border-gray-200 dark:border-gray-700 rounded-lg">
                                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead class="bg-gray-50 dark:bg-gray-700">
                                                    <tr>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Package</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Repository</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">Current Version</th>
                                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">New Version</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700 font-mono text-sm">
                                                    @foreach($upgradePkgs as $upkg)
                                                        <tr>
                                                            <td class="px-4 py-2 font-semibold text-gray-900 dark:text-gray-100">{{ $upkg['name'] ?? '-' }}</td>
                                                            <td class="px-4 py-2 text-gray-500 dark:text-gray-400">{{ $upkg['repository'] ?? '-' }}</td>
                                                            <td class="px-4 py-2 text-red-600 dark:text-red-400">{{ $upkg['current_version'] ?? '-' }}</td>
                                                            <td class="px-4 py-2 text-green-600 dark:text-green-400">{{ $upkg['new_version'] ?? '-' }}</td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                @endif

                                @if(!empty($consoleLog))
                                    <div class="mt-6" x-data="{
                                        logText: @js($consoleLog),
                                        statusState: @js($upgradeStatus['status'] ?? 'done'),
                                        isRefreshing: false,
                                        refreshLog() {
                                            this.isRefreshing = true;
                                            fetch('{{ route('system.update.status-log', $firewall) }}')
                                                .then(r => r.json())
                                                .then(data => {
                                                    if (data.log) this.logText = data.log;
                                                    if (data.status) this.statusState = data.status;
                                                })
                                                .finally(() => { this.isRefreshing = false; });
                                        }
                                    }">
                                        <div class="flex justify-between items-center mb-2">
                                            <div class="flex items-center space-x-2">
                                                <h5 class="text-sm font-semibold uppercase tracking-wider text-gray-700 dark:text-gray-300">Console Output</h5>
                                                <span class="px-2 py-0.5 text-xs rounded font-mono font-medium"
                                                    :class="statusState === 'running' ? 'bg-amber-100 dark:bg-amber-900/60 text-amber-800 dark:text-amber-200 animate-pulse' : 'bg-green-100 dark:bg-green-900/60 text-green-800 dark:text-green-200'"
                                                    x-text="statusState">
                                                </span>
                                            </div>
                                            <button @click="refreshLog()" type="button" class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1" :class="{'animate-spin': isRefreshing}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                                Refresh Output
                                            </button>
                                        </div>
                                        <pre class="bg-gray-950 text-gray-100 font-mono text-xs p-4 rounded-lg overflow-x-auto max-h-96 whitespace-pre-wrap leading-relaxed border border-gray-800" x-text="logText"></pre>
                                    </div>
                                @endif
                            </div>
                        @else
                            <div
                                class="mt-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Update Capability
                                        </h3>
                                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                            <p>System updates are not currently supported via the API. Please use the pfSense
                                                web interface or console to perform updates.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    @else
                        <div class="text-center py-8 text-gray-500 dark:text-gray-400">
                            Unable to retrieve system version information.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
