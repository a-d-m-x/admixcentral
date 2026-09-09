<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Queues Status') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($firewall->isOpnSense())
                        {{-- Pipes Section --}}
                        <div class="mb-8">
                            <div class="flex items-center justify-between mb-4">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Traffic Shaper Pipes</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">Bandwidth limiters and schedulers.</p>
                                </div>
                                <a href="{{ route('firewall.limiters.index', $firewall) }}" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 font-medium">
                                    Manage Limiters &rarr;
                                </a>
                            </div>
                            <div class="pf-table-container">
                                <table class="pf-table">
                                    <thead>
                                        <tr>
                                            <th>Number</th>
                                            <th>Description</th>
                                            <th>Bandwidth</th>
                                            <th>Scheduler</th>
                                            <th>Mask</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($pipes as $pipe)
                                            <tr>
                                                <td class="font-mono text-sm" data-label="Number">#{{ $pipe['number'] ?? '-' }}</td>
                                                <td class="font-medium" data-label="Description">{{ $pipe['description'] ?? '-' }}</td>
                                                <td class="font-mono text-sm" data-label="Bandwidth">{{ $pipe['bandwidth'] ?? '0' }} {{ $pipe['bandwidthMetric'] ?? 'Kbit' }}/s</td>
                                                <td data-label="Scheduler">{{ $pipe['scheduler'] ?? 'FIFO' }}</td>
                                                <td data-label="Mask">{{ $pipe['mask'] ?? 'none' }}</td>
                                                <td data-label="Status">
                                                    @if(($pipe['enabled'] ?? '1') === '1')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">Enabled</span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Disabled</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="6" class="text-center py-6 text-gray-500 dark:text-gray-400">No pipes configured.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Queues Section --}}
                        <div class="mb-8">
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-2">Traffic Shaper Queues</h3>
                            <div class="pf-table-container">
                                <table class="pf-table">
                                    <thead>
                                        <tr>
                                            <th>Number</th>
                                            <th>Description</th>
                                            <th>Target Pipe</th>
                                            <th>Weight</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($queues as $q)
                                            <tr>
                                                <td class="font-mono text-sm" data-label="Number">#{{ $q['number'] ?? '-' }}</td>
                                                <td class="font-medium" data-label="Description">{{ $q['description'] ?? '-' }}</td>
                                                <td class="font-mono text-sm" data-label="Target Pipe">{{ $q['pipe'] ?? '-' }}</td>
                                                <td data-label="Weight">{{ $q['weight'] ?? '-' }}</td>
                                                <td data-label="Status">
                                                    @if(($q['enabled'] ?? '1') === '1')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">Enabled</span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Disabled</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="5" class="text-center py-6 text-gray-500 dark:text-gray-400">No queues configured.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {{-- Rules Section --}}
                        <div>
                            <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100 mb-2">Traffic Shaper Rules</h3>
                            <div class="pf-table-container">
                                <table class="pf-table">
                                    <thead>
                                        <tr>
                                            <th>Sequence</th>
                                            <th>Description</th>
                                            <th>Target</th>
                                            <th>Protocol</th>
                                            <th>Source</th>
                                            <th>Destination</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($rules as $rule)
                                            <tr>
                                                <td class="font-mono text-sm" data-label="Sequence">{{ $rule['sequence'] ?? '-' }}</td>
                                                <td class="font-medium" data-label="Description">{{ $rule['description'] ?? '-' }}</td>
                                                <td class="font-mono text-sm" data-label="Target">{{ $rule['target'] ?? '-' }}</td>
                                                <td data-label="Protocol">{{ $rule['proto'] ?? 'all' }}</td>
                                                <td data-label="Source">{{ $rule['source'] ?? 'any' }}</td>
                                                <td data-label="Destination">{{ $rule['destination'] ?? 'any' }}</td>
                                                <td data-label="Status">
                                                    @if(($rule['enabled'] ?? '1') === '1')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">Enabled</span>
                                                    @else
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">Disabled</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @empty
                                            <tr>
                                                <td colspan="7" class="text-center py-6 text-gray-500 dark:text-gray-400">No shaper rules configured.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @else
                        <x-api-not-supported :firewall="$firewall" urlSuffix="status_queues.php" featureName="Queues Status" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
