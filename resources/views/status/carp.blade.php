<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('CARP Status') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Global CARP Settings --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-lg font-medium">Global CARP Settings</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Common Address Redundancy Protocol (CARP) failover cluster status.</p>
                        </div>
                        @if($firewall->isOpnSense())
                            <a href="{{ route('system.high-avail-sync', $firewall) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-50 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800 rounded-md text-xs font-semibold hover:bg-indigo-100">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                                Configure HA Sync
                            </a>
                        @endif
                    </div>

                    @if(!empty($carpStatus['status_msg']))
                        <div class="mb-6 p-4 bg-indigo-50 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800 rounded-lg">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-block w-2.5 h-2.5 rounded-full {{ ($carpStatus['enable'] ?? false) ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                    <span class="text-sm font-medium text-indigo-950 dark:text-indigo-200">{{ $carpStatus['status_msg'] }}</span>
                                </div>
                                @if(isset($carpStatus['demotion']))
                                    <span class="text-xs font-mono text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 px-2 py-1 rounded border border-gray-200 dark:border-gray-700">
                                        Demotion: {{ $carpStatus['demotion'] }}
                                    </span>
                                @endif
                            </div>
                        </div>
                    @endif

                    <form action="{{ route('status.carp.update', $firewall) }}" method="POST" class="space-y-4">
                        @csrf
                        <fieldset @if(auth()->user()->isReadOnly()) disabled @endif class="[&:disabled]:opacity-60 [&:disabled]:pointer-events-none">
                        <div class="flex items-center">
                            <input type="checkbox" id="enable" name="enable" class="pf-checkbox"
                                {{ ($carpStatus['enable'] ?? false) ? 'checked' : '' }}>
                            <label for="enable" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enable CARP</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="maintenance_mode" name="maintenance_mode" class="pf-checkbox"
                                {{ ($carpStatus['maintenance_mode'] ?? false) ? 'checked' : '' }}>
                            <label for="maintenance_mode" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Maintenance Mode</label>
                        </div>
                        </fieldset>
                        @if(!auth()->user()->isReadOnly())
                        <div>
                            <button type="submit" class="pf-btn pf-btn-primary">Save Settings</button>
                        </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Virtual IPs --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">Virtual IPs</h3>
                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Type</th>
                                    <th scope="col" class="py-3 px-6">Interface</th>
                                    <th scope="col" class="py-3 px-6">Address</th>
                                    <th scope="col" class="py-3 px-6">VHID</th>
                                    <th scope="col" class="py-3 px-6">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($virtualIps as $vip)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6">{{ $vip['mode'] ?? 'N/A' }}</td>
                                        <td class="py-4 px-6">{{ strtoupper($vip['interface'] ?? '') }}</td>
                                        <td class="py-4 px-6">{{ $vip['subnet'] ?? '' }}/{{ $vip['subnet_bits'] ?? '' }}</td>
                                        <td class="py-4 px-6">{{ $vip['vhid'] ?? '-' }}</td>
                                        <td class="py-4 px-6">{{ $vip['descr'] ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="5" class="py-4 px-6 text-center">No Virtual IPs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
