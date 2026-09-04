<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('NDP Table') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if($firewall->isOpnSense())
                        <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="py-3 px-6">IPv6 Address</th>
                                        <th scope="col" class="py-3 px-6">MAC Address</th>
                                        <th scope="col" class="py-3 px-6">Interface</th>
                                        <th scope="col" class="py-3 px-6">Status</th>
                                        <th scope="col" class="py-3 px-6">Manufacturer</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($ndpTable as $entry)
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="py-4 px-6 font-mono text-xs text-gray-900 dark:text-white">
                                                {{ $entry['ip'] ?? '' }}
                                            </td>
                                            <td class="py-4 px-6 font-mono text-xs">
                                                {{ $entry['mac'] ?? '' }}
                                            </td>
                                            <td class="py-4 px-6">
                                                {{ $entry['intf_description'] ?? ($entry['intf'] ?? '') }}
                                            </td>
                                            <td class="py-4 px-6">
                                                <span class="px-2 py-0.5 text-xs rounded bg-gray-100 dark:bg-gray-700">
                                                    {{ $entry['status'] ?? 'reachable' }}
                                                </span>
                                            </td>
                                            <td class="py-4 px-6 text-xs text-gray-500">
                                                {{ $entry['manufacturer'] ?? '—' }}
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                            <td colspan="5" class="py-6 px-6 text-center text-gray-500">
                                                No NDP neighbor cache entries found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @else
                        <x-api-not-supported :firewall="$firewall" urlSuffix="diag_ndp.php" featureName="NDP Table" />
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
