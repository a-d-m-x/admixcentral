<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Intrusion Detection (IDS / IPS)') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative dark:bg-green-900/30 dark:border-green-800 dark:text-green-300">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error') || isset($error))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative dark:bg-red-900/30 dark:border-red-800 dark:text-red-300">
                    {{ session('error') ?? $error }}
                </div>
            @endif

            {{-- Service Status Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Suricata Engine Status</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Real-time intrusion detection and prevention service</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @php
                            $isRunning = ($status['status'] ?? '') === 'running';
                            $isDisabled = ($status['status'] ?? '') === 'disabled';
                        @endphp

                        @if($isRunning)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                <span class="w-2 h-2 mr-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Running
                            </span>
                        @elseif($isDisabled)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Disabled
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                Stopped
                            </span>
                        @endif

                        @if(!auth()->user()->isReadOnly())
                            <div class="flex items-center gap-2">
                                @if(!$isRunning)
                                    <form action="{{ route('services.ids.action', [$firewall, 'start']) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded text-xs font-medium transition">
                                            Start
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('services.ids.action', [$firewall, 'restart']) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-amber-600 hover:bg-amber-700 text-white rounded text-xs font-medium transition">
                                            Restart
                                        </button>
                                    </form>
                                    <form action="{{ route('services.ids.action', [$firewall, 'stop']) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded text-xs font-medium transition">
                                            Stop
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Configuration overview --}}
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Engine State</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ ($settings['enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled' }}
                        </p>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Detection Mode</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            @php
                                $selectedMode = 'PCAP (IDS)';
                                foreach ($settings['mode'] ?? [] as $mKey => $mVal) {
                                    if (!empty($mVal['selected'])) {
                                        $selectedMode = $mVal['value'] ?? $mKey;
                                        break;
                                    }
                                }
                            @endphp
                            {{ $selectedMode }}
                        </p>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Active Alerts</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ count($alerts) }} Recorded
                        </p>
                    </div>
                </div>
            </div>

            {{-- Alerts Table --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Live Security Alerts</h3>
                </div>

                @if(empty($alerts))
                    <div class="text-center py-12 text-gray-500 dark:text-gray-400 bg-gray-50 dark:bg-gray-700/30 rounded-lg">
                        <svg class="mx-auto h-10 w-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                        <p class="mt-3 text-sm font-medium">No intrusion alerts recorded.</p>
                        <p class="text-xs text-gray-400 mt-1">Suricata has not detected suspicious traffic or is currently disabled.</p>
                    </div>
                @else
                    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700 text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                <tr>
                                    <th class="px-4 py-3 text-left">Timestamp</th>
                                    <th class="px-4 py-3 text-left">Severity</th>
                                    <th class="px-4 py-3 text-left">Source</th>
                                    <th class="px-4 py-3 text-left">Destination</th>
                                    <th class="px-4 py-3 text-left">Alert / Signature</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($alerts as $alert)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                        <td class="px-4 py-3 whitespace-nowrap text-gray-600 dark:text-gray-300">{{ $alert['timestamp'] ?? 'N/A' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap">
                                            <span class="inline-flex px-2 py-0.5 rounded text-xs font-medium bg-rose-100 text-rose-800 dark:bg-rose-900/50 dark:text-rose-300">
                                                {{ $alert['alert']['severity'] ?? 'Alert' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $alert['src_ip'] ?? 'N/A' }}:{{ $alert['src_port'] ?? '' }}</td>
                                        <td class="px-4 py-3 whitespace-nowrap font-mono text-xs">{{ $alert['dest_ip'] ?? 'N/A' }}:{{ $alert['dest_port'] ?? '' }}</td>
                                        <td class="px-4 py-3 text-gray-900 dark:text-gray-100">{{ $alert['alert']['signature'] ?? ($alert['message'] ?? 'Signature match') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

        </div>
    </div>
</x-app-layout>
