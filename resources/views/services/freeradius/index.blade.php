<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('FreeRADIUS') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            {{-- Tabs --}}
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="{{ route('services.freeradius.users.index', $firewall) }}"
                        class="{{ $tab === 'users' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Users
                    </a>
                    <a href="{{ route('services.freeradius.clients.index', $firewall) }}"
                        class="{{ $tab === 'clients' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        NAS / Clients
                    </a>
                    <a href="{{ route('services.freeradius.interfaces.index', $firewall) }}"
                        class="{{ $tab === 'interfaces' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Interfaces
                    </a>
                    <a href="{{ route('services.freeradius.settings', $firewall) }}"
                        class="{{ $tab === 'settings' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Settings
                    </a>
                </nav>
            </div>

            {{-- Messages --}}
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <x-confirm-delete-modal>

                {{-- Users Tab --}}
                @if($tab === 'users')
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Users</h3>
                        </div>

                        @if(isset($listingSupported) && !$listingSupported)
                            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4">
                                <strong class="font-bold">Notice:</strong>
                                <span class="block sm:inline">User listing is not supported by the pfSense API. You can add new
                                    users, but existing users cannot be displayed here.</span>
                            </div>
                        @else
                            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Username</th>
                                            <th scope="col" class="px-6 py-3">Description</th>
                                            <th scope="col" class="px-6 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($users as $user)
                                            <tr
                                                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    {{ $user['username'] ?? 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4">{{ $user['description'] ?? '' }}</td>
                                                <td class="px-6 py-4 text-right">
                                                    <a href="{{ route('services.freeradius.users.edit', ['firewall' => $firewall, 'username' => $user['username'] ?? '']) }}"
                                                        class="font-medium text-blue-600 dark:text-blue-500 hover:underline mr-3">Edit</a>
                                                    <button type="button" @click="openDelete('{{ route('services.freeradius.users.destroy', ['firewall' => $firewall, 'username' => $user['username'] ?? '']) }}', '{{ addslashes($user['username'] ?? 'this user') }}')" class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                                <td colspan="3" class="px-6 py-4 text-center">No Users found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Clients Tab --}}
                @if($tab === 'clients')
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">NAS / Clients</h3>
                            <x-link-button-add href="{{ route('services.freeradius.clients.create', $firewall) }}">
                                Add Client
                            </x-link-button-add>
                        </div>

                        @if(isset($listingSupported) && !$listingSupported)
                            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4">
                                <strong class="font-bold">Notice:</strong>
                                <span class="block sm:inline">Client listing is not supported by the pfSense API. You can add
                                    new clients, but existing clients cannot be displayed here.</span>
                            </div>
                        @else
                            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Client IP/Host</th>
                                            <th scope="col" class="px-6 py-3">Shortname</th>
                                            <th scope="col" class="px-6 py-3">Description</th>
                                            <th scope="col" class="px-6 py-3">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($clients as $client)
                                            <tr
                                                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                    {{ $client['client'] ?? 'N/A' }}
                                                </td>
                                                <td class="px-6 py-4">{{ $client['shortname'] ?? '' }}</td>
                                                <td class="px-6 py-4">{{ $client['description'] ?? '' }}</td>
                                                <td class="px-6 py-4 text-right">
                                                    <a href="{{ route('services.freeradius.clients.edit', ['firewall' => $firewall, 'id' => $client['client'] ?? '']) }}"
                                                        class="font-medium text-blue-600 dark:text-blue-500 hover:underline mr-3">Edit</a>
                                                    <button type="button" @click="openDelete('{{ route('services.freeradius.clients.destroy', ['firewall' => $firewall, 'id' => $client['client'] ?? '']) }}', '{{ addslashes($client['client'] ?? 'this client') }}')" class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                                </td>
                                            </tr>
                                        @empty
                                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                                <td colspan="4" class="px-6 py-4 text-center">No Clients found.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Interfaces Tab --}}
                @if($tab === 'interfaces')
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Interfaces</h3>
                        <p class="text-gray-500 mb-4">Read-only view for MVP. Configure full interface binding in settings
                            or via XML/API.</p>

                        @if(isset($listingSupported) && !$listingSupported)
                            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4">
                                <strong class="font-bold">Notice:</strong>
                                <span class="block sm:inline">Interface listing is not supported via current API
                                    endpoint.</span>
                            </div>
                        @else
                            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Interface</th>
                                            <th scope="col" class="px-6 py-3">Port</th>
                                            <th scope="col" class="px-6 py-3">Type</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse($interfaces as $iface)
                                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                                <td class="px-6 py-4">{{ $iface['interface'] ?? 'N/A' }}</td>
                                                <td class="px-6 py-4">{{ $iface['port'] ?? '' }}</td>
                                                <td class="px-6 py-4">{{ $iface['type'] ?? '' }}</td>
                                            </tr>
                                        @empty
                                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                                <td colspan="3" class="px-6 py-4 text-center">No Interfaces configured.</td>
                                            </tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Settings Tab --}}
                @if($tab === 'settings')
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">General Settings</h3>
                        @if(isset($settingsSupported) && !$settingsSupported)
                            <div class="bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded relative mb-4">
                                <strong class="font-bold">Notice:</strong>
                                <span class="block sm:inline">Global FreeRADIUS settings are not supported via the pfSense API
                                    in this version.</span>
                            </div>
                        @else
                            <form action="{{ route('services.freeradius.settings.update', $firewall) }}" method="POST">
                                @csrf
                                <div class="grid grid-cols-1 gap-6">
                                    <div>
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="enable" value="1"
                                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                                {{ isset($settings['enable']) ? 'checked' : '' }}>
                                            <span class="ml-2 text-gray-700 dark:text-gray-300">Enable FreeRADIUS</span>
                                        </label>
                                    </div>
                                    {{-- Additional global settings like Max Requests, Timeouts etc. --}}
                                </div>
                                <div class="mt-6 flex justify-end">
                                    <x-primary-button>
                                        {{ __('Update Settings') }}
                                    </x-primary-button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endif

                </x-confirm-delete-modal>
            </div>
        </div>
    </div>
</x-app-layout>
