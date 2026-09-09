<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('High Availability Sync') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="p-4 bg-green-50 dark:bg-green-900/40 border border-green-200 dark:border-green-800 rounded-lg text-sm text-green-800 dark:text-green-200">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div class="p-4 bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-800 dark:text-red-200">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="p-4 bg-red-50 dark:bg-red-900/40 border border-red-200 dark:border-red-800 rounded-lg text-sm text-red-800 dark:text-red-200">
                    <ul class="list-disc pl-5">
                        @foreach ($errors->all() as $err)
                            <li>{{ $err }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if($firewall->isOpnSense())
                @php
                    $hasync = $haData['hasync'] ?? [];
                    $syncItems = $hasync['syncitems'] ?? [];
                    $pfsyncIfs = $hasync['pfsyncinterface'] ?? [];
                    $pfsyncVersions = $hasync['pfsyncversion'] ?? [];
                @endphp

                <form method="POST" action="{{ route('system.high-avail-sync.update', $firewall) }}" class="space-y-6">
                    @csrf
                    <fieldset @if(auth()->user()->isReadOnly()) disabled @endif class="space-y-6 [&:disabled]:opacity-70 [&:disabled]:pointer-events-none">

                        {{-- General CARP / Preemption --}}
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">General CARP Settings</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Failover behavior and kernel preemption across cluster nodes.</p>

                                <div class="space-y-3">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" id="disablepreempt" name="disablepreempt" value="1"
                                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700"
                                                {{ ($hasync['disablepreempt'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="disablepreempt" class="font-medium text-gray-700 dark:text-gray-300">Disable Preempt</label>
                                            <p class="text-gray-500 dark:text-gray-400">When disabled, all CARP virtual IPs on this firewall will demote if any single interface fails.</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input type="checkbox" id="disconnectppps" name="disconnectppps" value="1"
                                                class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700"
                                                {{ ($hasync['disconnectppps'] ?? '0') === '1' ? 'checked' : '' }}>
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="disconnectppps" class="font-medium text-gray-700 dark:text-gray-300">Disconnect PPP connections on backup</label>
                                            <p class="text-gray-500 dark:text-gray-400">Terminates Point-to-Point connections when node transitions to CARP backup state.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- State Synchronization (pfsync) --}}
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">State Synchronization (pfsync)</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Replicates state table in real-time between nodes so ongoing firewall connections survive failover without dropping.</p>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="pfsyncinterface" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Synchronize Interface</label>
                                        <select id="pfsyncinterface" name="pfsyncinterface"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @foreach($pfsyncIfs as $key => $ifData)
                                                <option value="{{ $key }}" {{ !empty($ifData['selected']) ? 'selected' : '' }}>
                                                    {{ $ifData['value'] ?? ($key ?: 'Disabled') }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Recommended: dedicated direct sync network or VLAN between the two firewalls.</p>
                                    </div>

                                    <div>
                                        <label for="pfsyncpeerip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Synchronize Peer IP</label>
                                        <input type="text" id="pfsyncpeerip" name="pfsyncpeerip"
                                            value="{{ $hasync['pfsyncpeerip'] ?? '' }}"
                                            placeholder="e.g. 192.168.240.12 (leave empty for multicast)"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Unicast IP of the backup node or empty for default multicast.</p>
                                    </div>

                                    <div>
                                        <label for="pfsyncversion" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">pfsync Protocol Version</label>
                                        <select id="pfsyncversion" name="pfsyncversion"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm">
                                            @foreach($pfsyncVersions as $vKey => $vData)
                                                <option value="{{ $vKey }}" {{ !empty($vData['selected']) ? 'selected' : '' }}>
                                                    {{ $vData['value'] ?? $vKey }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="flex items-center pt-6">
                                        <input type="checkbox" id="pfsyncdefer" name="pfsyncdefer" value="1"
                                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700"
                                            {{ ($hasync['pfsyncdefer'] ?? '0') === '1' ? 'checked' : '' }}>
                                        <label for="pfsyncdefer" class="ml-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                                            Defer State Insertion
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Configuration Synchronization (XMLRPC Sync) --}}
                        <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg overflow-hidden">
                            <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-1">Configuration Synchronization (XMLRPC Sync)</h3>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-4">Automatically synchronizes firewall settings, rules, and services to the backup firewall when saved on master.</p>

                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                                    <div>
                                        <label for="synchronizetoip" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Synchronize to IP</label>
                                        <input type="text" id="synchronizetoip" name="synchronizetoip"
                                            value="{{ $hasync['synchronizetoip'] ?? '' }}"
                                            placeholder="e.g. 192.168.240.12"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">IP address of the secondary node to synchronize config to.</p>
                                    </div>

                                    <div>
                                        <label for="username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Remote Username</label>
                                        <input type="text" id="username" name="username"
                                            value="{{ $hasync['username'] ?? '' }}"
                                            placeholder="e.g. root"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                                    </div>

                                    <div>
                                        <label for="password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Remote Password</label>
                                        <input type="password" id="password" name="password"
                                            value="{{ $hasync['password'] ?? '' }}"
                                            placeholder="Remote node admin password"
                                            class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono">
                                    </div>
                                </div>

                                <div class="mb-6">
                                    <div class="flex items-center">
                                        <input type="checkbox" id="verifypeer" name="verifypeer" value="1"
                                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700"
                                            {{ ($hasync['verifypeer'] ?? '0') === '1' ? 'checked' : '' }}>
                                        <label for="verifypeer" class="ml-2 text-sm text-gray-700 dark:text-gray-300 font-medium">
                                            Verify SSL Peer (TLS Certificate Validation)
                                        </label>
                                    </div>
                                </div>

                                <div>
                                    <h4 class="text-sm font-semibold text-gray-900 dark:text-gray-100 uppercase tracking-wider mb-3">
                                        Select Services & Items to Synchronize
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3 p-4 bg-gray-50 dark:bg-gray-900/50 rounded-lg border border-gray-200 dark:border-gray-700">
                                        @foreach($syncItems as $itemKey => $itemData)
                                            <label class="flex items-center space-x-2 text-xs text-gray-700 dark:text-gray-300 hover:text-indigo-600 dark:hover:text-indigo-400 cursor-pointer">
                                                <input type="checkbox" name="syncitems[]" value="{{ $itemKey }}"
                                                    class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 dark:bg-gray-700"
                                                    {{ !empty($itemData['selected']) ? 'checked' : '' }}>
                                                <span>{{ $itemData['value'] ?? $itemKey }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>

                        @if(!auth()->user()->isReadOnly())
                        <div class="flex justify-end">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Save HA Settings
                            </button>
                        </div>
                        @endif

                    </fieldset>
                </form>
            @else
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <x-api-not-supported :firewall="$firewall" urlSuffix="system_hasync.php" featureName="High Availability Sync" />
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>

