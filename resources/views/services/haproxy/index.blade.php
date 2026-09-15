<x-app-layout>
    <x-slot name="header">
    <x-slot name="header">
        <x-firewall-header title="{{ __('HAProxy Load Balancer') }}" :firewall="$firewall" />
    </x-slot>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            {{-- Tabs --}}
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="{{ route('services.haproxy.settings', $firewall) }}"
                        class="{{ $tab === 'settings' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Settings
                    </a>
                    <a href="{{ route('services.haproxy.frontends.index', $firewall) }}"
                        class="{{ $tab === 'frontends' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Frontends
                    </a>
                    <a href="{{ route('services.haproxy.backends.index', $firewall) }}"
                        class="{{ $tab === 'backends' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Backends
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

                {{-- Settings Tab --}}
                @if($tab === 'settings')
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">General Settings</h3>
                        <form action="{{ route('services.haproxy.settings.update', $firewall) }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="enable" value="1"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                            {{ isset($settings['enable']) ? 'checked' : '' }}>
                                        <span class="ml-2 text-gray-700 dark:text-gray-300">Enable HAProxy</span>
                                    </label>
                                </div>
                                <div>
                                    <x-input-label for="maxconn" :value="__('Maximum Connections')" />
                                    <x-text-input id="maxconn" class="block mt-1 w-full max-w-sm" type="number"
                                        name="maxconn" :value="$settings['maxconn'] ?? 1000" />
                                    <p class="text-sm text-gray-500 mt-1">Per-process maximum number of concurrent
                                        connections.</p>
                                </div>
                                {{-- Add more global settings as needed --}}
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-primary-button>
                                    {{ __('Update Settings') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif

                {{-- Frontends Tab --}}
                @if($tab === 'frontends')
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Frontends</h3>
                            <x-link-button-add href="{{ route('services.haproxy.frontends.create', $firewall) }}">
                                Add Frontend
                            </x-link-button-add>
                        </div>
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Name</th>
                                        <th scope="col" class="px-6 py-3">Description</th>
                                        <th scope="col" class="px-6 py-3">Status</th>
                                        <th scope="col" class="px-6 py-3">Type</th>
                                        <th scope="col" class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($frontends as $frontend)
                                        <tr
                                            class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $frontend['name'] }}
                                            </td>
                                            <td class="px-6 py-4">{{ $frontend['description'] ?? '' }}</td>
                                            <td class="px-6 py-4">
                                                <span
                                                    class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ ($frontend['status'] ?? '') === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ ucfirst($frontend['status'] ?? 'unknown') }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">{{ strtoupper($frontend['type'] ?? 'HTTP') }}</td>
                                            <td class="px-6 py-4 text-right">
                                                <a href="{{ route('services.haproxy.frontends.edit', ['firewall' => $firewall, 'id' => $frontend['id'] ?? $frontend['name']]) }}"
                                                    class="font-medium text-blue-600 dark:text-blue-500 hover:underline mr-3">Edit</a>
                                                <button type="button" @click="openDelete('{{ route('services.haproxy.frontends.destroy', ['firewall' => $firewall, 'id' => $frontend['id'] ?? $frontend['name']]) }}', '{{ addslashes($frontend['name'] ?? 'this frontend') }}')" class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                            <td colspan="5" class="px-6 py-4 text-center">No Frontends found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Backends Tab --}}
                @if($tab === 'backends')
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Backends</h3>
                            <x-link-button-add href="{{ route('services.haproxy.backends.create', $firewall) }}">
                                Add Backend
                            </x-link-button-add>
                        </div>
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead
                                    class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Name</th>
                                        <th scope="col" class="px-6 py-3">Description</th>
                                        <th scope="col" class="px-6 py-3">Mode</th> {{-- Balance mode --}}
                                        <th scope="col" class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($backends as $backend)
                                        <tr
                                            class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $backend['name'] }}
                                            </td>
                                            <td class="px-6 py-4">{{ $backend['description'] ?? '' }}</td>
                                            <td class="px-6 py-4">{{ $backend['mode'] ?? 'http' }}</td> {{-- e.g. balance --}}
                                            <td class="px-6 py-4 text-right">
                                                <a href="{{ route('services.haproxy.backends.edit', ['firewall' => $firewall, 'id' => $backend['id'] ?? $backend['name']]) }}"
                                                    class="font-medium text-blue-600 dark:text-blue-500 hover:underline mr-3">Edit</a>
                                                <button type="button" @click="openDelete('{{ route('services.haproxy.backends.destroy', ['firewall' => $firewall, 'id' => $backend['id'] ?? $backend['name']]) }}', '{{ addslashes($backend['name'] ?? 'this backend') }}')" class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                            <td colspan="4" class="px-6 py-4 text-center">No Backends found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                </x-confirm-delete-modal>
            </div>
        </div>
    </div>
</x-app-layout>
