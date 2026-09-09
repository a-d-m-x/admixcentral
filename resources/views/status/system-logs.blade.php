{{--
    View: System Logs
    Purpose: Display various types of logs (System, Firewall, DHCP, Auth, IPsec, OpenVPN, NTP).
    Features:
    - Tab navigation to switch between log categories.
    - Table display of log entries (Time, Process, PID, Message).
--}}
<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System Logs') }}" :firewall="$firewall" />
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
                    {{-- Log Type Navigation Tabs --}}
                    <div class="mb-4">
                        <div class="flex space-x-2 overflow-x-auto pb-2">
                            <a href="{{ route('status.system-logs', $firewall) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type', 'system') === 'system' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">System</a>
                            <a href="{{ route('status.system-logs', [$firewall, 'type' => 'firewall']) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type') === 'firewall' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">Firewall</a>
                            <a href="{{ route('status.system-logs', [$firewall, 'type' => 'dhcp']) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type') === 'dhcp' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">DHCP</a>
                            <a href="{{ route('status.system-logs', [$firewall, 'type' => 'auth']) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type') === 'auth' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">Auth</a>
                            <a href="{{ route('status.system-logs', [$firewall, 'type' => 'ipsec']) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type') === 'ipsec' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">IPsec</a>
                            <a href="{{ route('status.system-logs', [$firewall, 'type' => 'openvpn']) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type') === 'openvpn' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">OpenVPN</a>
                            <a href="{{ route('status.system-logs', [$firewall, 'type' => 'ntp']) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type') === 'ntp' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">NTP</a>
                            <a href="{{ route('status.system-logs', [$firewall, 'type' => 'settings']) }}" class="px-3 py-2 rounded-md text-sm font-medium {{ request('type') === 'settings' ? 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-200' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}">Remote Logging / Settings</a>
                        </div>
                    </div>

                    @if(request('type') === 'settings')
                        @if($firewall->isOpnSense())
                            {{-- Service Status & Summary --}}
                            <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg border border-gray-200 dark:border-gray-700">
                                <div class="flex flex-wrap items-center justify-between gap-4">
                                    <div>
                                        <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Syslog Service (Syslog-ng)</h3>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">System event logger and remote syslog destination forwarder.</p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Status:</span>
                                        @php
                                            $isRunning = ($syslogServiceStatus['status'] ?? '') === 'running';
                                        @endphp
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold {{ $isRunning ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' }}">
                                            <span class="w-2 h-2 mr-1.5 rounded-full {{ $isRunning ? 'bg-green-500' : 'bg-red-500' }}"></span>
                                            {{ ucfirst($syslogServiceStatus['status'] ?? 'unknown') }}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {{-- Add New Remote Syslog Destination --}}
                            @if(!auth()->user()->isReadOnly())
                            <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700/30 rounded-lg border border-gray-200 dark:border-gray-700">
                                <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-4">Add Remote Syslog Server</h3>
                                <form action="{{ route('status.system-logs.destinations.store', $firewall) }}" method="POST">
                                    @csrf
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Hostname / IP</label>
                                            <input type="text" name="hostname" class="pf-input" required placeholder="192.168.1.50" value="{{ old('hostname') }}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Port</label>
                                            <input type="number" name="port" class="pf-input" required placeholder="514" value="{{ old('port', 514) }}">
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Transport</label>
                                            <select name="transport" class="pf-input">
                                                <option value="udp4" {{ old('transport') === 'udp4' ? 'selected' : '' }}>UDP (IPv4)</option>
                                                <option value="tcp4" {{ old('transport') === 'tcp4' ? 'selected' : '' }}>TCP (IPv4)</option>
                                                <option value="tls4" {{ old('transport') === 'tls4' ? 'selected' : '' }}>TLS (IPv4)</option>
                                                <option value="udp6" {{ old('transport') === 'udp6' ? 'selected' : '' }}>UDP (IPv6)</option>
                                                <option value="tcp6" {{ old('transport') === 'tcp6' ? 'selected' : '' }}>TCP (IPv6)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Description</label>
                                            <input type="text" name="description" class="pf-input" placeholder="Optional description" value="{{ old('description') }}">
                                        </div>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <button type="submit" class="btn-primary">Add Remote Destination</button>
                                    </div>
                                </form>
                            </div>
                            @endif

                            {{-- Remote Destinations List --}}
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Remote Syslog Destinations</h3>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ count($syslogDestinations) }} configured</span>
                                </div>

                                <div class="pf-table-container">
                                    <table class="pf-table">
                                        <thead>
                                            <tr>
                                                <th>Hostname / IP</th>
                                                <th>Port</th>
                                                <th>Transport</th>
                                                <th>Description</th>
                                                <th>Status</th>
                                                @if(!auth()->user()->isReadOnly())
                                                <th class="text-right">Actions</th>
                                                @endif
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse($syslogDestinations as $dest)
                                                <tr>
                                                    <td class="font-medium" data-label="Hostname">{{ $dest['hostname'] ?? '' }}</td>
                                                    <td class="font-mono text-sm" data-label="Port">{{ $dest['port'] ?? '514' }}</td>
                                                    <td class="uppercase font-mono text-xs" data-label="Transport">{{ $dest['transport'] ?? 'udp4' }}</td>
                                                    <td class="text-gray-500 dark:text-gray-400" data-label="Description">{{ $dest['description'] ?? '-' }}</td>
                                                    <td data-label="Status">
                                                        @if(($dest['enabled'] ?? '1') === '1')
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">Active</span>
                                                        @else
                                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Disabled</span>
                                                        @endif
                                                    </td>
                                                    @if(!auth()->user()->isReadOnly())
                                                    <td class="text-right" data-label="Actions">
                                                        <form action="{{ route('status.system-logs.destinations.destroy', ['firewall' => $firewall, 'uuid' => $dest['uuid'] ?? '']) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this remote syslog destination?');" class="inline">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 font-medium text-sm">Delete</button>
                                                        </form>
                                                    </td>
                                                    @endif
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="{{ auth()->user()->isReadOnly() ? 5 : 6 }}" class="text-center py-6 text-gray-500 dark:text-gray-400">
                                                        No remote syslog destinations configured.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @else
                            <div class="text-center py-8 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                                Syslog settings can be configured directly on the pfSense web interface.
                            </div>
                        @endif
                    @elseif(empty($logs))
                        <div
                            class="text-center py-8 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                            No logs found.
                        </div>
                    @else
                        {{-- Log Table --}}
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Time</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Process</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            {{ request('type') === 'firewall' ? 'Interface / PID' : 'PID' }}</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Message</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($logs as $log)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $log['time'] ?? '' }}</td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                                @if(str_contains($log['process'] ?? '', '[PASS]'))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900 dark:text-emerald-200">PASS</span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">filterlog</span>
                                                @elseif(str_contains($log['process'] ?? '', '[BLOCK]'))
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900 dark:text-rose-200">BLOCK</span>
                                                    <span class="text-xs text-gray-500 dark:text-gray-400 ml-1">filterlog</span>
                                                @else
                                                    {{ $log['process'] ?? '' }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $log['pid'] ?? '' }}</td>
                                            <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 break-all">
                                                {{ $log['message'] ?? '' }}</td>
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
</x-app-layout>
