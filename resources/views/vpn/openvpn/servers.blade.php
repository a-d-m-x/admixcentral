<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('OpenVPN Servers') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('status'))
                        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ session('status') }}
                        </div>
                    @endif
                    @if (session('success'))
                        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400">
                            {{ session('error') }}
                        </div>
                    @endif

                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Server List</h3>
                        @if(!auth()->user()->isReadOnly())
                        <x-link-button-add href="{{ route('vpn.openvpn.server.create', $firewall) }}">
                            Add Server
                        </x-link-button-add>
                        @endif
                    </div>

                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Protocol / Port</th>
                                    <th scope="col" class="py-3 px-6">Interface</th>
                                    <th scope="col" class="py-3 px-6">Tunnel Network</th>
                                    <th scope="col" class="py-3 px-6">Description</th>
                                    @if(!auth()->user()->isReadOnly())
                                    <th scope="col" class="py-3 px-6">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($servers as $server)
                                    <tr
                                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6">
                                            {{ $server['protocol'] ?? 'UDP' }} / {{ $server['local_port'] ?? '1194' }}
                                        </td>
                                        <td class="py-4 px-6">
                                            {{ strtoupper($server['interface'] ?? 'WAN') }}
                                        </td>
                                        <td class="py-4 px-6">
                                            {{ $server['tunnel_network'] ?? 'N/A' }}
                                        </td>
                                        <td class="py-4 px-6">
                                            {{ $server['description'] ?? '' }}
                                        </td>
                                        @if(!auth()->user()->isReadOnly())
                                        <td class="py-4 px-6 flex space-x-2">
                                            <a href="{{ route('vpn.openvpn.server.edit', [$firewall, $server['vpnid']]) }}"
                                                class="font-medium text-blue-600 dark:text-blue-500 hover:underline">Edit</a>
                                            <form
                                                action="{{ route('vpn.openvpn.server.destroy', [$firewall, $server['vpnid']]) }}"
                                                method="POST"
                                                onsubmit="return confirm('Are you sure you want to delete this server?');"
                                                class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                            </form>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="5" class="py-4 px-6 text-center">
                                            No OpenVPN servers found.
                                        </td>
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
