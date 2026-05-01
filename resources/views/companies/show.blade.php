<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $company->name }}
                </h2>
                <div class="mt-1 flex items-center text-sm text-gray-500 dark:text-gray-400">
                     <a href="{{ route('dashboard') }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors duration-200">
                        {{ __('Dashboard') }}
                     </a>
                     <span class="mx-2 text-gray-300 dark:text-gray-600">/</span>
                     <a href="{{ route('companies.index') }}" class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors duration-200 hover:underline">
                        {{ __('Companies') }}
                     </a>
                </div>
            </div>
            @if(auth()->user()->isGlobalAdmin())
                <a href="{{ route('companies.edit', $company) }}"
                    class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />
                    </svg>
                    Edit Company
                </a>
            @endif
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">
                <!-- Company Details -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex flex-col h-full">
                    <div class="p-6 text-gray-900 dark:text-gray-100 flex-1 flex flex-col">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                            Details
                        </h3>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg p-4 h-full overflow-hidden flex flex-col gap-4">
                            <div class="break-words">
                                <p class="text-gray-600 dark:text-gray-300 text-sm leading-relaxed">
                                    {{ $company->description ?? 'No description provided.' }}
                                </p>
                            </div>
                            
                            @if($company->address)
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">Address:</span>
                                    <span class="text-gray-600 dark:text-gray-400">{{ $company->address }}</span>
                                </div>
                            @endif

                            @if($company->latitude && $company->longitude)
                                <div id="map" class="w-full h-48 rounded-lg border border-gray-200 dark:border-gray-600 flex-1 min-h-[200px]" style="z-index: 0;"></div>
                                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
                                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function() {
                                        var map = L.map('map').setView([{{ $company->latitude }}, {{ $company->longitude }}], 13);
                                        L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            maxZoom: 19,
                                            attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                        }).addTo(map);
                                        L.marker([{{ $company->latitude }}, {{ $company->longitude }}]).addTo(map)
                                            .bindPopup('{{ addslashes($company->name) }}')
                                            .openPopup();
                                    });
                                </script>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Users Section -->
                <div class="lg:col-span-3 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex flex-col h-full">
                    <div class="p-6 text-gray-900 dark:text-gray-100 flex-1">
                        <div class="flex justify-between items-center mb-6">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center">
                                <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                Users
                            </h3>
                            <a href="{{ route('users.create', ['company_id' => $company->id]) }}"
                                class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                                Add User
                            </a>
                        </div>

                        @if($company->users->count() > 0)
                            <div class="overflow-x-auto border border-gray-100 dark:border-gray-700 rounded-lg">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700/80">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                                            <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"></th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                        @foreach($company->users as $user)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-150">
                                                <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $user->name }}</td>
                                                <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">{{ $user->email }}</td>
                                                <td class="px-4 py-3 text-sm">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $user->role === 'admin' ? 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                        {{ ucfirst($user->role) }}
                                                    </span>
                                                </td>
                                                <td class="px-4 py-3 text-right text-sm">
                                                    <a href="{{ route('users.edit', $user) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 font-medium">Edit</a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @else
                            <div class="text-center py-8 bg-gray-50 dark:bg-gray-700/30 rounded-lg border border-dashed border-gray-300 dark:border-gray-600">
                                <p class="text-gray-500 text-sm">No users assigned to this company.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Firewalls Section -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg flex flex-col">
                <div class="p-6 text-gray-900 dark:text-gray-100 flex-1">
                    <div class="flex justify-between items-center mb-6">
                        <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 flex items-center">
                            <svg class="w-5 h-5 mr-2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                            Firewalls
                        </h3>
                        <a href="{{ route('firewalls.create', ['company_id' => $company->id]) }}"
                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Firewall
                        </a>
                    </div>

                    @if($company->firewalls->count() > 0)
                        <div class="overflow-x-auto border border-gray-100 dark:border-gray-700 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700/80">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">URL</th>
                                        <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @foreach($company->firewalls as $firewall)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition duration-150">
                                            {{-- Name --}}
                                            <td class="px-4 py-3">
                                                <a href="{{ route('firewall.dashboard', $firewall) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium hover:underline">
                                                    {{ $firewall->name }}
                                                </a>
                                            </td>
                                            {{-- Status Badge --}}
                                            <td class="px-4 py-3">
                                                @if($firewall->is_online === true)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-green-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                        Online
                                                    </span>
                                                @elseif($firewall->is_online === false)
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                        <svg class="-ml-0.5 mr-1.5 h-2 w-2 text-red-400" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                                        Offline
                                                    </span>
                                                @else
                                                    <span class="inline-block w-16 h-5 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse"></span>
                                                @endif
                                            </td>
                                            {{-- URL as external link --}}
                                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                                @if($firewall->url)
                                                    <a href="{{ $firewall->url }}" target="_blank" rel="noopener noreferrer"
                                                        class="text-indigo-600 hover:text-indigo-900 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300">
                                                        {{ $firewall->url }}
                                                    </a>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right text-sm">
                                                <a href="{{ route('firewalls.edit', $firewall) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 font-medium">Edit</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-8 bg-gray-50 dark:bg-gray-700/30 rounded-lg border border-dashed border-gray-300 dark:border-gray-600">
                            <p class="text-gray-500 text-sm">No firewalls assigned to this company.</p>
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
