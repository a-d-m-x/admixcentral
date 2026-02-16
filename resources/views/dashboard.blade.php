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
                                New User
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
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center">
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
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center"
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
                                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center">
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
                                class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center">
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
                            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center">
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
                                <span class="text-xs text-gray-500 font-normal block xl:hidden"
                                    x-text="'Showing ' + filteredCount() + ' of ' + items.length + ' firewalls'"></span>
                            </div>

                            <div
                                class="flex flex-col sm:grid sm:grid-cols-2 xl:flex xl:flex-row items-stretch sm:items-center gap-2 w-full xl:w-auto">
                                <span
                                    class="text-xs text-gray-500 font-normal self-start xl:self-center whitespace-nowrap xl:mr-2 hidden xl:inline"
                                    x-text="'Showing ' + filteredCount() + ' of ' + items.length + ' firewalls'"></span>
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
                                            class="flex items-center justify-between w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 px-3 py-2 text-sm focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition ease-in-out duration-150">
                                            <span x-text="customerFilter === 'all' ? 'All Customers' : customerFilter"
                                                :class="{'text-gray-500': customerFilter === 'all', 'text-gray-900 dark:text-gray-200': customerFilter !== 'all'}"></span>
                                            <svg class="h-4 w-4 ml-2 text-gray-500 transform transition-transform duration-200"
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
                        <div class="space-y-6">
                            @foreach($firewallsWithStatus as $firewall)
                                <div x-show="matches(search) && matchesFilters(statusFilter, customerFilter)"
                                    x-data="firewallCard(
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        {{ json_encode($firewall->cached_status) }}, 
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        '{{ strtolower($firewall->name . ' ' . $firewall->company->name . ' ' . $firewall->url . ' ' . $firewall->hostname) }}',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        '{{ route('firewall.check-status', $firewall) }}',
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        {{ $firewall->id }},
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                        '{{ $firewall->company->name }}'
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    )"
                                    class="relative border border-gray-100 dark:border-gray-700 rounded-2xl p-3 sm:p-4 bg-white dark:bg-gray-800 shadow-md hover:shadow-xl transition-shadow duration-200">

                                    {{-- Overlay Moved to Body --}}

                                    {{-- Header Row: Name & Actions --}}
                                    <div class="flex flex-row justify-between items-center mb-4 gap-2">
                                        <div class="flex flex-wrap items-center gap-2 sm:gap-3 min-w-0">
                                            <h4 class="font-semibold text-2xl whitespace-nowrap truncate">{{ $firewall->name }}
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

                                            @if(auth()->user()->role === 'admin')
                                                <span
                                                    class="text-xs text-gray-500 border-l pl-3 dark:border-gray-600 whitespace-nowrap hidden sm:inline">{{ $firewall->company->name }}</span>
                                            @endif
                                        </div>

                                        <div class="shrink-0">
                                            <a href="{{ route('firewall.dashboard', $firewall) }}"
                                                class="inline-flex items-center px-3 py-1.5 bg-transparent border border-indigo-600 dark:border-indigo-400 rounded-md font-medium text-xs sm:text-sm text-indigo-600 dark:text-indigo-400 shadow-sm hover:bg-indigo-50 dark:hover:bg-indigo-900/50 focus:outline-none transition ease-in-out duration-150">
                                                Manage
                                            </a>
                                        </div>
                                    </div>

                                    {{-- Meta Row: URL & Uptime --}}
                                    <div class="flex flex-wrap items-center gap-2 mb-4 text-xs font-medium">
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
                                                        x-text="status?.data?.uptime || status?.data?.uptime_text || status?.data?.uptime_string || 'Updating...'"></span>
                                                </span>
                                            </template>
                                        </div>
                                    </div>

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

                                            <!-- ... (rest of template) .. -->
                                            <!-- Note: I am truncating context to keep edit safe -->

                                            <!-- I need to jump to the JS section separately or include enough context? -->
                                            <!-- The JS is way down. I should use MultiReplace? Or separate Replace calls? -->
                                            <!-- I will do separate calls. -->

                                            <template x-if="!loading">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-start">
                                                    {{-- Left Column: System Details Table --}}
                                                    <div class="mt-5 hidden sm:block">
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
                                                            <template
                                                                x-if="status?.data?.gateways && status.data.gateways.length > 0">
                                                                <div class="mb-3 col-span-2 sm:col-span-1">
                                                                    <div
                                                                        class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                                        Gateways</div>
                                                                    <div class="grid gap-1">
                                                                        <template x-for="gateway in status.data.gateways"
                                                                            :key="gateway.name">
                                                                            <div class="flex items-center justify-between gap-2 text-xs px-2.5 py-1.5 rounded-r bg-gray-50 dark:bg-slate-800/50 mb-1"
                                                                                :class="{
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                'border-l-2 border-green-500': gateway.status === 'online' || gateway.status === 'none',
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
                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                    'bg-green-500': gateway.status === 'online' || gateway.status === 'none',
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
                                                            </template>

                                                            <template
                                                                x-if="!status?.data?.gateways || status.data.gateways.length === 0">
                                                                <div class="mb-3 col-span-2 sm:col-span-1">
                                                                    <div
                                                                        class="mb-1 text-xs font-medium text-gray-700 dark:text-gray-300">
                                                                        Gateways</div>
                                                                    <div class="grid gap-1">
                                                                        <div
                                                                            class="flex items-center justify-between gap-2 text-xs px-2.5 py-1.5 rounded-r bg-gray-50 dark:bg-slate-800/50 mb-1 border-l-2 border-gray-300 dark:border-gray-600">
                                                                            <span
                                                                                class="text-sm font-mono font-medium text-gray-500 dark:text-gray-400">WAN</span>
                                                                            <div class="flex items-center gap-1.5">
                                                                                <div class="w-2 h-2 rounded-full bg-gray-400">
                                                                                </div>
                                                                                <span
                                                                                    class="capitalize text-[10px] font-medium text-gray-500 dark:text-gray-400">Unknown</span>
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
                                                                        x-text="(status?.data?.cpu_usage || 0) + '%'"></span>
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
                                                                        x-text="(status?.data?.mem_usage || 0) + '%'"></span>
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
                                                                        x-text="(status?.data?.swap_usage != null) ? (status.data.swap_usage + '%') : 'N/A'"></span>
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
                                                                        x-text="(status?.data?.disk_usage || 0) + '%'"></span>
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

                                                            <!-- Interface Status Indicators -->





                                                            <!-- Compact Traffic Monitor -->
                                                            <div class="col-span-2 sm:col-span-1">
                                                                <div class="flex justify-between items-center mb-1">
                                                                    <span
                                                                        class="text-xs font-medium text-gray-700 dark:text-gray-300">Traffic
                                                                        Monitor</span>
                                                                    <div class="flex gap-4 text-xs">
                                                                        <span
                                                                            class="text-green-600 dark:text-green-400 font-mono">In:
                                                                            <span x-text="currentTraffic.in"></span></span>
                                                                        <span
                                                                            class="text-blue-600 dark:text-blue-400 font-mono">Out:
                                                                            <span x-text="currentTraffic.out"></span></span>
                                                                    </div>
                                                                </div>
                                                                <div
                                                                    class="h-8 w-full bg-gray-50 dark:bg-gray-900 rounded overflow-hidden relative border border-gray-100 dark:border-gray-700 flex">
                                                                    <svg class="w-full h-full" preserveAspectRatio="none"
                                                                        viewBox="0 0 100 30">
                                                                        <!-- Inbound -->
                                                                        <polyline :points="getGraphPoints('in')" fill="none"
                                                                            stroke="#22c55e" stroke-width="1.5"
                                                                            vector-effect="non-scaling-stroke" />
                                                                        <!-- Outbound -->
                                                                        <polyline :points="getGraphPoints('out')" fill="none"
                                                                            stroke="#3b82f6" stroke-width="1.5"
                                                                            vector-effect="non-scaling-stroke"
                                                                            style="opacity: 0.7" />
                                                                    </svg>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>


                                            </template>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <script>
        document.addEventListener('alpine:init', () => {
            // Centralized coordinator for batch firewall updates (eliminates waterfall)
            Alpine.data('dashboardCoordinator', (firewallIds) => ({
                firewallIds: firewallIds,
                loading: true,

                init() {
                    // Smart WebSocket connection check (same pattern as /firewalls page)
                    const checkAndTrigger = () => {
                        if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
                            const state = window.Echo.connector.pusher.connection.state;

                            // If connected, trigger batch update
                            if (state === 'connected') {
                                this.triggerBatchUpdate();
                                return;
                            }

                            // If connecting, wait for connection
                            if (state === 'connecting' || state === 'initialized') {
                                const onConnect = () => {
                                    this.triggerBatchUpdate();
                                    window.Echo.connector.pusher.connection.unbind('connected', onConnect);
                                };
                                window.Echo.connector.pusher.connection.bind('connected', onConnect);
                                // Fallback timeout
                                setTimeout(() => {
                                    if (this.loading) this.triggerBatchUpdate();
                                }, 3000);
                                return;
                            }
                        }

                        // If disconnected or no Echo, use sync fallback
                        this.triggerBatchUpdate();
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

                    // Monitor WebSocket state to adjust interval speed
                    const getDelay = () => {
                        // Default to fast (Real-time) unless explicitly disconnected
                        // This ensures 'connecting' or 'initialized' states don't slow us down
                        const state = window.Echo?.connector?.pusher?.connection?.state;
                        const isExplicitlyDisconnected = (state === 'disconnected' || state === 'failed' || state === 'unavailable');

                        if (isExplicitlyDisconnected) {
                            return this.fallbackMs;
                        }
                        return this.realtimeMs;
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
                    let url = '{{ route("firewalls.refresh-all") }}';
                    let isWsConnected = false;

                    // Only force sync fallback if explicitly disconnected
                    const state = window.Echo?.connector?.pusher?.connection?.state;
                    let isExplicitlyDisconnected = (state === 'disconnected' || state === 'failed' || state === 'unavailable');

                    // Also treat missing Echo as disconnected
                    if (!window.Echo) isExplicitlyDisconnected = true;

                    if (isExplicitlyDisconnected) {
                        // console.warn('WS Disconnected/Failed. Forcing sync update.');
                        url += '?sync=true';
                    }

                    try {
                        const response = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token
                            },
                            body: JSON.stringify({ ids: this.firewallIds })
                        });

                        const data = await response.json();

                        // If we got results (Sync fallback), dispatch them immediately
                        if (data.results) {
                            Object.entries(data.results).forEach(([id, status]) => {
                                // Mark as cached so the card knows not to show "Offline" overlay yet
                                if (status) status._source = 'cache';

                                window.dispatchEvent(new CustomEvent('firewall-updated-' + id, {
                                    detail: { status: status }
                                }));
                            });
                        }

                        this.loading = false;
                    } catch (err) {
                        console.error('Dashboard batch update failed:', err);
                    }
                }
            }));

            Alpine.data('dashboard', (initialFirewalls) => ({
                // Spread in the filterable mixin
                ...window.filterableMixin(initialFirewalls, 'device-updated'),

                // Dashboard-specific properties
                offlineCount: 0,
                showOnlineBadge: false,
                customerFilter: 'all',
                statusFilter: 'all',

                init() {
                    // Initialize filterable functionality
                    this.initFilterable();

                    // Dashboard-specific init
                    setTimeout(() => this.showOnlineBadge = true, 2500);
                    window.addEventListener('device-offline', () => this.offlineCount++);
                    window.addEventListener('device-online', () => this.offlineCount = Math.max(0, this.offlineCount - 1));
                }
            }));
            Alpine.data('firewallCard', (initialStatus, staticInfo, checkUrl, firewallId, companyName) => ({
                // Treat as loading if:
                // 1. No initial status (Cache Miss)
                // 2. Initial status says Offline (Cache Hit but needs verification)
                // This prevents "Offline" flash/overlay until real-time update confirms it.
                loading: !initialStatus || (initialStatus && (initialStatus.online !== true && initialStatus.online !== 'true' && initialStatus.online !== 1)),

                online: initialStatus ? (initialStatus.online !== false) : null,
                reportedOffline: false,
                status: initialStatus,
                error: null,
                staticInfo: staticInfo,
                checkUrl: checkUrl,
                firewallId: firewallId,
                companyName: companyName,

                // Traffic Monitor (Rate Calculation)
                bandwidthHistory: new Array(20).fill({ in: 0, out: 0 }),
                currentTraffic: { in: '0 Bps', out: '0 Bps' },
                lastBytes: { in: 0, out: 0, time: 0 },

                // Load Monitor
                loadHistory: new Array(20).fill(0),

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
                        const isOnline = !this.loading && this.online;
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

                    if (this.lastBytes.time > 0) {
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
                    // Standardize initial status
                    if (this.status) {
                        this.status._source = 'cache';
                    }

                    // Only apply if we aren't waiting for verification (valid Online status)
                    // OR if we have data to show.
                    if (this.status && !this.loading) {
                        this.updateFromStatus(this.status);
                    } else if (this.loading && this.status) {
                        // Mark as cache so updateFromStatus keeps it loading if offline
                        this.status._source = 'cache';
                        this.updateFromStatus(this.status);

                        // If we are forced to load due to Offline cache, set a safety timeout
                        // If 15 seconds pass and we are STILL loading, revert to Verified Offline.
                        setTimeout(() => {
                            if (this.loading) {
                                console.warn(`[Firewall ${this.firewallId}] Verification timed out (15s). Forcing Offline.`);
                                // Force a non-cached offline status to show the overlay
                                this.updateFromStatus({
                                    online: false,
                                    error: 'Connection timed out',
                                    data: null,
                                    _source: 'timeout_forced'
                                });
                            }
                        }, 15000);
                    }

                    // Listen for updates from coordinator (Sync fallback)
                    window.addEventListener('firewall-updated-' + this.firewallId, (e) => {
                        this.updateFromStatus(e.detail.status);
                    });

                    // WebSocket handle real-time updates
                    this.setupWebSocket();
                },

                updateFromStatus(status) {
                    if (!status) return;

                    // Standardize Structure: If there's a nested data key (v2 API) and we're not flattened yet, merge it up
                    if (status.data && status.data.data && typeof status.data.data === 'object') {
                        Object.assign(status.data, status.data.data);
                    }

                    this.status = status;

                    // Explicitly check for boolean/string truthiness
                    const prevOnline = this.online;
                    this.online = (status.online === true || status.online === 'true' || status.online === 1);

                    // Determine if this is a "Cached" update
                    const isCached = (status._source === 'cache');

                    // LOADING STATE LOGIC
                    if (isCached && !this.online) {
                        this.loading = true; // Keep skeleton if cached offline
                    } else {
                        this.loading = false; // Optimistic or Verified -> Show content
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
                        let response = await fetch(this.checkUrl + '?t=' + new Date().getTime());
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
                        console.log('System Health widget initialized');

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
                            console.log('Health widget subscribing to firewall ' + firewallId);
                            window.Echo.private('firewall.' + firewallId)
                                .listen('.firewall.status.update', (e) => {
                                    console.log('Health widget received update for firewall ' + firewallId, e);
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
                        console.log('Updating firewall status:', data);
                        this.firewallStats[data.id] = {
                            cpu: parseFloat(data.cpu) || 0,
                            memory: parseFloat(data.memory) || 0
                        };
                        this.calculateHealth();
                    },

                    calculateHealth() {
                        const stats = Object.values(this.firewallStats);
                        console.log('Calculating health from stats:', stats);

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

                        console.log('Health calc:', { avgCpu: this.avgCpu, avgMem: this.avgMemory, maxUsage });

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

                        console.log('Final health:', this.healthStatus, this.healthColor);
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