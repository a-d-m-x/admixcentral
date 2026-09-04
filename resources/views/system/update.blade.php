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
                            @endphp
                            <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-6">
                                <div class="flex justify-between items-center mb-4">
                                    <div>
                                        <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100">OPNsense Firmware Management</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Manage release series, mirror status, and check for available updates.</p>
                                    </div>
                                    @if(!auth()->user()->isReadOnly())
                                    <form method="POST" action="{{ route('system.update.check', $firewall) }}">
                                        @csrf
                                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                                            Check for Updates
                                        </button>
                                    </form>
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

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
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
