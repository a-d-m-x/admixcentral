<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('IPsec Status') }}" :firewall="$firewall" />
    </x-slot>

    @php
        // Format a duration in seconds as H:i:s (allowing >24h), e.g. 13677 -> "03:47:57"
        $hms = function ($s) {
            $s = (int) $s;
            $h = intdiv($s, 3600);
            $m = intdiv($s % 3600, 60);
            $sec = $s % 60;
            return sprintf('%02d:%02d:%02d', $h, $m, $sec);
        };
        $bytes = function ($b) {
            $b = (float) $b;
            $u = ['B', 'KB', 'MB', 'GB', 'TB'];
            $i = 0;
            while ($b >= 1024 && $i < count($u) - 1) { $b /= 1024; $i++; }
            return round($b, $i ? 1 : 0) . ' ' . $u[$i];
        };
        $connected = collect($status)->where('state', 'ESTABLISHED')->count();
    @endphp

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-4">

            {{-- Summary strip --}}
            <div class="flex flex-wrap items-center gap-3">
                <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-teal-50 dark:bg-teal-900/20 text-teal-700 dark:text-teal-300 text-sm font-medium">
                    <span class="w-2 h-2 rounded-full bg-teal-500"></span>
                    {{ $connected }} of {{ count($status) }} tunnels established
                </span>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="px-6 py-3 bg-gray-700 dark:bg-gray-900 text-white text-sm font-semibold tracking-wide">
                    IPsec Status
                </div>

                @if (empty($status))
                    <div class="p-6 text-center text-gray-500 dark:text-gray-400">No IPsec Security Associations found.</div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 dark:bg-gray-700/50 text-xs uppercase tracking-wider text-gray-500 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 text-left font-medium">ID</th>
                                    <th class="px-4 py-3 text-left font-medium">Description</th>
                                    <th class="px-4 py-3 text-left font-medium">Local</th>
                                    <th class="px-4 py-3 text-left font-medium">Remote</th>
                                    <th class="px-4 py-3 text-left font-medium">Role</th>
                                    <th class="px-4 py-3 text-left font-medium">Timers</th>
                                    <th class="px-4 py-3 text-left font-medium">Algo</th>
                                    <th class="px-4 py-3 text-left font-medium">Status</th>
                                </tr>
                            </thead>
                                @foreach ($status as $sa)
                                    @php $up = ($sa['state'] ?? '') === 'ESTABLISHED'; @endphp
                                    <tbody x-data="{ open: false }" class="divide-y divide-gray-100 dark:divide-gray-700 border-b-4 border-gray-100 dark:border-gray-700/50">
                                    <tr class="align-top">
                                        <td class="px-4 py-4 whitespace-nowrap text-gray-900 dark:text-gray-100">
                                            <span class="font-mono">{{ $sa['con_id'] ?? '—' }}</span>
                                            <span class="text-gray-400">#{{ $sa['uniqueid'] ?? '' }}</span>
                                            <button type="button" @click="open = !open"
                                                class="mt-2 flex items-center gap-1.5 text-xs px-2 py-1 rounded bg-sky-500 hover:bg-sky-600 text-white transition-colors">
                                                <span x-text="open ? '−' : '+'"></span>
                                                <span>Child SA ({{ count($sa['children'] ?? []) }} Connected)</span>
                                            </button>
                                        </td>
                                        <td class="px-4 py-4 text-gray-900 dark:text-gray-100 font-medium">{{ $sa['descr'] ?? 'N/A' }}</td>

                                        {{-- Local --}}
                                        <td class="px-4 py-4 whitespace-nowrap font-mono text-xs text-gray-600 dark:text-gray-300 leading-6">
                                            <div><span class="font-semibold text-gray-500 dark:text-gray-400">ID:</span> {{ $sa['local_id'] }}</div>
                                            <div><span class="font-semibold text-gray-500 dark:text-gray-400">Host:</span> {{ $sa['local_host'] }}:{{ $sa['local_port'] }}</div>
                                            <div><span class="font-semibold text-gray-500 dark:text-gray-400">SPI:</span> {{ $sa['local_spi'] }}</div>
                                        </td>

                                        {{-- Remote --}}
                                        <td class="px-4 py-4 whitespace-nowrap font-mono text-xs text-gray-600 dark:text-gray-300 leading-6">
                                            <div><span class="font-semibold text-gray-500 dark:text-gray-400">ID:</span> {{ $sa['remote_id'] }}</div>
                                            <div>
                                                <span class="font-semibold text-gray-500 dark:text-gray-400">Host:</span> {{ $sa['remote_host'] }}:{{ $sa['remote_port'] }}
                                                @if (!empty($sa['remote_natt']))<span class="ml-1 px-1 rounded bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">NAT-T</span>@endif
                                            </div>
                                            <div><span class="font-semibold text-gray-500 dark:text-gray-400">SPI:</span> {{ $sa['remote_spi'] }}</div>
                                        </td>

                                        {{-- Role --}}
                                        <td class="px-4 py-4 whitespace-nowrap text-gray-700 dark:text-gray-300 leading-6">
                                            <div class="font-medium">IKEv{{ $sa['version'] ?? 2 }}</div>
                                            <div class="capitalize text-gray-500 dark:text-gray-400">{{ $sa['role'] ?? '' }}</div>
                                        </td>

                                        {{-- Timers --}}
                                        <td class="px-4 py-4 whitespace-nowrap text-xs text-gray-600 dark:text-gray-300 leading-6">
                                            <div><span class="font-semibold">Rekey:</span> {{ $sa['rekey'] }}s ({{ $hms($sa['rekey']) }})</div>
                                            <div><span class="font-semibold">Reauth:</span> {{ empty($sa['reauth']) ? 'Disabled' : $sa['reauth'] . 's (' . $hms($sa['reauth']) . ')' }}</div>
                                        </td>

                                        {{-- Algo --}}
                                        <td class="px-4 py-4 whitespace-nowrap text-xs font-mono text-gray-600 dark:text-gray-300 leading-6">
                                            <div>{{ $sa['encr'] }} ({{ $sa['keysize'] }})</div>
                                            <div>{{ $sa['integ'] }}</div>
                                            <div>{{ $sa['prf'] }}</div>
                                            <div>{{ $sa['dh'] }}</div>
                                        </td>

                                        {{-- Status --}}
                                        <td class="px-4 py-4 whitespace-nowrap">
                                            <div class="font-semibold {{ $up ? 'text-teal-600 dark:text-teal-400' : 'text-red-600 dark:text-red-400' }}">
                                                {{ ucfirst(strtolower($sa['state'] ?? 'Unknown')) }}
                                            </div>
                                            @if ($up)
                                                <div class="text-xs {{ $up ? 'text-teal-600/80 dark:text-teal-400/80' : '' }}">
                                                    {{ number_format($sa['established']) }} seconds ({{ $hms($sa['established']) }}) ago
                                                </div>
                                            @endif
                                            <button type="button"
                                                class="mt-2 inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded bg-red-600 hover:bg-red-700 text-white transition-colors">
                                                <i class="ti ti-trash text-[13px]"></i> Disconnect P1
                                            </button>
                                        </td>
                                    </tr>

                                    {{-- Child SA expandable row --}}
                                    @if (!empty($sa['children']))
                                        <tr x-show="open" style="display:none;" class="bg-gray-50 dark:bg-gray-900/40">
                                            <td colspan="8" class="px-6 py-4">
                                                <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 mb-2 uppercase tracking-wider">Child SA entries</div>
                                                <div class="overflow-x-auto">
                                                    <table class="min-w-full text-xs">
                                                        <thead class="text-gray-400 uppercase tracking-wider">
                                                            <tr>
                                                                <th class="px-3 py-2 text-left font-medium">Name</th>
                                                                <th class="px-3 py-2 text-left font-medium">State</th>
                                                                <th class="px-3 py-2 text-left font-medium">Local subnet</th>
                                                                <th class="px-3 py-2 text-left font-medium">Remote subnet</th>
                                                                <th class="px-3 py-2 text-left font-medium">SPIs (in/out)</th>
                                                                <th class="px-3 py-2 text-left font-medium">Algo</th>
                                                                <th class="px-3 py-2 text-left font-medium">Bytes (in/out)</th>
                                                                <th class="px-3 py-2 text-left font-medium">Life / Rekey</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody class="font-mono text-gray-600 dark:text-gray-300">
                                                            @foreach ($sa['children'] as $c)
                                                                <tr class="border-t border-gray-100 dark:border-gray-700/50">
                                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $c['name'] }} <span class="text-gray-400">#{{ $c['reqid'] }}</span></td>
                                                                    <td class="px-3 py-2 whitespace-nowrap">
                                                                        <span class="px-1.5 py-0.5 rounded bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400 font-sans font-medium">{{ ucfirst(strtolower($c['state'])) }}</span>
                                                                        <span class="ml-1 text-gray-400">{{ $c['mode'] }}/{{ $c['proto'] }}</span>
                                                                    </td>
                                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $c['local_ts'] }}</td>
                                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $c['remote_ts'] }}</td>
                                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $c['spi_in'] }} / {{ $c['spi_out'] }}</td>
                                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $c['encr'] }} ({{ $c['keysize'] }})</td>
                                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $bytes($c['bytes_in']) }} / {{ $bytes($c['bytes_out']) }}</td>
                                                                    <td class="px-3 py-2 whitespace-nowrap">{{ $hms($c['life']) }} / {{ $hms($c['rekey']) }}</td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </td>
                                        </tr>
                                    @endif
                                    </tbody>
                                @endforeach
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>
