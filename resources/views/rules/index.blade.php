<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $firewall->name }} - Firewall Rules
            </h2>
            <a href="{{ route('firewall.dashboard', $firewall) }}"
                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <x-confirm-delete-modal>
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('error'))
                <div class="bg-red-500 text-white p-4 rounded-lg mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-4">
                <div class="p-4 text-gray-900 dark:text-gray-100 flex justify-between items-center">
                    <form method="GET" action="{{ route('firewall.rules.index', $firewall) }}"
                        class="flex gap-3 items-center">
                        <label for="interface" class="text-sm font-medium">Filter by Interface:</label>
                        <select name="interface" id="interface" onchange="this.form.submit()"
                            class="rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            <option value="">All Interfaces</option>
                            <option value="wan" {{ $interfaceFilter === 'wan' ? 'selected' : '' }}>WAN</option>
                            <option value="lan" {{ $interfaceFilter === 'lan' ? 'selected' : '' }}>LAN</option>
                        </select>
                        @if($interfaceFilter)
                            <a href="{{ route('firewall.rules.index', $firewall) }}"
                                class="text-sm text-blue-600 hover:underline">Clear Filter</a>
                        @endif
                    </form>

                    <a href="{{ route('firewall.rules.create', $firewall) }}"
                        class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                        Add Rule
                    </a>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Firewall Rules
                        @if($interfaceFilter)
                            <span class="text-sm text-gray-500">({{ strtoupper($interfaceFilter) }})</span>
                        @endif
                    </h3>

                    @if(isset($rules['data']) && count($rules['data']) > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            #</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Interface</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Action</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Protocol</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Source</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Destination</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Description</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($rules['data'] as $index => $rule)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $index + 1 }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                @php
                                                    $iface = $rule['interface'] ?? '';
                                                    if (is_array($iface)) {
                                                        $iface = implode(', ', $iface);
                                                    }
                                                @endphp
                                                {{ strtoupper($iface) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ strtoupper($rule['ipprotocol'] ?? '-') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if(isset($rule['type']) && $rule['type'] === 'pass')
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        Pass
                                                    </span>
                                                @elseif(isset($rule['type']) && $rule['type'] === 'block')
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                        Block
                                                    </span>
                                                @elseif(isset($rule['type']) && $rule['type'] === 'reject')
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                                        Reject
                                                    </span>
                                                @else
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                        {{ $rule['type'] ?? 'N/A' }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ strtoupper($rule['protocol'] ?? 'any') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                                                {{ $rule['source']['address'] ?? $rule['src'] ?? 'any' }}
                                                @if(isset($rule['source']['port']) && $rule['source']['port'])
                                                    :{{ $rule['source']['port'] }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                                                {{ $rule['destination']['address'] ?? $rule['dst'] ?? 'any' }}
                                                @if(isset($rule['destination']['port']) && $rule['destination']['port'])
                                                    :{{ $rule['destination']['port'] }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                {{ $rule['descr'] ?? $rule['description'] ?? '-' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('firewall.rules.edit', [$firewall, $rule['tracker']]) }}"
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-600 mr-3">Edit</a>
                                                <button type="button" @click="openDelete('{{ route('firewall.rules.destroy', [$firewall, $rule['tracker']]) }}', '{{ addslashes($rule['descr'] ?? $rule['description'] ?? 'this rule') }}')"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                    Delete
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500">No firewall rules found{{ $interfaceFilter ? ' for this interface' : '' }}.
                        </p>
                    @endif
                </div>
            </div>
        </div>
        </x-confirm-delete-modal>
    </div>
</x-app-layout>
