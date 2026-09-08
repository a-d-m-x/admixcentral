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
                        </div>
                    </div>

                    @if(empty($logs))
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
