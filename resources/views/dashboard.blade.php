<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Dashboard') }}
            </h2>
            @if(auth()->user()->role === 'admin')
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false"
                        class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded inline-flex items-center transition-colors duration-200">
                        <span>Add New</span>
                        <svg class="fill-current h-4 w-4 ml-2 transform transition-transform duration-200"
                            :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                            <path
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" />
                        </svg>
                    </button>

                    <div x-show="open"
                        class="absolute right-0 mt-2 w-56 bg-white dark:bg-gray-800 rounded-md shadow-xl z-50 ring-1 ring-black ring-opacity-5 divide-y divide-gray-100 dark:divide-gray-700 focus:outline-none"
                        style="display: none;" x-transition:enter="transition ease-out duration-100"
                        x-transition:enter-start="transform opacity-0 scale-95"
                        x-transition:enter-end="transform opacity-100 scale-100"
                        x-transition:leave="transition ease-in duration-75"
                        x-transition:leave-start="transform opacity-100 scale-100"
                        x-transition:leave-end="transform opacity-0 scale-95">

                        <!-- Operational -->
                        <div class="py-1">
                            <a href="{{ route('firewalls.create') }}"
                                class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/50 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors duration-150">
                                <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-indigo-500 dark:group-hover:text-indigo-400"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01" />
                                </svg>
                                Firewall
                            </a>
                            <a href="{{ route('companies.create') }}"
                                class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/50 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors duration-150">
                                <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-indigo-500 dark:group-hover:text-indigo-400"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                Company
                            </a>
                        </div>

                        <!-- System -->
                        <div class="py-1">
                            <a href="{{ route('users.create') }}"
                                class="group flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-indigo-50 dark:hover:bg-indigo-900/50 hover:text-indigo-700 dark:hover:text-indigo-300 transition-colors duration-150">
                                <svg class="mr-3 h-5 w-5 text-gray-400 group-hover:text-indigo-500 dark:group-hover:text-indigo-400"
                                    xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z" />
                                </svg>
                                User
                            </a>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </x-slot>

    <div class="py-6 sm:py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="overflow-hidden">
                <div class="p-6 text-gray-900 dark:text-gray-100" x-data='dashboard({{ $firewallsWithStatus->map(fn($f) => [
    "id" => $f->id,
    "staticInfo" => strtolower($f->name . " " . $f->company->name . " " . $f->url . " " . $f->hostname),
    "online" => isset($f->cached_status["online"]) ? $f->cached_status["online"] : false,
    "companyName" => $f->company->name
])->values()->toJson() }})'>
                    <!-- Widgets Grid -->
                    <!-- Invisible Coordinator: Manages batch status updates -->
                    <div x-data="dashboardCoordinator({{ $firewallsWithStatus->pluck('id')->toJson() }})"
                        style="display: none;"></div>

                    <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
                        <!-- Firewalls Widget -->
                        <div
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center justify-center">
                            <div
                                class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-500 dark:text-blue-300 mr-4">
                                <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Firewalls</p>
                                <div class="flex items-center text-4xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ $totalFirewalls }}
                                    <!-- Unified Status Badge -->
                                    <span x-data="{ isReady: false }"
                                        x-effect="isReady = (offlineCount > 0 || (offlineCount === 0 && showOnlineBadge))"
                                        class="ml-3 flex items-center text-xs font-medium sm:px-2 sm:py-1 p-0 rounded-full align-middle transition-all duration-500 ease-in-out"
                                        :class="
                                            !isReady 
                                                ? 'bg-transparent sm:bg-gray-100 sm:dark:bg-gray-800 animate-pulse' 
                                                : (offlineCount > 0 
                                                    ? 'text-red-600 dark:text-red-400 sm:bg-red-100 sm:dark:bg-red-900 bg-transparent' 
                                                    : 'text-green-600 dark:text-green-400 sm:bg-green-100 sm:dark:bg-green-900 bg-transparent')
                                        ">

                                        <!-- Content -->
                                        <div class="flex items-center">
                                            <!-- Desktop Dot/Indicator -->
                                            <span class="relative flex h-2 w-2 sm:mr-2">
                                                <!-- Loading Dot (Desktop) -->
                                                <span x-show="!isReady"
                                                    class="w-full h-full bg-gray-300 dark:bg-gray-600 rounded-full hidden sm:inline-flex"></span>

                                                <!-- Offline Dot -->
                                                <template x-if="isReady && offlineCount > 0">
                                                    <span class="w-full h-full relative inline-flex">
                                                        <span
                                                            class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-500 opacity-75"></span>
                                                        <span
                                                            class="relative inline-flex rounded-full h-2 w-2 bg-red-600"></span>
                                                    </span>
                                                </template>

                                                <!-- Online Dot -->
                                                <template x-if="isReady && offlineCount === 0">
                                                    <span
                                                        class="relative inline-flex rounded-full h-2 w-2 bg-green-600"></span>
                                                </template>
                                            </span>

                                            <!-- Mobile Dot (Only shows when loading or when ready; basically always checking isReady logic implicitly for color, but size is fixed) -->
                                            <!-- Wait, on mobile we ONLY show the dot. The container has p-0. -->
                                            <!-- The 'Desktop Dot' above has 'sm:mr-2'. On mobile it has no margin 
                                                 BUT the 'Desktop Dot' (lines above) handles the actual colored circle. 
                                                 We just need to make sure the 'sm:mr-2' is removed on mobile. 
                                                 Actually, the dot above is 'relative flex h-2 w-2'. 
                                                 It works for mobile too. -->

                                            <!-- Text Content (Desktop Only) -->
                                            <span x-show="!isReady" class="w-16 h-4 hidden sm:inline-block"></span>

                                            <span x-show="isReady" class="hidden sm:inline">
                                                <span
                                                    x-text="offlineCount > 0 ? offlineCount + ' Offline' : 'All Online'"></span>
                                            </span>
                                        </div>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- System Health Widget -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center justify-center"
                            id="system-health-widget">
                            <div id="health-icon"
                                class="p-3 rounded-full bg-gray-100 dark:bg-gray-700 mr-4 transition-all duration-300">
                                <svg class="h-8 w-8 text-gray-400 dark:text-gray-500 transition-colors duration-300"
                                    xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Health</p>
                                <!-- Skeleton Placeholder -->
                                <div id="health-skeleton" class="animate-pulse">
                                    <div class="h-8 bg-gray-200 dark:bg-gray-700 rounded w-16 mb-1"></div>
                                </div>
                                <!-- Actual Data (hidden initially) -->
                                <div id="health-data" style="display: none;">
                                    <p id="health-score" class="text-4xl font-bold text-gray-900 dark:text-gray-100">--
                                    </p>
                                </div>
                            </div>
                        </div>

                        @if($adminLabel === 'Global Admin')
                            <!-- Total Companies Widget -->
                            <div
                                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center justify-center">
                                <div
                                    class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-500 dark:text-indigo-300 mr-4">
                                    <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Companies</p>
                                    <p class="text-4xl font-bold text-gray-900 dark:text-gray-100">{{ $totalCompanies }}</p>
                                </div>
                            </div>
                        @else
                            <!-- Gateways Widget -->
                            <div
                                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center justify-center">
                                <div
                                    class="relative p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-500 dark:text-blue-300 mr-4">
                                    <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                        stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Gateways</p>
                                    <div class="flex items-center gap-2">
                                        <p class="text-4xl font-bold text-gray-900 dark:text-gray-100">
                                            {{ $totalGateways ?? 0 }}
                                        </p>
                                        @if(isset($downGateways) && $downGateways > 0)
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-300">
                                                <span class="w-2 h-2 mr-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                                {{ $downGateways }} Down
                                            </span>
                                        @else
                                            <span
                                                class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-300">
                                                <span class="w-2 h-2 mr-1.5 bg-green-500 rounded-full"></span>
                                                All Online
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Total Users Widget -->
                        <div
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center justify-center">
                            <div
                                class="p-3 rounded-full bg-purple-100 dark:bg-purple-900 text-purple-500 dark:text-purple-300 mr-4">
                                <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                    stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Users</p>
                                <div class="flex items-center gap-2">
                                    <p class="text-4xl font-bold text-gray-900 dark:text-gray-100">{{ $totalUsers }}</p>
                                    {{-- Display badge for Admins --}}
                                    @if($totalAdmins > 0)
                                        <span
                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-purple-100 dark:bg-purple-900 text-purple-800 dark:text-purple-300">
                                            {{ $totalAdmins }}<span
                                                class="hidden sm:inline">&nbsp;{{ $adminLabel }}{{ $totalAdmins > 1 ? 's' : '' }}</span>
                                        </span>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                    @if($firewallsWithStatus->isNotEmpty())
                        <div class="flex flex-col xl:flex-row justify-between items-start xl:items-center gap-4 mb-4">
                            <div class="flex flex-wrap w-full xl:w-auto justify-between items-center gap-2">
                                <h3 class="text-lg font-semibold whitespace-nowrap">Managed Firewalls</h3>
                                <div class="flex items-center gap-2 xl:hidden">
                                    <span class="text-xs text-gray-500 font-normal"
                                        x-text="'Showing ' + filteredCount() + ' of ' + items.length + ' firewalls'"></span>
                                    {{-- Layout Switcher (mobile) --}}
                                    <div class="flex items-center gap-0.5 bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5">
                                        <button type="button" title="Card view"
                                            @click="$store.dashLayout.set('cards')"
                                            :class="$store.dashLayout.layout === 'cards' ? 'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                            class="p-1.5 rounded-md transition-all duration-150">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                        </button>
                                        <button type="button" title="Compact view"
                                            @click="$store.dashLayout.set('compact')"
                                            :class="$store.dashLayout.layout === 'compact' ? 'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                            class="p-1.5 rounded-md transition-all duration-150">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="4" height="4" rx="0.5"/><rect x="10" y="3" width="4" height="4" rx="0.5"/><rect x="17" y="3" width="4" height="4" rx="0.5"/><rect x="3" y="10" width="4" height="4" rx="0.5"/><rect x="10" y="10" width="4" height="4" rx="0.5"/><rect x="17" y="10" width="4" height="4" rx="0.5"/><rect x="3" y="17" width="4" height="4" rx="0.5"/><rect x="10" y="17" width="4" height="4" rx="0.5"/><rect x="17" y="17" width="4" height="4" rx="0.5"/></svg>
                                        </button>
                                        <button type="button" title="List view"
                                            @click="$store.dashLayout.set('list')"
                                            :class="$store.dashLayout.layout === 'list' ? 'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                            class="p-1.5 rounded-md transition-all duration-150">
                                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div
                                class="flex flex-col sm:grid sm:grid-cols-2 xl:flex xl:flex-row items-stretch sm:items-center gap-2 w-full xl:w-auto">
                                <span
                                    class="text-xs text-gray-500 font-normal self-start xl:self-center whitespace-nowrap hidden xl:inline"
                                    x-text="'Showing ' + filteredCount() + ' of ' + items.length + ' firewalls'"></span>
                                {{-- Layout Switcher (desktop) --}}
                                <div class="hidden xl:flex items-center gap-0.5 bg-gray-100 dark:bg-gray-700 rounded-lg p-0.5 mr-2">
                                    <button type="button" title="Card view"
                                        @click="$store.dashLayout.set('cards')"
                                        :class="$store.dashLayout.layout === 'cards' ? 'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                        class="p-1.5 rounded-md transition-all duration-150">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
                                    </button>
                                    <button type="button" title="Compact view"
                                        @click="$store.dashLayout.set('compact')"
                                        :class="$store.dashLayout.layout === 'compact' ? 'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                        class="p-1.5 rounded-md transition-all duration-150">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="4" height="4" rx="0.5"/><rect x="10" y="3" width="4" height="4" rx="0.5"/><rect x="17" y="3" width="4" height="4" rx="0.5"/><rect x="3" y="10" width="4" height="4" rx="0.5"/><rect x="10" y="10" width="4" height="4" rx="0.5"/><rect x="17" y="10" width="4" height="4" rx="0.5"/><rect x="3" y="17" width="4" height="4" rx="0.5"/><rect x="10" y="17" width="4" height="4" rx="0.5"/><rect x="17" y="17" width="4" height="4" rx="0.5"/></svg>
                                    </button>
                                    <button type="button" title="List view"
                                        @click="$store.dashLayout.set('list')"
                                        :class="$store.dashLayout.layout === 'list' ? 'bg-white dark:bg-gray-600 shadow text-indigo-600 dark:text-indigo-400' : 'text-gray-400 hover:text-gray-600 dark:hover:text-gray-300'"
                                        class="p-1.5 rounded-md transition-all duration-150">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
                                    </button>
                                </div>
                                <!-- Customer Filter -->
                                @if(auth()->user()->isGlobalAdmin())
                                    @php
                                        $uniqueCustomers = $firewallsWithStatus->pluck('company.name')->unique()->sort()->values();
                                    @endphp
                                    <div x-data="{ 
                                                                                                                                                    open: false, 
                                                                                                                                                    filter: '', 
                                                                                                                                                    customers: {{ json_encode($uniqueCustomers) }},
                                                                                                                                                    get filteredCustomers() {
                                                                                                                                                        if (this.filter === '') return this.customers.slice(0, 10);
                                                                                                                                                        return this.customers.filter(c => c.toLowerCase().includes(this.filter.toLowerCase())).slice(0, 10);
                                                                                                                                                    },
                                                                                                                                                    select(name) {
                                                                                                                                                        customerFilter = name;
                                                                                                                                                        this.open = false;
                                                                                                                                                        this.filter = '';
                                                                                                                                                    }
                                                                                                                                                }"
                                        class="relative w-full sm:col-span-1 xl:w-56" @keydown.escape="open = false"
                                        @click.outside="open = false">

                                        <!-- Trigger Button -->
                                        <button @click="open = !open" type="button"
                                            class="flex items-center justify-between w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition ease-in-out duration-150 min-w-0">
                                            <span class="truncate block min-w-0 text-left"
                                                x-text="customerFilter === 'all' ? 'All Customers' : (customerFilter.length > 22 ? customerFilter.substring(0, 20) + '…' : customerFilter)"
                                                :class="{'text-gray-500': customerFilter === 'all', 'text-gray-900 dark:text-gray-200': customerFilter !== 'all'}"
                                                :title="customerFilter !== 'all' ? customerFilter : ''"></span>
                                            <svg class="h-4 w-4 ml-2 flex-shrink-0 text-gray-500 transform transition-transform duration-200"
                                                :class="{'rotate-180': open}" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>

                                        <!-- Dropdown Menu -->
                                        <div x-show="open" x-transition.opacity.duration.200ms style="display: none;"
                                            class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md shadow-lg overflow-hidden">

                                            <!-- Search Input -->
                                            <div
                                                class="p-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                                <input x-model="filter" type="text" placeholder="Search..."
                                                    class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                                            </div>

                                            <!-- List -->
                                            <ul class="max-h-60 overflow-y-auto py-1">
                                                <li @click="select('all')"
                                                    class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm italic text-gray-800 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700 mb-1"
                                                    :class="{'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700': customerFilter === 'all'}">
                                                    All Customers
                                                </li>
                                                <template x-for="name in filteredCustomers" :key="name">
                                                    <li @click="select(name)"
                                                        class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm text-gray-700 dark:text-gray-300 truncate"
                                                        :class="{'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700': customerFilter === name}"
                                                        x-text="name">
                                                    </li>
                                                </template>
                                                <li x-show="filteredCustomers.length === 0"
                                                    class="px-4 py-2 text-sm text-gray-400 italic text-center">No matches
                                                </li>
                                            </ul>
                                        </div>
                                    </div>
                                @endif<!-- Status Filter -->
                                <select x-model="statusFilter"
                                    class="rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500 w-full sm:col-span-1 xl:w-auto">
                                    <option value="all">All Status</option>
                                    <option value="online">Online</option>
                                    <option value="offline">Offline</option>
                                </select>

                                <!-- Search Input -->
                                <div
                                    class="flex items-center w-full sm:col-span-2 xl:w-64 rounded-lg border border-gray-300 dark:border-gray-600 dark:bg-gray-700 focus-within:ring-2 focus-within:ring-indigo-500 bg-white">
                                    <div class="pl-3 pr-2 text-gray-500">
                                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" x-model="search" placeholder="Search..."
                                        class="form-input-reset flex-1 min-w-0">
                                    <button x-show="search.length > 0" @click="search = ''"
                                        class="pr-3 text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if($firewallsWithStatus->isEmpty())
                        <!-- Enhanced Empty State -->
                        <div
                            class="text-center py-12 bg-white dark:bg-gray-800 rounded-lg shadow-sm border border-gray-100 dark:border-gray-700">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                    d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z" />
                            </svg>
                            <h3 class="mt-4 text-sm font-semibold text-gray-900 dark:text-white">No firewalls managed</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Get started by adding your first
                                firewall to Admix Central.</p>
                            <div class="mt-6">
                                <a href="{{ route('firewalls.create') }}"
                                    class="inline-flex items-center rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600">
                                    <svg class="-ml-0.5 mr-1.5 h-5 w-5" viewBox="0 0 20 20" fill="currentColor"
                                        aria-hidden="true">
                                        <path
                                            d="M10.75 4.75a.75.75 0 00-1.5 0v4.5h-4.5a.75.75 0 000 1.5h4.5v4.5a.75.75 0 001.5 0v-4.5h4.5a.75.75 0 000-1.5h-4.5v-4.5z" />
                                    </svg>
                                    Add Firewall
                                </a>
                            </div>
                        </div>
                    @else
                        <div
                            :class="{
                                'grid grid-cols-1 xl:grid-cols-2 gap-4':          $store.dashLayout.layout === 'cards',
                                'grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4 gap-3': $store.dashLayout.layout === 'compact',
                                'flex flex-col gap-1.5':                          $store.dashLayout.layout === 'list'
                            }">
                            @foreach($firewallsWithStatus as $firewall)
                                <div x-show="matches(search) && matchesFilters(statusFilter, customerFilter)"
                                    x-data="firewallCard(
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        {{ json_encode($firewall->cached_status) }}, 
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        '{{ strtolower($firewall->name . ' ' . $firewall->company->name . ' ' . $firewall->url . ' ' . $firewall->hostname) }}',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        '{{ route('firewall.check-status', $firewall) }}',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        {{ $firewall->id }},
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        '{{ $firewall->company->name }}'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    )"
                                    :class="{
                                        'rounded-2xl p-3 sm:p-4 shadow-md hover:shadow-xl': $store.dashLayout.layout === 'cards',
                                        'rounded-xl p-2.5 shadow-sm hover:shadow-md':       $store.dashLayout.layout === 'compact',
                                        'rounded-lg px-3 py-2 shadow-sm hover:bg-gray-50/60 dark:hover:bg-gray-700/20': $store.dashLayout.layout === 'list'
                                    }"
                                    class="relative border border-gray-100 dark:border-gray-700 bg-white dark:bg-gray-800 transition-all duration-200">

                                    {{-- ═══ LIST ROW VIEW ═══ --}}
                                    {{-- Layout: [identity flex-1] [metrics shrink-0 always-rendered] --}}
                                    <div x-show="$store.dashLayout.layout === 'list'" class="flex items-center gap-x-3 w-full min-w-0 py-0.5">

                                        {{-- ── Identity group (flex-1) ── --}}
                                        <div class="flex items-center gap-x-2 flex-1 min-w-0">
                                            {{-- Status dot — fixed 8px square so it never causes reflow --}}
                                            <div class="shrink-0 w-2 h-2 relative">
                                                <div class="absolute inset-0 rounded-full transition-colors duration-300"
                                                     :class="loading ? 'bg-gray-300 dark:bg-gray-600 animate-pulse' : (online ? 'bg-green-500' : 'bg-red-500 animate-pulse')"></div>
                                            </div>
                                            <a href="{{ route('firewall.dashboard', $firewall) }}"
                                               class="font-semibold text-sm text-gray-900 dark:text-gray-100 hover:text-indigo-600 dark:hover:text-indigo-400 hover:underline underline-offset-2 truncate shrink-0 max-w-[130px] sm:max-w-[180px]">{{ $firewall->name }}</a>
                                            @if(auth()->user()->role === 'admin')
                                                <a href="{{ route('companies.show', $firewall->company) }}"
                                                   class="text-xs text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 truncate shrink hidden md:block max-w-[110px]">{{ $firewall->company->name }}</a>
                                            @endif
                                            {{-- Uptime / Offline badge — fixed min-width to prevent reflow --}}
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 font-mono text-[10px] font-medium shrink-0 hidden sm:inline-flex min-w-[56px]"
                                                  :class="(!loading && online) ? 'opacity-100' : 'opacity-0'"
                                                  x-text="(!loading && online) ? formatUptime(status?.data?.uptime || status?.data?.uptime_text || status?.data?.uptime_string) : ''"></span>
                                            <span class="text-[10px] text-red-400 font-medium shrink-0 min-w-[40px]"
                                                  :class="(!loading && !online) ? 'opacity-100' : 'opacity-0'">Offline</span>
                                        </div>

                                        {{-- ── Gateways + Storage + Temp (lg+) — always in DOM, never collapses ── --}}
                                        <div class="hidden lg:flex items-center gap-x-3 shrink-0">
                                            <div class="h-3 w-px bg-gray-200 dark:bg-gray-700"></div>

                                            {{-- Gateways: label always visible; dots fade in. x-for on empty array = no DOM reflow --}}
                                            <div class="flex items-center gap-x-1">
                                                <span class="text-[10px] font-medium text-gray-400 dark:text-gray-500 shrink-0">Gateways</span>
                                                <div class="flex items-center gap-0.5 min-w-[8px]"
                                                     :class="(!loading && online) ? 'opacity-100' : 'opacity-0'">
                                                    <template x-for="gw in (status?.data?.gateways || [])" :key="gw.name">
                                                        <div class="relative group">
                                                            <div class="w-2 h-2 rounded-full cursor-default shrink-0"
                                                                 :class="{
                                                                     'bg-teal-500': gw.status === 'online' || gw.status === 'none',
                                                                     'bg-red-500':   gw.status === 'offline' || gw.status === 'down',
                                                                     'bg-yellow-500': gw.status && gw.status !== 'online' && gw.status !== 'none' && gw.status !== 'offline' && gw.status !== 'down'
                                                                 }"></div>
                                                            <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 z-20 hidden group-hover:block pointer-events-none">
                                                                <div class="bg-gray-900 dark:bg-gray-700 text-white text-[10px] rounded px-2 py-1 whitespace-nowrap shadow-lg">
                                                                    <div class="font-semibold" x-text="gw.descr || gw.name"></div>
                                                                    <div class="text-gray-400 font-mono text-[9px]" x-text="gw.monitorip || gw.srcip || ''"></div>
                                                                    <div class="capitalize"
                                                                         :class="(gw.status === 'online' || gw.status === 'none') ? 'text-teal-400' : (gw.status === 'offline' || gw.status === 'down') ? 'text-red-400' : 'text-yellow-400'"
                                                                         x-text="gw.status"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            </div>

                                            <div class="h-3 w-px bg-gray-200 dark:bg-gray-700"></div>

                                            {{-- Storage metrics — Label → Value → Bar format for clarity --}}
                                            <div class="flex items-center gap-x-3"
                                                 :class="(!loading && online) ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                                                {{-- Disk --}}
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">Disk</span>
                                                    <span class="text-[10px] tabular-nums font-medium text-gray-700 dark:text-gray-300 w-7 inline-block text-right shrink-0"
                                                          x-text="parseFloat(status?.data?.disk_usage || 0).toFixed(0) + '%'"></span>
                                                    <div class="w-8 bg-gray-200 dark:bg-gray-700 rounded-full h-1 shrink-0">
                                                        <div class="bg-yellow-500 h-1 rounded-full" :style="'width:' + (status?.data?.disk_usage || 0) + '%'"></div>
                                                    </div>
                                                </div>
                                                {{-- Swap --}}
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">Swap</span>
                                                    <span class="text-[10px] tabular-nums font-medium text-gray-700 dark:text-gray-300 w-7 inline-block text-right shrink-0"
                                                          x-text="(status?.data?.swap_usage != null ? parseFloat(status.data.swap_usage).toFixed(0) : '0') + '%'"></span>
                                                    <div class="w-8 bg-gray-200 dark:bg-gray-700 rounded-full h-1 shrink-0">
                                                        <div class="bg-yellow-400 h-1 rounded-full" :style="'width:' + (status?.data?.swap_usage || 0) + '%'"></div>
                                                    </div>
                                                </div>
                                                {{-- Temp — fixed w-12 so "—", "36°C", "100°C" never shift --}}
                                                <div class="flex items-center gap-1">
                                                    <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">Temp</span>
                                                    <span class="text-[10px] tabular-nums font-semibold w-12 inline-block text-right shrink-0"
                                                          :class="(status?.data?.temp_c || 0) > 75 ? 'text-red-500' : (status?.data?.temp_c || 0) > 60 ? 'text-yellow-500' : 'text-gray-600 dark:text-gray-300'"
                                                          x-text="(status?.data?.temp_c && status.data.temp_c > 1) ? status.data.temp_c + '°C' : (status?.data?.temperature || '—')"></span>
                                                </div>
                                            </div>
                                        </div>

                                        {{-- ── CPU + Mem + Traffic + Manage (sm+) — always in DOM ── --}}
                                        <div class="flex items-center gap-x-3 shrink-0">
                                            <div class="h-3 w-px bg-gray-200 dark:bg-gray-700 hidden sm:block"
                                                 :class="(!loading && online) ? 'opacity-100' : 'opacity-0'"></div>

                                            {{-- CPU — Label → Value → Bar --}}
                                            <div class="hidden sm:flex items-center gap-1"
                                                 :class="(!loading && online) ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">CPU</span>
                                                <span class="text-[10px] tabular-nums font-medium text-gray-700 dark:text-gray-300 w-7 inline-block text-right shrink-0"
                                                      x-text="parseFloat(status?.data?.cpu_usage || 0).toFixed(0) + '%'"></span>
                                                <div class="w-8 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 shrink-0">
                                                    <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-500" :style="'width:' + (status?.data?.cpu_usage || 0) + '%'"></div>
                                                </div>
                                            </div>

                                            {{-- Mem — Label → Value → Bar --}}
                                            <div class="hidden sm:flex items-center gap-1"
                                                 :class="(!loading && online) ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                                                <span class="text-[10px] text-gray-400 dark:text-gray-500 shrink-0">Mem</span>
                                                <span class="text-[10px] tabular-nums font-medium text-gray-700 dark:text-gray-300 w-7 inline-block text-right shrink-0"
                                                      x-text="parseFloat(status?.data?.mem_usage || 0).toFixed(0) + '%'"></span>
                                                <div class="w-8 bg-gray-200 dark:bg-gray-700 rounded-full h-1.5 shrink-0">
                                                    <div class="bg-purple-500 h-1.5 rounded-full transition-all duration-500" :style="'width:' + (status?.data?.mem_usage || 0) + '%'"></div>
                                                </div>
                                            </div>

                                            {{-- Traffic (xl+) — w-20 each accommodates "816.17 kbps" --}}
                                            <div class="hidden xl:flex items-center gap-1"
                                                 :class="(!loading && online) ? 'opacity-100' : 'opacity-0 pointer-events-none'">
                                                <div class="h-3 w-px bg-gray-200 dark:bg-gray-700 mx-0.5"></div>
                                                <span class="text-[10px] text-green-600 dark:text-green-400 tabular-nums inline-block w-20 text-right shrink-0">↑<span x-text="currentTraffic.in"></span></span>
                                                <span class="text-[10px] text-blue-600 dark:text-blue-400 tabular-nums inline-block w-20 text-right shrink-0">↓<span x-text="currentTraffic.out"></span></span>
                                                <div class="w-14 h-3.5 bg-gray-50 dark:bg-gray-900 rounded overflow-hidden border border-gray-100 dark:border-gray-700 shrink-0">
                                                    <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 30">
                                                        <polyline :points="getGraphPoints('in')" fill="none" stroke="#22c55e" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                                                        <polyline :points="getGraphPoints('out')" fill="none" stroke="#3b82f6" stroke-width="1.5" vector-effect="non-scaling-stroke" style="opacity:0.7" />
                                                    </svg>
                                                </div>
                                            </div>

                                            {{-- Manage button — always visible, no toggle --}}
                                            <a href="{{ route('firewall.dashboard', $firewall) }}"
                                               class="shrink-0 inline-flex items-center justify-center w-7 h-7 rounded-md border border-indigo-300 dark:border-indigo-700 text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors"
                                               title="Manage {{ $firewall->name }}">
                                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                        </div>

                                    </div>

                                    {{-- ═══ CARDS + COMPACT VIEW ═══ --}}
                                    <div x-show="$store.dashLayout.layout !== 'list'">

                                    {{-- Header Row: Name & Actions --}}
                                    <div :class="$store.dashLayout.layout === 'compact' ? 'flex flex-row justify-between items-center mb-1.5 gap-1' : 'flex flex-row justify-between items-center mb-4 gap-2'">
                                        <div class="flex flex-wrap items-center gap-2 min-w-0">
                                            <h4 :class="$store.dashLayout.layout === 'compact' ? 'font-semibold text-base whitespace-nowrap truncate' : 'font-semibold text-2xl whitespace-nowrap truncate'">
                                                <a href="{{ route('firewall.dashboard', $firewall) }}" class="hover:underline decoration-indigo-500 underline-offset-2 transition-colors hover:text-indigo-600 dark:hover:text-indigo-400">{{ $firewall->name }}</a>
                                            </h4>

                                            <template x-if="loading">
                                                <span
                                                    class="bg-gray-200 dark:bg-gray-700 w-14 h-5 rounded-full animate-pulse block"></span>
                                            </template>
                                            <template x-if="!loading && online">
                                                <span
                                                    class="bg-green-100 text-green-800 text-xs px-2 py-0.5 rounded-full dark:bg-green-900 dark:text-green-300">Online</span>
                                            </template>
                                            <template x-if="!loading && !online">
                                                <span
                                                    class="bg-red-100 text-red-800 text-xs px-2 py-0.5 rounded-full dark:bg-red-900 dark:text-red-300">Offline</span>
                                            </template>

                                        </div>

                                        <div class="shrink-0">
                                            <a href="{{ route('firewall.dashboard', $firewall) }}"
                                               x-show="$store.dashLayout.layout === 'cards'"
                                               class="inline-flex items-center px-3 py-1.5 bg-transparent border border-indigo-600 dark:border-indigo-400 rounded-md font-medium text-xs sm:text-sm text-indigo-600 dark:text-indigo-400 shadow-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/50 focus:outline-none transition ease-in-out duration-150">
                                               Manage
                                            </a>
                                            <a href="{{ route('firewall.dashboard', $firewall) }}"
                                               x-show="$store.dashLayout.layout === 'compact'"
                                               class="inline-flex items-center justify-center w-7 h-7 border border-indigo-300 dark:border-indigo-700 rounded-md text-indigo-600 dark:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/30 transition-colors"
                                               title="Manage">
                                               <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                                            </a>
                                        </div>
                                    </div>

                                    {{-- Meta Row: Company + URL & Uptime (cards only) --}}
                                    <div x-show="$store.dashLayout.layout === 'cards'"
                                         class="flex flex-wrap items-center gap-2 mb-4 text-xs font-medium">
                                        {{-- Company Name Chip --}}
                                        @if(auth()->user()->role === 'admin')
                                            <a href="{{ route('companies.show', $firewall->company) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-gray-700/50 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 transition-colors"
                                               title="{{ $firewall->company->name }}">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                <span style="display: block; max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $firewall->company->name }}</span>
                                            </a>
                                        @endif
                                        {{-- URL Chip --}}
                                        <a href="{{ $firewall->url }}" target="_blank" rel="noopener noreferrer"
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-gray-700/50 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 transition-colors group">
                                            <svg class="w-3.5 h-3.5 text-gray-500 group-hover:text-gray-700 dark:text-gray-500 dark:group-hover:text-gray-300"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                            <span
                                                class="truncate max-w-[150px] sm:max-w-xs font-mono">{{ $firewall->url }}</span>
                                            <svg class="w-3 h-3 opacity-0 group-hover:opacity-50 transition-opacity" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                            </svg>
                                        </a>

                                        {{-- Uptime Chip --}}
                                        <div
                                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md bg-blue-50 text-blue-700 dark:bg-blue-900/20 dark:text-blue-400 border border-blue-100 dark:border-blue-800/30">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                            </svg>
                                            <template x-if="loading">
                                                <div class="h-3 w-12 bg-blue-200 dark:bg-blue-800 rounded animate-pulse"></div>
                                            </template>
                                            <template x-if="!loading">
                                                <span>
                                                    <span class="font-mono" x-show="!online">Offline</span>
                                                    <span class="font-mono" x-show="online"
                                                        x-text="formatUptime(status?.data?.uptime || status?.data?.uptime_text || status?.data?.uptime_string)"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Compact: company + uptime + manage inline under name --}}
                                    <div x-show="$store.dashLayout.layout === 'compact'"
                                         class="flex items-center gap-2 text-xs mb-2">
                                        @if(auth()->user()->role === 'admin')
                                            {{-- Company Name Chip (matches card view style) --}}
                                            <a href="{{ route('companies.show', $firewall->company) }}"
                                               class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 hover:bg-gray-200 hover:text-gray-900 dark:bg-gray-700/50 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200 transition-colors"
                                               title="{{ $firewall->company->name }}">
                                                <svg class="w-3 h-3 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                                                <span style="display: block; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $firewall->company->name }}</span>
                                            </a>
                                            <span class="text-gray-300 dark:text-gray-600">&middot;</span>
                                        @endif
                                        {{-- External link icon (matches card view URL chip icon) --}}
                                        <a href="{{ $firewall->url }}" target="_blank" rel="noopener noreferrer"
                                           class="inline-flex items-center justify-center w-5 h-5 rounded text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
                                           title="{{ $firewall->url }}">
                                            <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />
                                            </svg>
                                        </a>
                                        {{-- Uptime formatted short --}}
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-400 font-mono font-medium"
                                              x-show="!loading && online"
                                              x-text="formatUptime(status?.data?.uptime || status?.data?.uptime_text || status?.data?.uptime_string)"></span>
                                        <span class="text-red-500 dark:text-red-400 font-medium" x-show="!loading && !online">Offline</span>
                                        <div class="h-3 w-12 bg-gray-200 dark:bg-gray-700 rounded animate-pulse" x-show="loading"></div>
                                    </div>{{-- end compact row --}}

                                    <div class="relative flex-1">
                                        {{-- Offline Overlay (Body Only) --}}
                                        <div x-cloak x-show="!online && !loading"
                                            class="absolute inset-0 z-10 flex flex-col items-center justify-center pointer-events-none"
                                            style="background-color: rgba(255,255,255,.80);">
                                            <div class="backdrop-blur-md p-6 rounded-lg shadow-xl text-center w-full max-w-sm mx-4 pointer-events-auto"
                                                style="background-color: rgba(255, 255, 255, 0.80);">
                                                <svg class="w-10 h-10 mx-auto text-red-500 mb-3" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                                    </path>
                                                </svg>
                                                <h3 class="text-lg font-semibold text-red-600 dark:text-red-400">Firewall is
                                                    unreachable</h3>
                                                <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">Connection timed out or
                                                    refused</p>
                                            </div>
                                        </div>

                                        {{-- Content Grid --}}
                                        <div>
                                            <template x-if="loading">
                                                <div class="animate-pulse space-y-4">
                                                    <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                                    <div class="space-y-2">
                                                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                                                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                                                        <div class="h-4 bg-gray-200 rounded w-full"></div>
                                                    </div>
                                                </div>
                                            </template>

                                            <template x-if="!loading">
                                                <div :class="$store.dashLayout.layout === 'cards' ? 'grid grid-cols-1 md:grid-cols-2 items-start' : 'block'" :style="$store.dashLayout.layout === 'cards' ? 'column-gap: 2rem; align-content: start;' : ''">
                                                    {{-- Left Column: System Details Table (cards only) --}}
                                                    <div class="mt-5 hidden sm:block" x-show="$store.dashLayout.layout === 'cards'">
                                                        <table
                                                            class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                                            <tbody>
                                                                <tr
                                                                    class="border-b dark:border-gray-700 align-top hidden sm:table-row">
                                                                    <th
                                                                        class="py-3 font-medium text-gray-900 dark:text-gray-300 w-1/4">
                                                                        Version</th>
                                                                    <td class="py-3"
                                                                        x-text="status?.data?.product_version || status?.data?.version || status?.data?.firmware_version || 'Unknown'">
                                                                    </td>
                                                                </tr>
                                                                <tr
                                                                    class="border-b dark:border-gray-700 align-top hidden sm:table-row">
                                                                    <th
                                                                        class="py-3 font-medium text-gray-900 dark:text-gray-300 w-1/4">
                                                                        REST API</th>
                                                                    <td class="py-3"
                                                                        x-text="status?.api_version || status?.data?.api_version || 'Unknown'">
                                                                    </td>
                                                                </tr>
                                                                <tr
                                                                    class="border-b dark:border-gray-700 align-top hidden sm:table-row">
                                                                    <th
                                                                        class="py-3 font-medium text-gray-900 dark:text-gray-300 w-1/4">
                                                                        Platform</th>
                                                                    <td class="py-3"
                                                                        x-text="status?.data?.platform || 'Unknown'"></td>
                                                                </tr>
                                                                <tr
                                                                    class="border-b dark:border-gray-700 align-top hidden sm:table-row">
                                                                    <th
                                                                        class="py-3 font-medium text-gray-900 dark:text-gray-300 w-1/4">
                                                                        BIOS</th>
                                                                    <td class="py-3">
                                                                        <template
                                                                            x-if="!status?.data?.bios_vendor && !status?.data?.bios_version && !status?.data?.bios_date">
                                                                            <span>Unknown</span>
                                                                        </template>
                                                                        <template
                                                                            x-if="status?.data?.bios_vendor || status?.data?.bios_version || status?.data?.bios_date">
                                                                            <div class="flex flex-col text-sm">
                                                                                <span x-show="status?.data?.bios_vendor"
                                                                                    x-text="status?.data?.bios_vendor"></span>
                                                                                <span x-show="status?.data?.bios_version"
                                                                                    x-text="status?.data?.bios_version"></span>
                                                                                <span x-show="status?.data?.bios_date"
                                                                                    x-text="status?.data?.bios_date"></span>
                                                                            </div>
                                                                        </template>
                                                                    </td>
                                                                </tr>
                                                                <tr class="align-top">
                                                                    <th
                                                                        class="py-3 font-medium text-gray-900 dark:text-gray-300 w-1/4">
                                                                        CPU System</th>
                                                                    <td class="py-3">
                                                                        <div class="flex flex-col text-sm">
                                                                            <span
                                                                                x-text="status?.data?.cpu_model || status?.data?.cpu_type || status?.data?.cpu || 'Unknown'"></span>
                                                                            <span class="text-gray-500"
                                                                                x-show="status?.data?.cpu_count"
                                                                                x-text="(status?.data?.cpu_count || '1') + ' CPUs'"></span>
                                                                            <span class="text-gray-400 mt-1"
                                                                                x-show="status?.data?.cpu_load_avg">
                                                                                Load: <span
                                                                                    x-text="(status?.data?.cpu_load_avg || []).join(', ')"></span>
                                                                            </span>
                                                                        </div>
                                                                    </td>
                                                                </tr>
                                                            </tbody>
                                                        </table>


                                                    </div>



                                                    {{-- Right Column: Live Metrics (Masked if Offline) --}}
                                                    <div>
                                                        <div
                                                            class="space-y-4 sm:space-y-4 grid grid-cols-2 gap-4 sm:flex sm:flex-col sm:gap-0 sm:grid-cols-1">
                                                            {{-- Gateways --}}
                                                            <template x-if="status?.data?.gateways && status.data.gateways.length > 0">
                                                                <div class="col-span-2 sm:col-span-1">
                                                                    {{-- Cards: full verbose list --}}
                                                                    <div x-show="$store.dashLayout.layout === 'cards'">
                                                                        <div class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Gateways</div>
                                                                        <div class="grid gap-1">
                                                                            <template x-for="gateway in status.data.gateways" :key="gateway.name">
                                                                                <div class="flex items-center justify-between gap-2 text-xs px-2.5 py-1.5 rounded-r bg-gray-50 dark:bg-slate-800/50 mb-1"
                                                                                    :class="{
                                                                                        'border-l-2 border-teal-500': gateway.status === 'online' || gateway.status === 'none',
                                                                                        'border-l-2 border-red-500': gateway.status === 'offline' || gateway.status === 'down',
                                                                                        'border-l-2 border-yellow-500': gateway.status && gateway.status !== 'online' && gateway.status !== 'none' && gateway.status !== 'offline' && gateway.status !== 'down'
                                                                                    }"
                                                                                    :title="gateway.monitorip || gateway.srcip">
                                                                                    <div class="flex flex-col min-w-0"><span
                                                                                            class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate"
                                                                                            x-text="gateway.descr || 'Unknown'"></span>
                                                                                        <div
                                                                                            class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400 font-mono truncate">
                                                                                            <span
                                                                                                x-show="gateway.name && gateway.name !== gateway.descr"
                                                                                                x-text="gateway.name"></span><span
                                                                                                x-show="gateway.name && gateway.name !== gateway.descr"
                                                                                                class="text-gray-300 dark:text-gray-600">|</span><span
                                                                                                x-text="gateway.monitorip || gateway.srcip || 'N/A'"></span>
                                                                                        </div>
                                                                                    </div>
                                                                                    <div class="flex items-center gap-1.5">
                                                                                        <div class="w-2 h-2 rounded-full"
                                                                                            :class="{
                                                                                                'bg-teal-500': gateway.status === 'online' || gateway.status === 'none',
                                                                                                'bg-red-500': gateway.status === 'offline' || gateway.status === 'down',
                                                                                                'bg-yellow-500': gateway.status && gateway.status !== 'online' && gateway.status !== 'none' && gateway.status !== 'offline' && gateway.status !== 'down'
                                                                                            }">
                                                                                        </div>
                                                                                        <span
                                                                                            class="capitalize text-[10px] font-medium text-gray-500 dark:text-gray-400"
                                                                                            x-text="gateway.status"></span>
                                                                                    </div>
                                                                                </div>
                                                                            </template>
                                                                        </div>
                                                                    </div>

                                                                    {{-- Compact: label + pill row below --}}
                                                                    <div class="mb-1" x-show="$store.dashLayout.layout === 'compact'">
                                                                        <div class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Gateways</div>
                                                                        <div class="flex items-center gap-1 flex-wrap">
                                                                            <template x-for="gateway in status.data.gateways" :key="gateway.name">
                                                                                <div class="relative group flex items-center gap-1 px-1.5 py-0.5 rounded-full text-[10px] font-medium cursor-default"
                                                                                     :class="{
                                                                                         'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400': gateway.status === 'online' || gateway.status === 'none',
                                                                                         'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400':     gateway.status === 'offline' || gateway.status === 'down',
                                                                                         'bg-yellow-100 text-yellow-700 dark:bg-yellow-900/30 dark:text-yellow-400': gateway.status && gateway.status !== 'online' && gateway.status !== 'none' && gateway.status !== 'offline' && gateway.status !== 'down'
                                                                                     }">
                                                                                    <div class="w-1.5 h-1.5 rounded-full"
                                                                                         :class="{
                                                                                             'bg-teal-500': gateway.status === 'online' || gateway.status === 'none',
                                                                                             'bg-red-500':   gateway.status === 'offline' || gateway.status === 'down',
                                                                                             'bg-yellow-500': gateway.status && gateway.status !== 'online' && gateway.status !== 'none' && gateway.status !== 'offline' && gateway.status !== 'down'
                                                                                         }"></div>
                                                                                    <span class="truncate max-w-[60px]" x-text="gateway.descr || gateway.name"></span>
                                                                                    {{-- Hover tooltip --}}
                                                                                    <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1.5 z-20 hidden group-hover:block pointer-events-none">
                                                                                        <div class="bg-gray-900 dark:bg-gray-700 text-white text-[10px] rounded px-2 py-1.5 whitespace-nowrap shadow-lg min-w-[120px]">
                                                                                            <div class="font-semibold mb-0.5" x-text="gateway.descr || gateway.name"></div>
                                                                                            <div class="text-gray-300 font-mono" x-show="gateway.name && gateway.name !== gateway.descr" x-text="gateway.name"></div>
                                                                                            <div class="text-gray-400 font-mono text-[9px]" x-text="gateway.monitorip || gateway.srcip || ''"></div>
                                                                                            <div class="mt-0.5 capitalize font-medium"
                                                                                                 :class="(gateway.status === 'online' || gateway.status === 'none') ? 'text-teal-400' : (gateway.status === 'offline' || gateway.status === 'down') ? 'text-red-400' : 'text-yellow-400'"
                                                                                                 x-text="gateway.status"></div>
                                                                                        </div>
                                                                                    </div>
                                                                                </div>
                                                                            </template>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>

                                                            <template x-if="!status?.data?.gateways || status.data.gateways.length === 0">
                                                                <div class="mb-3 col-span-2 sm:col-span-1" x-show="$store.dashLayout.layout === 'cards'">
                                                                    <div class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">Gateways</div>
                                                                    <div class="grid gap-1">
                                                                        <div class="flex items-center justify-between gap-2 text-xs px-2.5 py-1.5 rounded-r bg-gray-50 dark:bg-slate-800/50 mb-1 border-l-2 border-gray-300 dark:border-gray-600">
                                                                            <span class="text-sm font-mono font-medium text-gray-500 dark:text-gray-400">WAN</span>
                                                                            <div class="flex items-center gap-1.5">
                                                                                <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                                                                                <span class="capitalize text-[10px] font-medium text-gray-500 dark:text-gray-400">Unknown</span>
                                                                            </div>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </template>

                                                            {{-- CPU Usage --}}
                                                            <div>
                                                                <div class="flex justify-between mb-1">
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300">CPU
                                                                        Usage</span>
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                                                        x-text="parseFloat(parseFloat(status?.data?.cpu_usage || 0).toFixed(2)) + '%'"></span>
                                                                </div>
                                                                <div
                                                                    class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                                                    <div class="bg-blue-600 h-2.5 rounded-full transition-all duration-500"
                                                                        :style="'width: ' + (status?.data?.cpu_usage || 0) + '%'">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {{-- Memory Usage --}}
                                                            <div>
                                                                <div class="flex justify-between mb-1">
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300">Memory
                                                                        Usage</span>
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                                                        x-text="parseFloat(parseFloat(status?.data?.mem_usage || 0).toFixed(2)) + '%'"></span>
                                                                </div>
                                                                <div
                                                                    class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                                                    <div class="bg-purple-600 h-2.5 rounded-full transition-all duration-500"
                                                                        :style="'width: ' + (status?.data?.mem_usage || 0) + '%'">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {{-- Swap Usage --}}
                                                             <div>
                                                                <div class="flex justify-between mb-1">
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300">Swap
                                                                        Usage</span>
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                                                        x-text="(status?.data?.swap_usage != null) ? (parseFloat(parseFloat(status.data.swap_usage).toFixed(2)) + '%') : 'N/A'"></span>
                                                                </div>
                                                                <div
                                                                    class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                                                    <div class="bg-yellow-500 h-2.5 rounded-full transition-all duration-500"
                                                                        :style="'width: ' + (status?.data?.swap_usage || 0) + '%'">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            {{-- Disk Usage --}}
                                                             <div>
                                                                <div class="flex justify-between mb-1">
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300">Disk
                                                                        Usage (/)</span>
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300"
                                                                        x-text="parseFloat(parseFloat(status?.data?.disk_usage || 0).toFixed(2)) + '%'"></span>
                                                                </div>
                                                                <div
                                                                    class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                                                                    <div class="bg-yellow-600 h-2.5 rounded-full transition-all duration-500"
                                                                        :style="'width: ' + (status?.data?.disk_usage || 0) + '%'">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Temperature -->
                                                             <div class="col-span-2 sm:col-span-1">
                                                                <div class="flex justify-between mb-1 text-xs">
                                                                    <span
                                                                        class="font-medium text-gray-700 dark:text-gray-300">Temperature</span>
                                                                    <span class="text-gray-700 dark:text-gray-300"
                                                                        x-text="(status?.data?.temp_c && status.data.temp_c > 1) ? status.data.temp_c + '°C' : (status?.data?.temperature || 'N/A')"></span>
                                                                </div>
                                                                <div
                                                                    class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                                                    <div class="bg-orange-500 h-2 rounded-full transition-all duration-500"
                                                                        :style="'width: ' + ((status?.data?.temp_c && status.data.temp_c > 1) ? Math.min(status.data.temp_c, 100) : 0) + '%'">
                                                                    </div>
                                                                </div>
                                                             </div>
                                                        </div>
                                                    </div>
                                                    {{-- Traffic Monitor: spans both columns --}}
                                                    <div class="w-full mt-4" style="grid-column: 1 / -1;">
                                                        <div class="flex justify-between items-center mb-1">
                                                            <span class="text-xs font-medium text-gray-700 dark:text-gray-300 whitespace-nowrap">Traffic Monitor</span>
                                                            <div class="flex gap-4 text-xs">
                                                                <span class="text-green-600 dark:text-green-400 font-mono">In: <span x-text="currentTraffic.in"></span></span>
                                                                <span class="text-blue-600 dark:text-blue-400 font-mono">Out: <span x-text="currentTraffic.out"></span></span>
                                                            </div>
                                                        </div>
                                                        <div class="h-12 w-full bg-gray-50 dark:bg-gray-900 rounded overflow-hidden border border-gray-100 dark:border-gray-700">
                                                            <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 30">
                                                                <polyline :points="getGraphPoints('in')" fill="none" stroke="#22c55e" stroke-width="1.5" vector-effect="non-scaling-stroke" />
                                                                <polyline :points="getGraphPoints('out')" fill="none" stroke="#3b82f6" stroke-width="1.5" vector-effect="non-scaling-stroke" style="opacity: 0.7" />
                                                            </svg>
                                                        </div>
                                                    </div>


                                            </template>
                                        </div>
                                    </div>
                                    </div>{{-- end cards+compact wrapper --}}
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
        // Register layout store before Alpine initialises so all components can access it.
        // Fallback chain: localStorage → system default → 'cards'
        document.addEventListener('alpine:init', () => {
            Alpine.store('dashLayout', {
                layout: localStorage.getItem('admix_dashboard_layout')
                      || '{{ $settings["dashboard_default_layout"] ?? "cards" }}',
                set(v) {
                    this.layout = v;
                    localStorage.setItem('admix_dashboard_layout', v);
                }
            });
        });

        document.addEventListener('alpine:init', () => {
            // Centralized coordinator for batch firewall updates (eliminates waterfall)
            Alpine.data('dashboardCoordinator', (firewallIds) => ({
                firewallIds: firewallIds,
                loading: true,

                init() {
                    // Initial poll — waits for WS to connect first if still connecting,
                    // falls back after 3s if it hasn't connected yet.
                    const checkAndTrigger = () => {
                        if (window.wsConnected) {
                            // Already connected — trigger immediately
                            this.triggerBatchUpdate();
                            return;
                        }

                        // Not yet connected — wait up to 3s for connection
                        const onConnect = () => {
                            this.triggerBatchUpdate();
                            window.Echo?.connector?.pusher?.connection?.unbind('connected', onConnect);
                        };
                        window.Echo?.connector?.pusher?.connection?.bind('connected', onConnect);

                        // Fallback: if WS hasn't connected in 3s, poll anyway
                        setTimeout(() => {
                            if (this.loading) this.triggerBatchUpdate();
                        }, 3000);
                    };

                    checkAndTrigger();

                    // Dynamic Intervals from Settings
                    this.realtimeMs = {{ ($settings['realtime_interval'] ?? 10) * 1000 }};
                    this.fallbackMs = {{ ($settings['fallback_interval'] ?? 30) * 1000 }};
                    console.log('Dashboard Intervals Loaded:', {
                        realtime: this.realtimeMs,
                        fallback: this.fallbackMs,
                        rawSettings: @json($settings)
                    });
                    this.timer = null;

                    this.startIntervalManager();
                },

                startIntervalManager() {
                    if (this.timer) clearInterval(this.timer);

                    // Monitor WebSocket state to adjust interval speed.
                    // Uses window.wsConnected (set by echo.js) for reliable state detection.
                    const getDelay = () => {
                        // WS connected → fast interval (job dispatch + WS events handle delivery)
                        // WS down → slower interval (cards depend on poll results for status)
                        return window.wsConnected ? this.realtimeMs : this.fallbackMs;
                    };

                    let lastState = (window.Echo?.connector?.pusher?.connection?.state === 'connected');

                    const run = () => {
                        this.triggerBatchUpdate();

                        // Recursive timeout allows us to adjust speed based on connection state in real-time
                        const delay = getDelay();
                        this.timer = setTimeout(run, delay);

                        // If state changed, log it
                        let currentState = (delay === this.realtimeMs);
                        if (currentState !== lastState) {
                            console.log(`Switching dashboard refresh speed: ${currentState ? 'Real-time' : 'Fallback'} (${delay / 1000}s)`);
                            lastState = currentState;
                        }
                    };

                    // Initial kick-off (after the immediate checkAndTrigger)
                    this.timer = setTimeout(run, getDelay());
                },

                async triggerBatchUpdate() {
                    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                    // Always use the status-poll endpoint — no sync fallback.
                    // Jobs are dispatched + deduplicated server-side regardless of WS state.
                    const url = '{{ route("firewalls.status-poll") }}';

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({ ids: this.firewallIds })
                        });

                        if (response.status === 429) {
                            // Throttled by server — silently skip, next interval will retry
                            console.debug('Dashboard poll throttled (429). Skipping this cycle.');
                            this.loading = false;
                            return;
                        }

                        const data = await response.json();

                        if (data.results) {
                            Object.entries(data.results).forEach(([id, status]) => {
                                if (!status) return;

                                // Tag as 'poll_cache' — not authoritative, but carries freshness metadata.
                                // firewallCard.updateFromStatus() will apply Source Discrimination:
                                //   - poll_cache + offline + fresh  → keep skeleton (job in-flight)
                                //   - poll_cache + offline + stale  → show stale warning
                                //   - poll_cache + online           → show optimistically
                                // WebSocket events (no _source tag) are the definitive final state.
                                status._source = 'poll_cache';

                                window.dispatchEvent(new CustomEvent('firewall-updated-' + id, {
                                    detail: { status }
                                }));
                            });
                        }

                        this.loading = false;
                    } catch (err) {
                        console.error('Dashboard status poll failed:', err);
                    }
                }
            }));

            Alpine.data('dashboard', (initialFirewalls) => ({
                // Spread in the filterable mixin
                ...window.filterableMixin(initialFirewalls, 'device-updated'),

                // Dashboard-specific properties
                // Seed from PHP cache pre-load so the widget resolves immediately,
                // matching the same pattern as the individual firewall cards.
                offlineCount: {{ $offlineFirewalls }},
                showOnlineBadge: {{ $firewallsWithStatus->filter(fn($f) => $f->cached_status !== null)->count() > 0 ? 'true' : 'false' }},
                // customerFilter and statusFilter come from filterableMixin (initialized from URL params)

                init() {
                    // Initialize filterable functionality
                    this.initFilterable();

                    // Fallback: if no cache data was available at render time, reveal after 2.5s
                    // so the widget doesn't stay as a skeleton forever on a cold start.
                    if (!this.showOnlineBadge) {
                        setTimeout(() => this.showOnlineBadge = true, 2500);
                    }
                    window.addEventListener('device-offline', () => this.offlineCount++);
                    window.addEventListener('device-online', () => this.offlineCount = Math.max(0, this.offlineCount - 1));
                }
            }));
            Alpine.data('firewallCard', (initialStatus, staticInfo, checkUrl, firewallId, companyName) => ({
                // SOURCE DISCRIMINATION — initial state:
                // Start in skeleton if no cache OR if cached as offline (pending verification).
                // Cached-online can show immediately (optimistic display).
                loading: !initialStatus || (initialStatus && !initialStatus.online),

                online: initialStatus ? (initialStatus.online === true || initialStatus.online === 'true' || initialStatus.online === 1) : null,
                reportedOffline: false,
                status: initialStatus,
                error: null,
                staticInfo: staticInfo,
                checkUrl: checkUrl,
                firewallId: firewallId,
                companyName: companyName,

                // Verification state drives the badge label.
                // States: 'cached' | 'pending_verification' | 'verified_online' | 'verified_offline' | 'stale' | 'timeout_stale'
                verificationState: initialStatus
                    ? (initialStatus.online ? 'cached' : 'pending_verification')
                    : 'pending_verification',

                // Safety timeout handle — cleared when a live WebSocket event arrives
                _safetyTimer: null,

                // Manual refresh state (for a future Force Refresh button)
                refreshing: false,

                // Traffic Monitor (Rate Calculation)
                bandwidthHistory: new Array(20).fill({ in: 0, out: 0 }),
                currentTraffic: { in: '0 Bps', out: '0 Bps' },
                lastBytes: { in: 0, out: 0, time: 0 },

                // Load Monitor
                loadHistory: new Array(20).fill(0),

                // Formats verbose pfSense uptime strings to a compact "Xd Xh Xm" representation.
                // Handles: seconds (number), "X Days Y Hours Z Minutes W Seconds" (string), or passthrough.
                formatUptime(raw) {
                    if (!raw) return '—';

                    // If numeric — raw seconds
                    const asNum = Number(raw);
                    if (!isNaN(asNum) && asNum >= 0) {
                        const d = Math.floor(asNum / 86400);
                        const h = Math.floor((asNum % 86400) / 3600);
                        const m = Math.floor((asNum % 3600) / 60);
                        if (d > 0) return `${d}d ${h}h`;
                        if (h > 0) return `${h}h ${m}m`;
                        return `${m}m`;
                    }

                    // If verbose string: "7 Days 07 Hours 33 Minutes 08 Seconds"
                    const re = /(\d+)\s*day[s]?\s*[,:]?\s*(\d+)\s*hour[s]?\s*[,:]?\s*(\d+)\s*minute[s]?/i;
                    const m2 = String(raw).match(re);
                    if (m2) {
                        const [, d, h, m] = m2.map(Number);
                        if (d > 0) return `${d}d ${h}h`;
                        if (h > 0) return `${h}h ${m}m`;
                        return `${m}m`;
                    }

                    // Fallback: return raw but truncated
                    return String(raw).split(' ').slice(0, 4).join(' ');
                },

                matches(query) {
                    if (!query) return true;
                    const q = query.toLowerCase();
                    if (this.staticInfo.includes(q)) return true;
                    const statusText = this.online ? 'online' : 'offline';
                    if (statusText.includes(q)) return true;
                    if (this.status) {
                        if (this.status.data && this.status.data.version && this.status.data.version.toLowerCase().includes(q)) return true;
                        if (this.status.api_version && this.status.api_version.toLowerCase().includes(q)) return true;
                    }
                    return false;
                },

                matchesFilters(statusFilter, customerFilter) {
                    // Status Filter
                    if (statusFilter !== 'all') {
                        // Use raw online status, don't block if loading
                        const isOnline = this.online;
                        if (statusFilter === 'online' && !isOnline) return false;
                        if (statusFilter === 'offline' && isOnline) return false;
                    }

                    // Customer Filter
                    if (customerFilter !== 'all') {
                        if (this.companyName !== customerFilter) return false;
                    }

                    return true;
                },

                getInterfaces() {
                    if (this.status && this.status.interfaces) {
                        return this.status.interfaces;
                    }
                    // Placeholder for offline devices without cached interfaces
                    return {
                        'wan': { descr: 'WAN', status: 'offline' },
                        'lan': { descr: 'LAN', status: 'offline' }
                    };
                },

                updateBandwidthFromInterfaces(interfaces) {
                    let wan = null;
                    const wanKey = Object.keys(interfaces).find(key => key.toLowerCase() === 'wan');
                    if (wanKey) {
                        wan = interfaces[wanKey];
                    } else {
                        const firstKey = Object.keys(interfaces)[0];
                        if (firstKey) wan = interfaces[firstKey];
                    }
                    if (!wan) return;

                    const now = new Date().getTime();
                    const bytesIn = parseFloat(wan.inbytes || 0);
                    const bytesOut = parseFloat(wan.outbytes || 0);
                    let inRate = 0;
                    let outRate = 0;

                    // Prefer server-computed rates (available once the backend cache is warm).
                    // These are injected as in_rate_bps / out_rate_bps on each interface object.
                    if (wan.in_rate_bps !== undefined && wan.out_rate_bps !== undefined) {
                        inRate  = parseFloat(wan.in_rate_bps  || 0);
                        outRate = parseFloat(wan.out_rate_bps || 0);
                    } else if (this.lastBytes.time > 0) {
                        // Fall back to client-side delta (requires two readings)
                        const timeDiff = (now - this.lastBytes.time) / 1000;
                        if (timeDiff > 0) {
                            if (bytesIn >= this.lastBytes.in) {
                                inRate = ((bytesIn - this.lastBytes.in) * 8) / timeDiff;
                            }
                            if (bytesOut >= this.lastBytes.out) {
                                outRate = ((bytesOut - this.lastBytes.out) * 8) / timeDiff;
                            }
                        }
                    }

                    this.lastBytes = { in: bytesIn, out: bytesOut, time: now };
                    this.bandwidthHistory.shift();
                    this.bandwidthHistory.push({ in: inRate, out: outRate });
                    this.currentTraffic = {
                        in: this.formatBytes(inRate, true),
                        out: this.formatBytes(outRate, true)
                    };
                },

                formatBytes(size, isBits = false) {
                    if (!+size) return isBits ? '0 bps' : '0 B';
                    const k = 1024;
                    const decimals = 2;
                    const dm = decimals < 0 ? 0 : decimals;
                    const sizes = isBits ? ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps'] : ['B', 'KB', 'MB', 'GB', 'TB'];
                    const i = Math.floor(Math.log(size) / Math.log(k));
                    return `${parseFloat((size / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
                },

                getGraphPoints(type) {
                    const max = Math.max(...this.bandwidthHistory.map(d => Math.max(d.in, d.out))) || 100;
                    const height = 30;
                    const width = 100;
                    const step = width / (this.bandwidthHistory.length - 1);
                    return this.bandwidthHistory.map((d, i) => {
                        const val = d[type];
                        const y = height - ((val / max) * height);
                        return `${i * step},${y}`;
                    }).join(' ');
                },

                getLoadGraphPoints() {
                    const max = Math.max(...this.loadHistory, 1); // Min scale 1.0
                    const height = 20;
                    const width = 100;
                    const step = width / (this.loadHistory.length - 1);
                    return this.loadHistory.map((val, i) => {
                        const y = height - ((val / max) * height);
                        return `${i * step},${y}`;
                    }).join(' ');
                },

                init() {
                    // Apply cached status with source tag — no pfSense API call.
                    if (this.status) {
                        this.status._source = 'cache';
                        this.updateFromStatus(this.status);

                        // PHP already seeds offlineCount from this same cached data.
                        // Pre-mark as reported so the card doesn't fire device-offline
                        // again during init, which would double the count.
                        if (!this.online) {
                            this.reportedOffline = true;
                        }
                    }

                    // If starting in skeleton state (no cache, or cached-offline),
                    // arm the 30s safety timeout to prevent eternal skeletons.
                    if (this.loading) {
                        this._startSafetyTimeout();
                    }

                    // Listen for coordinator poll results (_source: 'poll_cache')
                    window.addEventListener('firewall-updated-' + this.firewallId, (e) => {
                        this.updateFromStatus(e.detail.status);
                    });

                    // WebSocket delivers the authoritative live state (_source not set = live)
                    this.setupWebSocket();
                },

                _startSafetyTimeout() {
                    if (this._safetyTimer) return;
                    this._safetyTimer = setTimeout(() => {
                        if (this.loading) {
                            console.warn(`[Firewall ${this.firewallId}] Safety timeout (30s) — forcing stale state`);
                            this.updateFromStatus({
                                ...(this.status || {}),
                                online: false,
                                _source: 'timeout_stale',
                                freshness: 'stale',
                                error: `Verification timed out. Last check: ${this.status?.updated_at ?? 'unknown'}`,
                            });
                        }
                    }, 30000);
                },

                _clearSafetyTimeout() {
                    if (this._safetyTimer) {
                        clearTimeout(this._safetyTimer);
                        this._safetyTimer = null;
                    }
                },

                updateFromStatus(status) {
                    if (!status) return;

                    // Normalize to the cache wrapper shape { online, api_version, data: {...} }
                    // which the template reads as status.data.product_version etc.
                    //
                    // Two possible incoming shapes:
                    // 1. Cache wrapper (from init or fetchStatus): { online, api_version, data: { product_version, ... } }
                    // 2. WS broadcast (flat): { online, api_version, product_version, gateways, cpu_usage, ... }
                    //
                    // Detect flat broadcast: has top-level pfSense fields but no .data object.
                    if (!status.data || typeof status.data !== 'object') {
                        // Flat broadcast — promote all non-wrapper fields into .data
                        const wrapperKeys = new Set(['online','error','api_version','updated_at','_source','firewall_id','timestamp']);
                        const data = {};
                        Object.keys(status).forEach(k => { if (!wrapperKeys.has(k)) data[k] = status[k]; });
                        status = Object.assign({}, status, { data });
                    }

                    // Legacy: flatten status.data.data if it exists (defensive)
                    if (status.data && status.data.data && typeof status.data.data === 'object') {
                        Object.assign(status.data, status.data.data);
                        delete status.data.data;
                    }

                    // Merge into existing status to preserve last-known values for null fields
                    if (this.status && this.status.data) {
                        status.data = Object.assign({}, this.status.data, status.data);
                    }

                    this.status = status;

                    // Explicitly check for boolean/string truthiness
                    const prevOnline = this.online;
                    this.online = (status.online === true || status.online === 'true' || status.online === 1);

                    // SOURCE DISCRIMINATION — loading state and verificationState
                    const source = status._source || 'live';
                    const isPollCache = (source === 'cache' || source === 'poll_cache');
                    const isLive = !isPollCache; // WebSocket events have no _source tag
                    const isStale = (status.freshness === 'stale' || source === 'timeout_stale');

                    if (isLive) {
                        // Live WebSocket event — definitive, always resolve skeleton
                        this.loading = false;
                        this.verificationState = this.online ? 'verified_online' : 'verified_offline';
                        this._clearSafetyTimeout();
                    } else if (isPollCache && this.online) {
                        // Cached/polled online — show optimistically
                        this.loading = false;
                        this.verificationState = isStale ? 'stale' : 'cached';
                    } else if (isPollCache && !this.online && isStale) {
                        // Cached offline AND stale — reveal with stale warning rather than skeleton forever
                        this.loading = false;
                        this.verificationState = 'stale';
                    } else {
                        // Cached offline, still fresh — keep skeleton (job in-flight, WS event expected)
                        this.loading = true;
                        this.verificationState = 'pending_verification';
                    }

                    // Safety timeout guard: if source is timeout_stale, always exit skeleton
                    if (source === 'timeout_stale') {
                        this.loading = false;
                        this.verificationState = 'timeout_stale';
                    }

                    // Status Change Logging
                    if (prevOnline !== this.online) {
                        console.log(`[Firewall ${this.firewallId}] Status changed: ${prevOnline ? 'Online' : 'Offline'} -> ${this.online ? 'Online' : 'Offline'}`, status);
                    }

                    this.error = status.error || null;

                    // Update reported state & Dispatch events
                    if (this.online && this.reportedOffline) {
                        this.reportedOffline = false;
                        this.$dispatch('device-online', { id: this.firewallId });
                    }
                    if (!this.online && !this.reportedOffline) {
                        this.reportedOffline = true;
                        this.$dispatch('device-offline', { id: this.firewallId });
                    }

                    // Update bandwidth if interface data is present
                    if (this.online && this.status.data && this.status.data.interfaces) {
                        this.updateBandwidthFromInterfaces(this.status.data.interfaces);
                    }

                    // Update Load History
                    if (this.status && this.status.data && this.status.data.cpu_load_avg && this.status.data.cpu_load_avg.length > 0) {
                        const oneMinLoad = parseFloat(this.status.data.cpu_load_avg[0]) || 0;
                        this.loadHistory.shift();
                        this.loadHistory.push(oneMinLoad);
                    }

                    // Notify global listeners
                    this.$dispatch('device-updated', { id: this.firewallId, online: this.online });
                },

                async fetchStatus() {
                    try {
                        const controller = new AbortController();
                        const timeoutId = setTimeout(() => controller.abort(), 5000); // 5s Timeout

                        let response = await fetch(this.checkUrl + '?t=' + new Date().getTime(), {
                            signal: controller.signal
                        });
                        clearTimeout(timeoutId);

                        let data = await response.json();

                        // Use standardized update logic
                        this.updateFromStatus(data.status);
                    } catch (e) {
                        console.error(e);
                        this.loading = false;
                        this.online = false;
                        if (!this.reportedOffline) {
                            this.reportedOffline = true;
                            this.$dispatch('device-offline', { id: this.firewallId });
                            this.$dispatch('device-updated', { id: this.firewallId, online: this.online }); // Update widgets even if offline
                        }
                        this.error = 'Unreachable';
                    }
                },

                setupWebSocket() {
                    if (this.wsListener) return; // Prevent double binding

                    if (window.Echo) {
                        this.wsListener = window.Echo.private('firewall.' + this.firewallId)
                            .listen('.firewall.status.update', (e) => {
                                // console.log('Real-time update received via WebSocket for device ' + this.firewallId, e);
                                if (e && e.status) {
                                    this.updateFromStatus(e.status);
                                }
                            });
                    } else {
                        // Safety retry if Echo isn't initialized yet
                        setTimeout(() => this.setupWebSocket(), 1000);
                    }
                }
            }));

            // System Health Widget Component
            document.addEventListener('alpine:init', () => {
                Alpine.data('systemHealth', () => ({
                    loading: true,
                    healthStatus: 'No Data',
                    healthColor: 'gray',
                    avgCpu: 0,
                    avgMemory: 0,
                    firewallStats: {},

                    init() {
                        // Subscribe to WebSocket for all firewalls
                        @foreach($firewallsWithStatus as $fw)
                            this.subscribeToFirewall({{ $fw->id }});
                        @endforeach

                        // Set timeout to hide loading after 5 seconds even if no data
                        setTimeout(() => {
                            if (this.loading) {
                                this.loading = false;
                            }
                        }, 5000);
                    },

                    subscribeToFirewall(firewallId) {
                        if (window.Echo) {
                            window.Echo.private('firewall.' + firewallId)
                                .listen('.firewall.status.update', (e) => {
                                    if (e.status && e.status.data) {
                                        this.loading = false;

                                        // Handle both flat and nested structure
                                        const metrics = (e.status.data.data && typeof e.status.data.data === 'object') ? e.status.data.data : e.status.data;

                                        this.updateFirewallStatus({
                                            id: firewallId,
                                            cpu: metrics.cpu_usage || 0,
                                            memory: metrics.mem_usage || 0
                                        });
                                    }
                                });
                        } else {
                            setTimeout(() => this.subscribeToFirewall(firewallId), 500);
                        }
                    },

                    updateFirewallStatus(data) {
                        this.firewallStats[data.id] = {
                            cpu: parseFloat(data.cpu) || 0,
                            memory: parseFloat(data.memory) || 0
                        };
                        this.calculateHealth();
                    },

                    calculateHealth() {
                        const stats = Object.values(this.firewallStats);

                        if (stats.length === 0) {
                            this.healthStatus = 'No Data';
                            this.healthColor = 'gray';
                            return;
                        }

                        const totalCpu = stats.reduce((sum, s) => sum + s.cpu, 0);
                        const totalMem = stats.reduce((sum, s) => sum + s.memory, 0);

                        this.avgCpu = (totalCpu / stats.length).toFixed(1);
                        this.avgMemory = (totalMem / stats.length).toFixed(1);

                        const maxUsage = Math.max(this.avgCpu, this.avgMemory);

                        if (maxUsage < 50) {
                            this.healthStatus = 'Excellent';
                            this.healthColor = 'green';
                        } else if (maxUsage < 70) {
                            this.healthStatus = 'Good';
                            this.healthColor = 'blue';
                        } else if (maxUsage < 85) {
                            this.healthStatus = 'Fair';
                            this.healthColor = 'yellow';
                        } else {
                            this.healthStatus = 'Critical';
                            this.healthColor = 'red';
                        }
                    }
                }));
            });
        });

        // System Health Widget Update Logic (Polls Alpine.js firewall cards)
        (function () {
            function updateSystemHealth() {
                // Find all firewall card elements
                const cards = Array.from(document.querySelectorAll('[x-data^="firewallCard"]'));

                if (cards.length === 0) {
                    // No cards found yet, keep skeleton visible
                    return false;
                }

                // Check if any card is still loading
                let anyLoading = false;
                cards.forEach(el => {
                    try {
                        if (Alpine.$data(el).loading) {
                            anyLoading = true;
                        }
                    } catch (e) {
                        anyLoading = true; // Safety
                    }
                });

                if (anyLoading) {
                    // Wait for all to load
                    return false;
                }

                const stats = [];
                cards.forEach(el => {
                    try {
                        const data = Alpine.$data(el);
                        // Only count devices that are actually online (and have data)
                        if (data && data.online && data.status && data.status.data) {
                            const cpu = parseFloat(data.status.data.cpu_usage) || 0;
                            const memory = parseFloat(data.status.data.mem_usage) || 0;
                            const disk = parseFloat(data.status.data.disk_usage) || 0;
                            let temp = null;

                            if (data.status.data.temp_c && parseFloat(data.status.data.temp_c) > 1.0) {
                                temp = parseFloat(data.status.data.temp_c);
                            }

                            stats.push({ cpu, memory, disk, temp });
                        }
                    } catch (e) {
                        // Alpine not ready yet
                    }
                });

                const totalDevices = cards.length;
                const onlineDevices = stats.length;

                let networkPerfScore = 0;

                if (onlineDevices > 0) {
                    // Calculate performance score for each online device
                    const deviceScores = stats.map(s => {
                        let sum = s.cpu + s.memory + s.disk;
                        let count = 3;

                        if (s.temp !== null) {
                            sum += s.temp; // Assuming 0-100C maps roughly to 0-100% "usage" for scoring
                            count++;
                        }

                        const avgUsage = sum / count;
                        return Math.max(0, 100 - avgUsage); // Score is 100 - usage
                    });

                    // Average the device scores
                    const totalDeviceScore = deviceScores.reduce((a, b) => a + b, 0);
                    networkPerfScore = totalDeviceScore / onlineDevices;
                } else {
                    // If NO devices are online, performance is effectively 0 (or undefined), 
                    // but since Availability will be 0, the final weighted sum effectively handles it.
                    networkPerfScore = 0;
                }

                // Calculate Availability Score (percentage of devices online)
                const availScore = totalDevices > 0 ? (onlineDevices / totalDevices) * 100 : 0;

                // Final Health Score: 80% Availability, 20% Performance
                // If all devices offline: avail=0, perf=0 -> Score = 0
                const healthScore = Math.round((availScore * 0.8) + (networkPerfScore * 0.2));

                let healthStatus, iconColor, bgColor, iconBg, heartColorStyle;

                if (healthScore >= 75) {
                    healthStatus = 'Excellent';
                    iconColor = 'text-green-600 dark:text-green-300';
                    bgColor = 'bg-green-100 dark:bg-green-900';
                    iconBg = '';
                    heartColorStyle = '#16a34a'; // green-600
                } else if (healthScore >= 55) {
                    healthStatus = 'Fair';
                    iconColor = 'text-yellow-600 dark:text-yellow-300';
                    bgColor = 'bg-yellow-100 dark:bg-yellow-900';
                    iconBg = '';
                    heartColorStyle = '#ca8a04'; // yellow-600
                } else {
                    healthStatus = 'Critical';
                    iconColor = 'text-red-600 dark:text-red-300';
                    bgColor = 'bg-red-100 dark:bg-red-900';
                    iconBg = 'animate-pulse';
                    heartColorStyle = '#dc2626'; // red-600
                }

                // System Health Widget Logic


                // Update DOM
                const skeleton = document.getElementById('health-skeleton');
                const data = document.getElementById('health-data');

                if (skeleton && data) {
                    skeleton.style.display = 'none';
                    data.style.display = 'block';
                }

                // Update heart icon
                const iconContainer = document.getElementById('health-icon');
                if (iconContainer) {
                    iconContainer.className = `p-3 rounded-full ${bgColor} ${iconBg} mr-4 transition-all duration-300`;

                    const heartIcon = iconContainer.querySelector('svg');
                    if (heartIcon) {
                        // Use inline style to force color
                        heartIcon.style.color = heartColorStyle;
                        heartIcon.className = 'h-8 w-8 transition-colors duration-300';
                    }
                }

                // Update score, status, and systems count
                const scoreEl = document.getElementById('health-score');
                const systemsEl = document.getElementById('health-systems');
                // const statusEl = document.getElementById('health-status'); // Removed in favor of systems count or cleaner look

                if (scoreEl) scoreEl.textContent = healthScore + '%';
                // if (systemsEl) systemsEl.textContent = `${onlineDevices}/${totalDevices} Systems Online`;

                return true;
            }

            // Poll every 2 seconds until we get data
            const pollInterval = setInterval(() => {
                if (updateSystemHealth()) {
                    // Successfully updated, switch to slower polling
                    clearInterval(pollInterval);
                    // Keep updating every 5 seconds
                    setInterval(updateSystemHealth, 5000);
                }
            }, 2000);

            // Event-based updates (Syncs widgets with firewall cards)
            let debounceTimer;
            const debouncedUpdate = () => {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    updateSystemHealth();
                }, 100); // 100ms debounce
            };

            window.addEventListener('device-updated', debouncedUpdate);
            window.addEventListener('device-online', debouncedUpdate);
            window.addEventListener('device-offline', debouncedUpdate);

            // Timeout to show "No Data" after 10 seconds if still no data
            setTimeout(() => {
                const skeleton = document.getElementById('health-skeleton');
                if (skeleton && skeleton.style.display !== 'none') {
                    skeleton.style.display = 'none';
                    document.getElementById('health-data').style.display = 'block';
                    document.getElementById('health-status').textContent = 'No Data';
                }
            }, 10000);
        })();
    </script>
</x-app-layout>