<x-app-layout>
    <x-slot name="header">
        <x-firewall-header :title="$firewall->name . ' - ' . __('Dashboard')" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="firewallDashboard()">

        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            {{-- System Status --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-semibold">System Information</h3>

                        <div class="flex items-center gap-3">


                            <template x-if="systemLoading">
                                <div class="animate-pulse bg-gray-200 h-6 w-20 rounded"></div>
                            </template>
                            <template x-if="!systemLoading && systemConnected">
                                <span
                                    class="bg-green-100 text-green-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-green-900 dark:text-green-300">Online</span>
                            </template>
                            <template x-if="!systemLoading && !systemConnected">
                                <span
                                    class="bg-red-100 text-red-800 text-xs font-medium px-2.5 py-0.5 rounded dark:bg-red-900 dark:text-red-300">Offline</span>
                            </template>

                            @if(!auth()->user()->isReadOnly())
                            <x-dropdown align="right" width="48">
                                <x-slot name="trigger">
                                    <button
                                        class="inline-flex items-center p-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                        <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 5v.01M12 12v.01M12 19v.01M12 6a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2zm0 7a1 1 0 110-2 1 1 0 010 2z" />
                                        </svg>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <x-dropdown-link :href="route('firewalls.edit', $firewall)">
                                        {{ __('Edit Settings') }}
                                    </x-dropdown-link>

                                    <!-- Authentication -->
                                    <form method="POST" action="{{ route('firewalls.destroy', $firewall) }}"
                                        id="delete-firewall-form">
                                        @csrf
                                        @method('DELETE')

                                        <x-dropdown-link href="#"
                                            @click.prevent="$dispatch('open-modal', 'delete-firewall-modal')"
                                            class="text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                            {{ __('Delete Firewall') }}
                                        </x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                            @endif
                        </div>
                    </div>

                    {{-- Skeleton Loading --}}
                    <template x-if="systemLoading">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 animate-pulse">
                            <div class="space-y-4">
                                <div class="h-4 bg-gray-200 rounded w-3/4"></div>
                                <div class="h-4 bg-gray-200 rounded w-1/2"></div>
                                <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                            </div>
                            <div class="space-y-4">
                                <div class="h-4 bg-gray-200 rounded w-full"></div>
                                <div class="h-4 bg-gray-200 rounded w-full"></div>
                            </div>
                        </div>
                    </template>

                    <!-- Real Content -->
                    <div x-show="!systemLoading && systemConnected" style="display: none;">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                            <!-- Left Column: System Details Table -->
                            <div>
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <tbody>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">
                                                Version</th>
                                            <td class="py-2 text-sm" x-text="systemStatus?.data?.version"></td>
                                        </tr>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">REST
                                                API</th>
                                            <td class="py-2 text-sm" x-text="systemStatus?.api_version || 'Unknown'">
                                            </td>
                                        </tr>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">
                                                Platform</th>
                                            <td class="py-2 text-sm" x-text="systemStatus?.data?.platform"></td>
                                        </tr>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">BIOS
                                            </th>
                                            <td class="py-2 text-sm">
                                                @if($firewall->isOpnSense())
                                                    <span class="text-gray-400 dark:text-gray-500">Not available</span>
                                                @else
                                                <div class="flex flex-col">
                                                    <span x-text="systemStatus?.data?.bios_vendor"></span>
                                                    <span x-text="systemStatus?.data?.bios_version"></span>
                                                    <span x-text="systemStatus?.data?.bios_date"></span>
                                                </div>
                                                @endif
                                            </td>
                                        </tr>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">CPU
                                                System
                                            </th>
                                            <td class="py-2 text-sm">
                                                <div class="flex flex-col">
                                                    <span x-text="systemStatus?.data?.cpu_model"></span>
                                                    <span class="text-gray-500"
                                                        x-text="(systemStatus?.data?.cpu_count || '1') + ' CPUs'"></span>
                                                    <span class="text-gray-400 mt-1"
                                                        x-show="systemStatus?.data?.cpu_load_avg">
                                                        Load: <span
                                                            x-text="(systemStatus?.data?.cpu_load_avg || []).join(', ')"></span>
                                                    </span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">Uptime
                                            </th>
                                            <td class="py-2 text-sm"
                                                x-text="systemStatus?.data?.uptime || systemStatus?.data?.uptime_text || systemStatus?.data?.uptime_string || 'Updating...'">
                                            </td>
                                        </tr>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">
                                                Packages</th>
                                            <td class="py-2 text-sm"
                                                x-text="(systemStatus?.data?.installed_packages_count !== undefined) ? systemStatus.data.installed_packages_count : 'N/A'">
                                            </td>
                                        </tr>
                                        <tr class="border-b dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">DNS
                                                Servers
                                            </th>
                                            <td class="py-2 text-sm">
                                                <div class="flex flex-col">
                                                    <template x-for="dns in (systemStatus?.data?.dns_servers || [])">
                                                        <span x-text="dns"></span>
                                                    </template>
                                                    <span x-show="!systemStatus?.data?.dns_servers?.length">-</span>
                                                </div>
                                            </td>
                                        </tr>
                                        <tr class="dark:border-gray-700">
                                            <th class="py-2 font-medium text-gray-900 dark:text-gray-300 text-sm">Last
                                                Modified
                                            </th>
                                            <td class="py-2 text-sm">
                                                @if($firewall->isOpnSense())
                                                    <span class="text-gray-400 dark:text-gray-500">Not available</span>
                                                @else
                                                <div class="flex flex-col">
                                                    <span
                                                        x-text="systemStatus?.data?.last_config_change || 'Unknown'"></span>
                                                    <template x-if="systemStatus?.data?.last_config_change_ts">
                                                        <span
                                                            x-text="new Date(systemStatus.data.last_config_change_ts * 1000).toLocaleString('sv-SE', { timeZoneName: 'short' })"
                                                            class="text-gray-400 dark:text-gray-400"></span>
                                                    </template>
                                                </div>
                                                @endif
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Right Column: Mini Graphs & Status -->
                            <div class="space-y-3">
                                <!-- Gateways Status -->
                                <template x-if="gateways && gateways.length > 0">
                                    <div class="mb-3">
                                        <div class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Gateways
                                        </div>
                                        <div class="grid gap-1">
                                             <template x-for="gateway in gateways" :key="gateway.name || gateway.id">
                                                <div class="flex items-center justify-between gap-2 text-xs px-2.5 py-1.5 rounded-r bg-gray-50 dark:bg-slate-800/50 mb-1 border-l-2"
                                                    :class="{
                                                        'border-green-500': (gateway.status || '').toLowerCase() === 'online' || (gateway.status || '').toLowerCase() === 'none',
                                                        'border-red-500': (gateway.status || '').toLowerCase() === 'offline' || (gateway.status || '').toLowerCase() === 'down',
                                                        'border-yellow-500': (gateway.status || '').toLowerCase() !== 'online' && (gateway.status || '').toLowerCase() !== 'none' && (gateway.status || '').toLowerCase() !== 'offline' && (gateway.status || '').toLowerCase() !== 'down'
                                                    }" :title="gateway.address || gateway.monitorip || gateway.srcip || gateway.gateway">
                                                    <div class="flex flex-col min-w-0">
                                                        <span
                                                            class="text-xs font-semibold text-gray-700 dark:text-gray-200 truncate"
                                                            x-text="gateway.descr || gateway.name || 'Unknown'"></span>
                                                        <div
                                                            class="flex items-center gap-1 text-[10px] text-gray-500 dark:text-gray-400 font-mono truncate">
                                                            <span
                                                                x-show="gateway.name && gateway.name !== gateway.descr"
                                                                x-text="gateway.name"></span>
                                                            <span
                                                                x-show="gateway.name && gateway.name !== gateway.descr"
                                                                class="text-gray-300 dark:text-gray-600">|</span>
                                                            <span
                                                                x-text="gateway.address || gateway.monitorip || gateway.srcip || gateway.gateway || 'N/A'"></span>
                                                        </div>
                                                    </div>
                                                    <div class="flex items-center gap-1.5">
                                                        <div class="w-2 h-2 rounded-full" :class="{
                                                            'bg-green-500': (gateway.status || '').toLowerCase() === 'online' || (gateway.status || '').toLowerCase() === 'none',
                                                            'bg-red-500': (gateway.status || '').toLowerCase() === 'offline' || (gateway.status || '').toLowerCase() === 'down',
                                                            'bg-yellow-500': (gateway.status || '').toLowerCase() !== 'online' && (gateway.status || '').toLowerCase() !== 'none' && (gateway.status || '').toLowerCase() !== 'offline' && (gateway.status || '').toLowerCase() !== 'down'
                                                        }"></div>
                                                        <span
                                                            class="capitalize text-[10px] font-medium text-gray-500 dark:text-gray-400"
                                                            x-text="gateway.status"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <template x-if="!gateways || gateways.length === 0">
                                    <div class="mb-3">
                                        <div class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">Gateways
                                        </div>
                                        <div class="grid gap-1">
                                            <div
                                                class="flex items-center justify-between gap-2 text-xs px-2.5 py-1.5 rounded-r bg-gray-50 dark:bg-slate-800/50 mb-1 border-l-2 border-gray-300 dark:border-gray-600">
                                                <span
                                                    class="text-sm font-mono font-medium text-gray-500 dark:text-gray-400">WAN</span>
                                                <div class="flex items-center gap-1.5">
                                                    <div class="w-2 h-2 rounded-full bg-gray-400"></div>
                                                    <span
                                                        class="capitalize text-[10px] font-medium text-gray-500 dark:text-gray-400">Unknown</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>

                                <!-- CPU -->
                                <div>
                                    <div class="flex justify-between mb-1 text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">CPU Usage</span>
                                        <span class="text-gray-700 dark:text-gray-300"
                                            x-text="parseFloat(parseFloat(systemStatus?.data?.cpu_usage || 0).toFixed(2)) + '%'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-500"
                                            :style="'width: ' + (systemStatus?.data?.cpu_usage || 0) + '%'"></div>
                                    </div>
                                </div>

                                <!-- Memory -->
                                <div>
                                    <div class="flex justify-between mb-1 text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Memory Usage</span>
                                        <span class="text-gray-700 dark:text-gray-300"
                                            x-text="parseFloat(parseFloat(systemStatus?.data?.mem_usage || 0).toFixed(2)) + '%'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                        <div class="bg-purple-600 h-2 rounded-full transition-all duration-500"
                                            :style="'width: ' + (systemStatus?.data?.mem_usage || 0) + '%'"></div>
                                    </div>
                                </div>

                                <!-- Swap -->
                                <div>
                                    <div class="flex justify-between mb-1 text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Swap Usage</span>
                                        <span class="text-gray-700 dark:text-gray-300"
                                            x-text="(systemStatus?.data?.swap_usage != null) ? (parseFloat(parseFloat(systemStatus.data.swap_usage).toFixed(2)) + '%') : 'N/A'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                        <div class="bg-red-500 h-2 rounded-full transition-all duration-500"
                                            :style="'width: ' + (systemStatus?.data?.swap_usage || 0) + '%'"></div>
                                    </div>
                                </div>

                                <!-- Disk -->
                                <div>
                                    <div class="flex justify-between mb-1 text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Disk Usage (/)</span>
                                        <span class="text-gray-700 dark:text-gray-300"
                                            x-text="parseFloat(parseFloat(systemStatus?.data?.disk_usage || 0).toFixed(2)) + '%'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                        <div class="bg-yellow-500 h-2 rounded-full transition-all duration-500"
                                            :style="'width: ' + (systemStatus?.data?.disk_usage || 0) + '%'"></div>
                                    </div>
                                </div>

                                <!-- Temperature -->
                                <div>
                                    <div class="flex justify-between mb-1 text-sm">
                                        <span class="font-medium text-gray-700 dark:text-gray-300">Temperature</span>
                                        <span class="text-gray-700 dark:text-gray-300"
                                            x-text="(systemStatus?.data?.temp_c && systemStatus.data.temp_c > 1) ? systemStatus.data.temp_c + '°C' : 'N/A'"></span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2 dark:bg-gray-700">
                                        <div class="bg-orange-500 h-2 rounded-full transition-all duration-500"
                                            :style="'width: ' + ((systemStatus?.data?.temp_c && systemStatus.data.temp_c > 1) ? Math.min(systemStatus.data.temp_c, 100) : 0) + '%'">
                                        </div>
                                    </div>
                                </div>

                                <!-- Interface Status Indicators -->
                                <template
                                    x-if="(systemStatus && systemStatus.interfaces) || (interfaces && interfaces.length > 0)">
                                    <div>
                                        <div class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Interfaces</div>
                                        <div class="flex flex-wrap gap-2">
                                            <template x-for="(iface, name) in (systemStatus?.interfaces || interfaces)"
                                                :key="name">
                                                <div
                                                    class="flex items-center gap-1.5 bg-gray-50 dark:bg-gray-700 px-2 py-1 rounded text-xs border border-gray-100 dark:border-gray-600">
                                                    <div class="w-2 h-2 rounded-full" :class="{
                                                                        'bg-green-500': iface.status === 'up' || iface.status === 'associated',
                                                                        'bg-red-500': iface.status === 'down' || iface.status === 'no carrier',
                                                                        'bg-yellow-500': !['up', 'down', 'associated', 'no carrier'].includes(iface.status)
                                                                    }"></div>
                                                    <span class="font-mono uppercase text-gray-600 dark:text-gray-300"
                                                        x-text="iface.descr || name"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                <!-- Compact Traffic Monitor -->
                                <div class="mt-4 text-sm">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Traffic
                                            Monitor</span>
                                        <div class="flex gap-4 text-xs">
                                            <span class="text-green-600 dark:text-green-400 font-mono">In: <span
                                                    x-text="currentTraffic.in"></span></span>
                                            <span class="text-blue-600 dark:text-blue-400 font-mono">Out: <span
                                                    x-text="currentTraffic.out"></span></span>
                                        </div>
                                    </div>
                                    <!-- Compact Graph (30px height) -->
                                    <div
                                        class="h-8 w-full bg-gray-50 dark:bg-gray-900 rounded overflow-hidden relative border border-gray-100 dark:border-gray-700">
                                        <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 40">
                                            <polyline :points="getGraphPoints('in')" fill="none" stroke="#22c55e"
                                                stroke-width="2" vector-effect="non-scaling-stroke" />
                                            <polyline :points="getGraphPoints('out')" fill="none" stroke="#3b82f6"
                                                stroke-width="2" vector-effect="non-scaling-stroke" />
                                        </svg>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>




                    <div x-show="!systemLoading && !systemConnected" style="display: none;">
                        <div class="bg-red-50 dark:bg-red-900 p-4 rounded-lg">
                            <p class="text-red-600 dark:text-red-400 font-semibold">Unable to connect to firewall.</p>
                            <p class="text-sm text-red-500"
                                x-text="systemError || 'Check connectivity and credentials.'"></p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Quick Nav --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-xl font-semibold mb-4">Management</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="{{ route('firewall.interfaces.index', $firewall) }}"
                            class="bg-blue-500 hover:bg-blue-700 text-white p-4 rounded-lg text-center transition flex flex-col items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M4.5 12H3m18 0h-1.5m-15 3.75H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <div>
                                <p class="font-bold">Interfaces</p>
                                <p class="text-sm" x-text="interfacesLoading ? '...' : (interfaces?.length || 0)"></p>
                            </div>
                        </a>
                        <a href="{{ route('firewall.rules.index', $firewall) }}"
                            class="bg-purple-500 hover:bg-purple-700 text-white p-4 rounded-lg text-center transition flex flex-col items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z" />
                            </svg>
                            <div>
                                <p class="font-bold">Firewall Rules</p>
                                <p class="text-sm" x-text="rulesLoading ? '...' : (rules?.length || 0)"></p>
                            </div>
                        </a>
                        <a href="{{ route('status.services', $firewall) }}"
                            class="bg-green-500 hover:bg-green-700 text-white p-4 rounded-lg text-center transition flex flex-col items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003.827c-.293.24-.438.613-.431.992a6.759 6.759 0 010 .255c-.007.378.138.75.43.99l1.005.828c.424.35.534.954.26 1.43l-1.298 2.247a1.125 1.125 0 01-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.57 6.57 0 01-.22.128c-.331.183-.581.495-.644.869l-.213 1.28c-.09.543-.56.941-1.11.941h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 01-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 01-1.369-.49l-1.297-2.247a1.125 1.125 0 01.26-1.431l1.004-.827c.292-.24.437-.613.43-.992a6.932 6.932 0 010-.255c.007-.378-.138-.75-.43-.99l-1.004-.828a1.125 1.125 0 01-.26-1.43l1.297-2.247a1.125 1.125 0 011.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.087.22-.128.332-.183.582-.495.644-.869l.214-1.281z" />
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <div>
                                <p class="font-bold">Services</p>
                                <p class="text-sm">Status</p>
                            </div>
                        </a>
                        <a href="{{ route('vpn.ipsec', $firewall) }}"
                            class="bg-orange-500 hover:bg-orange-700 text-white p-4 rounded-lg text-center transition flex flex-col items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-8 h-8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z" />
                            </svg>
                            <div>
                                <p class="font-bold">VPN</p>
                                <p class="text-sm">Configure</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Layout Flex: Traffic (1/3) | Tables (2/3) --}}
            <style>
                @media (min-width: 1024px) {
                    #db-col-traffic {
                        flex: 1 0 0% !important;
                        width: auto !important;
                    }

                    #db-col-summary {
                        flex: 2 0 0% !important;
                        width: auto !important;
                    }
                }
            </style>
            <div class="flex flex-col lg:flex-row gap-6">
                {{-- Left Column: Traffic Graphs --}}
                <div id="db-col-traffic" class="w-full space-y-6">


                    {{-- Interface Traffic --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-xl font-semibold mb-4">Interface Traffic</h3>

                            <template
                                x-if="(!systemStatus || !systemStatus.interfaces) && (!interfaces || interfaces.length === 0)">
                                <div class="grid grid-cols-1 gap-6 animate-pulse">
                                    <!-- Skeleton Card 1 -->
                                    <div class="border border-gray-100 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2.5 h-2.5 rounded-full bg-gray-200 dark:bg-gray-700">
                                                </div>
                                                <div class="h-4 w-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                            </div>
                                            <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        </div>
                                        <div class="flex justify-between text-xs mb-2">
                                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        </div>
                                        <div class="h-10 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
                                    </div>
                                    <!-- Skeleton Card 2 -->
                                    <div class="border border-gray-100 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2.5 h-2.5 rounded-full bg-gray-200 dark:bg-gray-700">
                                                </div>
                                                <div class="h-4 w-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                            </div>
                                            <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        </div>
                                        <div class="flex justify-between text-xs mb-2">
                                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        </div>
                                        <div class="h-10 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
                                    </div>
                                    <!-- Skeleton Card 3 -->
                                    <div
                                        class="border border-gray-100 dark:border-gray-700 rounded-lg p-4 hidden lg:block">
                                        <div class="flex justify-between items-center mb-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2.5 h-2.5 rounded-full bg-gray-200 dark:bg-gray-700">
                                                </div>
                                                <div class="h-4 w-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                            </div>
                                            <div class="h-3 w-20 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        </div>
                                        <div class="flex justify-between text-xs mb-2">
                                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                            <div class="h-3 w-16 bg-gray-200 dark:bg-gray-700 rounded"></div>
                                        </div>
                                        <div class="h-10 w-full bg-gray-200 dark:bg-gray-700 rounded"></div>
                                    </div>
                                </div>
                            </template>

                            <div class="grid grid-cols-1 gap-6"
                                x-show="(systemStatus && systemStatus.interfaces) || (interfaces && interfaces.length > 0)">
                                <template x-for="(iface, name) in (systemStatus?.interfaces || interfaces)" :key="name">
                                    <div class="border border-gray-100 dark:border-gray-700 rounded-lg p-4">
                                        <div class="flex justify-between items-center mb-3">
                                            <div class="flex items-center gap-2">
                                                <div class="w-2.5 h-2.5 rounded-full" :class="{
                                                'bg-green-500': iface.status === 'up' || iface.status === 'associated',
                                                'bg-red-500': iface.status === 'down' || iface.status === 'no carrier',
                                                'bg-yellow-500': !['up', 'down', 'associated', 'no carrier'].includes(iface.status)
                                            }"></div>
                                                <span class="font-bold text-sm"
                                                    x-text="getInterfaceLabel(iface, name)"></span>
                                            </div>
                                            <span class="text-sm font-mono text-gray-500" x-text="iface.ip"></span>
                                        </div>

                                        <div class="flex justify-between text-sm mb-2">
                                            <span class="text-green-600 dark:text-green-400">In: <span
                                                    x-text="interfaceRates[name]?.in || '0 bps'"></span></span>
                                            <span class="text-blue-600 dark:text-blue-400">Out: <span
                                                    x-text="interfaceRates[name]?.out || '0 bps'"></span></span>
                                        </div>

                                        <div
                                            class="h-10 w-full bg-gray-50 dark:bg-gray-900 rounded overflow-hidden relative border border-gray-100 dark:border-gray-700">
                                            <svg class="w-full h-full" preserveAspectRatio="none" viewBox="0 0 100 40">
                                                <polyline :points="getGraphPoints('in', name)" fill="none"
                                                    stroke="#22c55e" stroke-width="2"
                                                    vector-effect="non-scaling-stroke" />
                                                <polyline :points="getGraphPoints('out', name)" fill="none"
                                                    stroke="#3b82f6" stroke-width="2"
                                                    vector-effect="non-scaling-stroke" />
                                            </svg>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>

                </div>

                {{-- Right Column: Summaries --}}
                <div id="db-col-summary" class="w-full space-y-6">

                    {{-- Location Map --}}
                    @if($firewall->latitude && $firewall->longitude)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 text-gray-900 dark:text-gray-100">
                                <h3 class="text-xl font-semibold mb-4">Location</h3>
                                <div class="text-sm text-gray-500 dark:text-gray-400 mb-4 flex items-start">
                                    <svg class="w-4 h-4 mr-1 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                        </path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    <a href="https://www.google.com/maps/search/?api=1&query={{ urlencode($firewall->address) }}"
                                        target="_blank"
                                        class="text-indigo-600 dark:text-indigo-400 hover:underline transition-colors">
                                        {{ $firewall->address }}
                                    </a>
                                </div>
                                <div id="firewall-map"
                                    class="w-full h-48 rounded-lg border border-gray-200 dark:border-gray-600 z-0"></div>

                                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
                                    integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
                                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                                    integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
                                <script>
                                    document.addEventListener('DOMContentLoaded', function () {
                                        // Initialize Leaflet map centered on firewall coordinates
                                        var map = L.map('firewall-map', { scrollWheelZoom: false }).setView([{{ $firewall->latitude }}, {{ $firewall->longitude }}], 13);

                                        // Add OpenStreetMap tile layer
                                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                                        }).addTo(map);

                                        // Add marker with popup showing firewall name and address
                                        L.marker([{{ $firewall->latitude }}, {{ $firewall->longitude }}]).addTo(map)
                                            .bindPopup("<b>{{ $firewall->name }}</b><br>{{ Str::limit($firewall->address, 30) }}").openPopup();
                                    });
                                </script>
                            </div>
                        </div>
                    @endif

                    {{-- Gateways Summary --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-xl font-semibold mb-4">Gateways</h3>

                            <template x-if="gatewaysLoading">
                                <div class="space-y-4 animate-pulse">
                                    <div class="h-8 bg-gray-200 rounded w-full"></div>
                                </div>
                            </template>

                            <div class="overflow-x-auto" x-show="!gatewaysLoading && gateways?.length > 0">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Description</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Gateway</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Loss</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium text-center">Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="gateway in gateways" :key="gateway.id || gateway.name">
                                            <tr
                                                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-gray-white"
                                                    x-text="gateway.descr || gateway.name || 'N/A'"></td>
                                                <td class="px-3 py-2 font-mono text-xs"
                                                    x-text="gateway.address || gateway.monitorip || gateway.srcip || gateway.gateway || 'N/A'"></td>
                                                <td class="px-3 py-2 text-xs" x-text="((gateway.loss || '0') + '').replace('%', '') + '%'"></td>
                                                <td class="px-3 py-2 text-center">
                                                    <div class="h-2.5 w-2.5 rounded-full mx-auto" :class="{
                                                        'bg-green-500': (gateway.status || '').toLowerCase() === 'online' || (gateway.status || '').toLowerCase() === 'none',
                                                        'bg-red-500': (gateway.status || '').toLowerCase() === 'offline' || (gateway.status || '').toLowerCase() === 'down',
                                                        'bg-yellow-500': (gateway.status || '').toLowerCase() !== 'online' && (gateway.status || '').toLowerCase() !== 'none' && (gateway.status || '').toLowerCase() !== 'offline' && (gateway.status || '').toLowerCase() !== 'down'
                                                    }" :title="gateway.status"></div>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- Interfaces Summary --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-xl font-semibold mb-4">Interfaces</h3>

                            <template x-if="interfacesLoading">
                                <div class="space-y-4 animate-pulse">
                                    <div class="h-8 bg-gray-200 rounded w-full"></div>
                                    <div class="h-8 bg-gray-200 rounded w-full"></div>
                                    <div class="h-8 bg-gray-200 rounded w-full"></div>
                                </div>
                            </template>

                            <div class="overflow-x-auto" x-show="!interfacesLoading && interfaces?.length > 0">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">ID</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Name</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium text-center">Status
                                            </th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">IP Address</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Speed</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <template x-for="iface in interfaces" :key="iface.id || iface.name">
                                            <tr
                                                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <td class="px-3 py-2 font-mono" x-text="iface.id ?? iface.name"></td>
                                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-white"
                                                    x-text="iface.descr || iface.name"></td>
                                                <td class="px-3 py-2 text-center">
                                                    <div class="h-2.5 w-2.5 rounded-full mx-auto" :class="{
                                                        'bg-green-500': iface.status === 'up' || iface.status === 'associated',
                                                        'bg-red-500': iface.status === 'down' || iface.status === 'no carrier',
                                                        'bg-yellow-500': !['up', 'down', 'associated', 'no carrier'].includes(iface.status)
                                                    }" :title="iface.status"></div>
                                                </td>
                                                <td class="px-3 py-2 font-mono text-xs" x-text="iface.ipaddr || iface.ip || 'N/A'">
                                                </td>
                                                <td class="px-3 py-2 text-xs truncate max-w-[150px]"
                                                    :title="iface.media" x-text="iface.media || 'Unknown'"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    {{-- VPN Status Card --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <div class="mb-4">
                                <h3 class="text-xl font-semibold">VPN Tunnels</h3>
                            </div>

                            {{-- Loading skeleton --}}
                            <template x-if="vpnLoading">
                                <div class="space-y-3 animate-pulse">
                                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4"></div>
                                    <div class="h-2 bg-gray-200 dark:bg-gray-700 rounded-full w-full"></div>
                                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-2/3"></div>
                                    <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                                </div>
                            </template>

                            {{-- Error --}}
                            <template x-if="!vpnLoading && vpnError">
                                <p class="text-sm text-gray-400 dark:text-gray-500 italic" x-text="vpnError"></p>
                            </template>

                            {{-- No VPN configured --}}
                            <template x-if="!vpnLoading && !vpnError && !hasAnyVpn()">
                                <p class="text-sm text-gray-400 dark:text-gray-500 italic">No VPN tunnels configured.</p>
                            </template>

                            {{-- VPN sections --}}
                            <div x-show="!vpnLoading && !vpnError && hasAnyVpn()" class="space-y-5">

                                {{-- ── IPsec ── --}}
                                <template x-if="vpnData && vpnData.ipsec && vpnData.ipsec.total > 0">
                                    <div>
                                        {{-- Header row --}}
                                        <div class="flex items-center justify-between mb-1.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-[10px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300">IPsec</span>
                                                <span class="text-lg font-bold text-gray-800 dark:text-gray-100 leading-none">
                                                    <span x-text="vpnData.ipsec.up"></span><span class="text-gray-400 dark:text-gray-500 font-normal"> / </span><span x-text="vpnData.ipsec.total"></span>
                                                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">Up</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span x-show="vpnData.ipsec.down > 0"
                                                      class="text-xs font-semibold text-red-500 dark:text-red-400"
                                                      x-text="vpnData.ipsec.down + ' Down'"></span>
                                                <a href="{{ route('vpn.ipsec', $firewall) }}"
                                                   class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium whitespace-nowrap">View →</a>
                                            </div>
                                        </div>
                                        {{-- Progress bar --}}
                                        <div class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mb-3">
                                            <div class="h-1.5 rounded-full transition-all duration-700"
                                                 :class="vpnData.ipsec.down > 0 ? 'bg-green-500' : 'bg-green-500'"
                                                 :style="'width: ' + (vpnData.ipsec.total > 0 ? Math.round((vpnData.ipsec.up / vpnData.ipsec.total) * 100) : 0) + '%'"></div>
                                        </div>
                                        {{-- Tunnel rows --}}
                                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                            <template x-for="tunnel in vpnData.ipsec.tunnels" :key="(tunnel.con_name || tunnel.name) + tunnel.remote">
                                                <div class="py-2">
                                                    {{-- Phase 1 row: name + remote subtitle + status badge + uptime --}}
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="flex items-start gap-2 min-w-0">
                                                            <div class="w-2 h-2 rounded-full flex-shrink-0 mt-1.5"
                                                                 :class="tunnel.status === 'up' ? 'bg-green-500' : 'bg-red-500'"></div>
                                                            <div class="min-w-0">
                                                                <div class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate"
                                                                     x-text="tunnel.name"
                                                                     :title="tunnel.remote_id || tunnel.remote"></div>
                                                                {{-- Show remote_id (FQDN/identity) if available, else fall back to IP --}}
                                                                <div x-show="(tunnel.remote_id || tunnel.remote) && (tunnel.remote_id || tunnel.remote) !== tunnel.name"
                                                                     class="text-[11px] text-gray-400 dark:text-gray-500 font-mono mt-0.5 truncate"
                                                                     x-text="(tunnel.remote_id && tunnel.remote_id !== tunnel.remote) ? tunnel.remote_id : tunnel.remote"></div>
                                                            </div>
                                                        </div>
                                                        <div class="flex items-center gap-2 flex-shrink-0 mt-0.5">
                                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                                                  :class="tunnel.status === 'up'
                                                                      ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400'
                                                                      : 'bg-red-100 dark:bg-red-900/40 text-red-700 dark:text-red-400'"
                                                                  x-text="tunnel.status === 'up' ? 'UP' : 'DOWN'"></span>
                                                            <span x-show="tunnel.established > 0"
                                                                  class="text-[11px] text-gray-400 dark:text-gray-500 font-mono"
                                                                  x-text="formatUptime(tunnel.established)"></span>
                                                        </div>
                                                    </div>
                                                    {{-- Phase 2 child SAs --}}
                                                    <template x-if="tunnel.peers && tunnel.peers.length > 0">
                                                        <div class="mt-1 ml-4 divide-y divide-gray-100 dark:divide-gray-700/40">
                                                            <template x-for="peer in tunnel.peers" :key="peer.con_name || peer.name">
                                                                <div class="flex items-center justify-between py-1 gap-2"
                                                                     :title="(peer.local_ts ? 'Local: ' + peer.local_ts : '') + (peer.remote_ts ? '  →  Remote: ' + peer.remote_ts : '')">
                                                                    <div class="flex items-center gap-1.5 min-w-0">
                                                                        <div class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                                                             :class="{
                                                                                 'bg-green-400':  peer.status === 'active',
                                                                                 'bg-yellow-400': peer.status === 'inactive',
                                                                                 'bg-red-400':    peer.status === 'disabled'
                                                                             }"></div>
                                                                        <span class="text-xs text-gray-600 dark:text-gray-300 truncate"
                                                                              x-text="peer.name"></span>
                                                                        <span x-show="peer.con_name && peer.con_name !== peer.name"
                                                                              class="text-[10px] text-gray-400 dark:text-gray-500 font-mono truncate"
                                                                              x-text="peer.con_name"></span>
                                                                    </div>
                                                                    <span class="flex-shrink-0 inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold"
                                                                          :class="{
                                                                              'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400':   peer.status === 'active',
                                                                              'bg-yellow-100 dark:bg-yellow-900/40 text-yellow-700 dark:text-yellow-400': peer.status === 'inactive',
                                                                              'bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400':            peer.status === 'disabled'
                                                                          }"
                                                                          x-text="peer.status === 'active' ? 'SA UP' : peer.status === 'inactive' ? 'NEGOTIATING' : 'DOWN'"></span>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- ── OpenVPN ── --}}
                                <template x-if="vpnData && vpnData.openvpn && vpnData.openvpn.total > 0">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-[10px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-green-100 dark:bg-green-900/60 text-green-700 dark:text-green-300">OpenVPN</span>
                                                <span class="text-lg font-bold text-gray-800 dark:text-gray-100 leading-none">
                                                    <span x-text="vpnData.openvpn.up"></span><span class="text-gray-400 dark:text-gray-500 font-normal"> / </span><span x-text="vpnData.openvpn.total"></span>
                                                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">Up</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span x-show="vpnData.openvpn.down > 0"
                                                      class="text-xs font-semibold text-red-500 dark:text-red-400"
                                                      x-text="vpnData.openvpn.down + ' Down'"></span>
                                                <a href="{{ route('vpn.openvpn.servers', $firewall) }}"
                                                   class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium whitespace-nowrap">View →</a>
                                            </div>
                                        </div>
                                        <div class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mb-3">
                                            <div class="h-1.5 bg-green-500 rounded-full transition-all duration-700"
                                                 :style="'width: ' + (vpnData.openvpn.total > 0 ? Math.round((vpnData.openvpn.up / vpnData.openvpn.total) * 100) : 0) + '%'"></div>
                                        </div>
                                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                            <template x-for="tunnel in vpnData.openvpn.tunnels" :key="tunnel.name + (tunnel.role || '')">
                                                <div class="flex items-center justify-between py-1.5 gap-2">
                                                    <div class="flex items-center gap-2 min-w-0">
                                                        <div class="w-2 h-2 rounded-full flex-shrink-0"
                                                             :class="tunnel.status === 'up' ? 'bg-green-500' : 'bg-red-500'"></div>
                                                        <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate"
                                                              x-text="tunnel.name"></span>
                                                        <span x-show="tunnel.role === 'client'"
                                                              class="text-[10px] text-gray-400 dark:text-gray-500 uppercase">(client)</span>
                                                    </div>
                                                    <div class="flex items-center gap-3 flex-shrink-0">
                                                        <span class="text-xs font-semibold uppercase tracking-wide"
                                                              :class="tunnel.status === 'up' ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'"
                                                              x-text="tunnel.status === 'up' ? 'UP' : 'DOWN'"></span>
                                                        <span class="text-xs text-gray-400 dark:text-gray-500 font-mono w-14 text-right"
                                                              x-text="tunnel.remote || '—'"></span>
                                                    </div>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                                {{-- ── WireGuard ── --}}
                                <template x-if="vpnData && vpnData.wireguard && vpnData.wireguard.total > 0">
                                    <div>
                                        <div class="flex items-center justify-between mb-1.5">
                                            <div class="flex items-center gap-2.5">
                                                <span class="text-[10px] font-bold uppercase tracking-widest px-1.5 py-0.5 rounded bg-purple-100 dark:bg-purple-900/60 text-purple-700 dark:text-purple-300">WireGuard</span>
                                                <span class="text-lg font-bold text-gray-800 dark:text-gray-100 leading-none">
                                                    <span x-text="vpnData.wireguard.up"></span><span class="text-gray-400 dark:text-gray-500 font-normal"> / </span><span x-text="vpnData.wireguard.total"></span>
                                                    <span class="text-sm font-normal text-gray-500 dark:text-gray-400 ml-1">Up</span>
                                                </span>
                                            </div>
                                            <div class="flex items-center gap-3">
                                                <span x-show="vpnData.wireguard.down > 0"
                                                      class="text-xs font-semibold text-red-500 dark:text-red-400"
                                                      x-text="vpnData.wireguard.down + ' Down'"></span>
                                                <a href="{{ route('vpn.wireguard.index', $firewall) }}"
                                                   class="text-xs text-blue-600 dark:text-blue-400 hover:underline font-medium whitespace-nowrap">View →</a>
                                            </div>
                                        </div>
                                        <div class="w-full h-1.5 bg-gray-200 dark:bg-gray-700 rounded-full mb-3">
                                            <div class="h-1.5 bg-green-500 rounded-full transition-all duration-700"
                                                 :style="'width: ' + (vpnData.wireguard.total > 0 ? Math.round((vpnData.wireguard.up / vpnData.wireguard.total) * 100) : 0) + '%'"></div>
                                        </div>
                                        <div class="divide-y divide-gray-100 dark:divide-gray-700/60">
                                            <template x-for="tunnel in vpnData.wireguard.tunnels" :key="tunnel.name">
                                                <div>
                                                    {{-- Tunnel header row --}}
                                                    <div class="flex items-center justify-between py-1.5 gap-2">
                                                        <div class="flex items-center gap-2 min-w-0">
                                                            <div class="w-2 h-2 rounded-full flex-shrink-0"
                                                                 :class="tunnel.status === 'up' ? 'bg-green-500' : 'bg-red-500'"></div>
                                                            <span class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate"
                                                                  x-text="tunnel.name"
                                                                  :title="tunnel.remote"></span>
                                                        </div>
                                                        <div class="flex items-center gap-3 flex-shrink-0">
                                                            <span class="text-xs font-semibold uppercase tracking-wide"
                                                                  :class="tunnel.status === 'up' ? 'text-green-600 dark:text-green-400' : 'text-red-500 dark:text-red-400'"
                                                                  x-text="tunnel.status === 'up' ? 'ACTIVE' : 'DISABLED'"></span>
                                                            <span x-show="tunnel.port"
                                                                  class="text-xs text-gray-400 dark:text-gray-500 font-mono"
                                                                  x-text="':' + tunnel.port"></span>
                                                        </div>
                                                    </div>
                                                    {{-- Peer list — 3-state: active (green), inactive (amber), disabled (red) --}}
                                                    <template x-if="tunnel.peers && tunnel.peers.length > 0">
                                                        <div class="divide-y divide-gray-100 dark:divide-gray-700/50 mt-1">
                                                            <template x-for="peer in tunnel.peers" :key="peer.name">
                                                                <div class="text-xs py-1.5">
                                                                    {{-- Row 1: status dot · name · status badge --}}
                                                                    <div class="flex items-center justify-between gap-2"
                                                                         :title="peer.endpoint ? 'Endpoint: ' + peer.endpoint : ''">
                                                                        <div class="flex items-center gap-1.5 min-w-0">
                                                                            <div class="w-1.5 h-1.5 rounded-full flex-shrink-0"
                                                                                 :class="{
                                                                                     'bg-green-400':  peer.status === 'active',
                                                                                     'bg-yellow-400': peer.status === 'inactive',
                                                                                     'bg-red-400':    peer.status === 'disabled'
                                                                                 }"></div>
                                                                            <span class="text-gray-600 dark:text-gray-300 font-medium truncate"
                                                                                  x-text="peer.name"></span>
                                                                        </div>
                                                                        <span class="flex-shrink-0 font-semibold"
                                                                              :class="{
                                                                                  'text-green-500 dark:text-green-400':   peer.status === 'active',
                                                                                  'text-yellow-500 dark:text-yellow-400': peer.status === 'inactive',
                                                                                  'text-red-400':                         peer.status === 'disabled'
                                                                              }"
                                                                              x-text="peer.status === 'active' ? 'ACTIVE' : peer.status === 'inactive' ? 'INACTIVE' : 'DISABLED'"></span>
                                                                    </div>
                                                                    {{-- Row 2: handshake + RX/TX (only when we have data) --}}
                                                                    <div x-show="peer.last_handshake !== null || peer.rx !== null"
                                                                         class="flex items-center gap-3 pl-3 mt-0.5 text-[10px] text-gray-400 dark:text-gray-500 font-mono">
                                                                        <span x-show="peer.last_handshake !== null"
                                                                              :class="peer.status === 'active' ? 'text-green-600 dark:text-green-500' : 'text-gray-400'"
                                                                              x-text="'⏱ ' + timeSince(peer.last_handshake)"></span>
                                                                        <span x-show="peer.rx !== null"
                                                                              class="text-green-600 dark:text-green-500"
                                                                              x-text="'↓ ' + (peer.rx || '0 B')"></span>
                                                                        <span x-show="peer.tx !== null"
                                                                              class="text-blue-600 dark:text-blue-400"
                                                                              x-text="'↑ ' + (peer.tx || '0 B')"></span>
                                                                    </div>
                                                                </div>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </div>
                                </template>

                            </div>
                        </div>
                    </div>

                    {{-- Config Backup (GlobalAdmin only) --}}
                    @if(auth()->user()->isGlobalAdmin())
                    @php $backup = $firewall->configBackup; $sshMissing = !$firewall->isOpnSense() && (empty($firewall->ssh_username) || empty($firewall->ssh_password)); @endphp
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg"
                         x-data="backupCard({
                             triggerUrl:  '{{ route('firewall.backup.trigger', $firewall) }}',
                             statusUrl:   '{{ route('firewall.backup.status', $firewall) }}',
                             downloadUrl: '{{ route('firewall.backup.download', $firewall) }}',
                             csrf:        '{{ csrf_token() }}',
                             initial: {
                                 status:      '{{ $sshMissing ? 'none' : ($backup?->status ?? 'none') }}',
                                 pulledAt:    '{{ $sshMissing ? '' : ($backup?->pulled_at?->utc()->toIso8601String() ?? '') }}',
                                 attemptedAt: '{{ $sshMissing ? '' : ($backup?->last_attempted_at?->utc()->toIso8601String() ?? '') }}',
                                 sizeKb:      '{{ $sshMissing ? '' : ($backup && $backup->size_bytes ? number_format($backup->size_bytes / 1024, 2) : '') }}',
                                 hash:        '{{ $sshMissing ? '' : ($backup?->sha256_hash ? substr($backup->sha256_hash, 0, 12) : '') }}',
                                 error:       '{{ $sshMissing ? '' : str_replace("'", "\\'", trim(preg_replace('/\s+/', ' ', $backup?->error_message ?? ''))) }}',
                             }
                         })"
                         >
                        <div class="p-6 text-gray-900 dark:text-gray-100">

                            {{-- Header --}}
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-xl font-semibold">Configuration Backup</h3>

                                <div class="flex items-center gap-2 shrink-0">
                                    @if(!$firewall->isOpnSense() && (empty($firewall->ssh_username) || empty($firewall->ssh_password)))
                                        <a href="{{ route('firewalls.edit', $firewall) }}"
                                            class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shadow-sm">
                                            <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                            Configure SSH
                                        </a>
                                    @else
                                        <a x-show="status === 'success'" x-bind:href="downloadUrl"
                                            class="inline-flex items-center px-3 py-1.5 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded-md text-xs font-medium text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors shadow-sm"
                                            style="display:none;">
                                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                                            Download
                                        </a>
                                        <button @click="runBackup()" x-bind:disabled="status === 'running'"
                                            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-md text-xs font-medium transition-colors shadow-sm">
                                            <svg x-show="status !== 'running'" class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                            <svg x-show="status === 'running'" class="animate-spin w-3.5 h-3.5 mr-1" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                            <span x-text="status === 'running' ? 'Running…' : 'Run Backup'"></span>
                                        </button>
                                    @endif
                                </div>
                            </div>

                            {{-- Status body --}}
                            <div class="text-sm">
                                {{-- None / Missing --}}
                                <template x-if="status === 'none' || status === 'missing'">
                                    <p class="text-gray-400 dark:text-gray-500 italic">No backup on record. Run a backup to get started.</p>
                                </template>

                                {{-- Running --}}
                                <template x-if="status === 'running'">
                                    <div class="flex items-center gap-2 text-indigo-600 dark:text-indigo-400">
                                        <svg class="animate-spin h-4 w-4" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                                        <span>Backup in progress…</span>
                                    </div>
                                </template>

                                {{-- Success --}}
                                <template x-if="status === 'success'">
                                    <div class="space-y-2">
                                        <div class="flex items-center gap-1.5 text-green-600 dark:text-green-400 text-xs font-medium">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                            Backup successful
                                        </div>
                                        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Pulled: <span class="text-gray-400" x-text="pulledAt"></span></span>
                                            <span x-show="sizeKb">Size: <span x-text="sizeKb + ' KB'"></span></span>
                                            <span x-show="hash">SHA256: <span class="bg-gray-50 dark:bg-gray-700/50 px-2 py-0.5 rounded border border-gray-200 dark:border-gray-700 font-mono" x-text="hash + '…'"></span></span>
                                        </div>
                                    </div>
                                </template>

                                {{-- Failed --}}
                                <template x-if="status === 'failed'">
                                    <div class="space-y-1">
                                        <div class="flex items-center gap-1.5 text-red-600 dark:text-red-400 text-xs font-medium">
                                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/></svg>
                                            Backup failed <span class="font-normal text-gray-400 ml-1" x-text="attemptedAt ? '(' + attemptedAt + ')' : ''"></span>
                                        </div>
                                        <p x-show="errorMsg" x-text="errorMsg" class="mt-2 text-xs text-red-700 dark:text-red-300 bg-red-50 dark:bg-red-900/30 px-2 py-1.5 rounded border border-red-100 dark:border-red-800/50 truncate" style="display:none;"></p>
                                    </div>
                                </template>
                            </div>

                        </div>
                    </div>
                    @endif

                    {{-- Packages Summary --}}
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-xl font-semibold">Packages</h3>
                            </div>

                            <template x-if="packagesLoading">
                                <div class="space-y-4 animate-pulse">
                                    <div class="h-8 bg-gray-200 rounded w-full"></div>
                                    <div class="h-8 bg-gray-200 rounded w-full"></div>
                                </div>
                            </template>

                            <div class="overflow-x-auto" x-show="!packagesLoading">
                                <template x-if="packages.length === 0">
                                    <div class="text-gray-500 text-sm italic">No packages installed.</div>
                                </template>

                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400"
                                    x-show="packages.length > 0">
                                    <thead
                                        class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Name</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium min-w-[200px]">
                                                Description</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Installed</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium">Latest</th>
                                            <th scope="col" class="px-3 py-2 text-sm font-medium text-center">Status
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="pkg in packages" :key="pkg.name">
                                            <tr
                                                class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                                <td class="px-3 py-2 font-medium text-gray-900 dark:text-white"
                                                    x-text="pkg.shortname || '-'"></td>
                                                <td class="px-3 py-2 text-xs whitespace-normal break-words"
                                                    x-text="pkg.descr || '-'"></td>
                                                <td class="px-3 py-2 font-mono text-xs"
                                                    :class="{'text-red-600 font-bold': pkg.update_available, 'text-gray-900 dark:text-gray-300': !pkg.update_available}"
                                                    x-text="pkg.installed_version"></td>
                                                <td class="px-3 py-2 font-mono text-xs"
                                                    x-text="pkg.latest_version || '-'"></td>
                                                <td class="px-3 py-2 text-center">
                                                    <template x-if="pkg.update_available">
                                                        <span
                                                            class="bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-0.5 rounded dark:bg-yellow-900 dark:text-yellow-300">Update</span>
                                                    </template>
                                                    <template x-if="!pkg.update_available">
                                                        <span
                                                            class="bg-green-100 text-green-800 text-xs font-medium px-2 py-0.5 rounded dark:bg-green-900 dark:text-green-300">OK</span>
                                                    </template>
                                                </td>
                                            </tr>
                                        </template>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                </div>



            </div>
        </div>

        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('firewallDashboard', () => ({
@php
    // $initialStatus is pre-resolved by DashboardController::firewall().
    // No cache lookup here — keep data resolution in the controller.
    $initialStatus    = $initialStatus ?? null;
    $systemLoading    = $initialStatus ? 'false' : 'true';
    $systemConnected  = ($initialStatus['online'] ?? false) ? 'true' : 'false';
@endphp
                    systemLoading: {{ $systemLoading }},
                    systemConnected: {{ $systemConnected }},
                    systemStatus: {!! json_encode($initialStatus, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},
                    systemError: null,

                    interfaces: [],
                    interfacesLoading: true,

                    gateways: {!! json_encode($initialStatus['gateways'] ?? [], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!},
                    gatewaysLoading: {{ empty($initialStatus['gateways'] ?? []) ? 'true' : 'false' }},

                    rules: [],
                    rulesLoading: true,
                    lastUpdated: null,

                    packages: [],
                    packagesLoading: true,

                    // VPN Summary
                    vpnData: null,
                    vpnLoading: true,
                    vpnError: null,
                    vpnTimer: null,

                    // Traffic Monitor
                    bandwidthHistory: new Array(20).fill(0).map(() => ({ in: 0, out: 0 })),
                    currentTraffic: { in: '0 Bps', out: '0 Bps' },
                    lastBytes: { in: 0, out: 0, time: 0 },

                    // Interface Monitor (Multi-Interface)
                    interfaceHistory: {},  // Map of interface name -> Array of history
                    lastInterfaceBytes: {}, // Map of interface name -> {in, out, time}
                    interfaceRates: {},     // Map of interface name -> {in, out}

                    // Load Monitor
                    loadHistory: new Array(20).fill(0),

                    extractBytes(iface, type) {
                        // type: 'in' or 'out'

                        // Helper to check an object and parse units with loose key matching
                        const check = (o) => {
                            if (!o || typeof o !== 'object') return null;

                            for (const [key, rawValue] of Object.entries(o)) {
                                if (rawValue === null || rawValue === undefined) continue;

                                const cleanKey = key.toLowerCase().replace(/[^a-z0-9]/g, '');

                                let isMatch = false;
                                if (type === 'in') {
                                    isMatch = ['inbytes', 'bytesin', 'rxbytes', 'inputbytes', 'in'].includes(cleanKey);
                                } else {
                                    isMatch = ['outbytes', 'bytesout', 'txbytes', 'outputbytes', 'out'].includes(cleanKey);
                                }

                                if (isMatch) {
                                    const s = String(rawValue).replace(/,/g, '').trim();
                                    const val = parseFloat(s);
                                    if (isNaN(val)) continue;

                                    const upper = s.toUpperCase();
                                    // Handle Units (PB, TB, GB, MB, KB)
                                    // Assume Base 1024 for data sizes
                                    if (upper.includes('P')) return val * 1024 * 1024 * 1024 * 1024 * 1024;
                                    if (upper.includes('T')) return val * 1024 * 1024 * 1024 * 1024;
                                    if (upper.includes('G')) return val * 1024 * 1024 * 1024;
                                    if (upper.includes('M')) return val * 1024 * 1024;
                                    if (upper.includes('K')) return val * 1024;

                                    return val;
                                }
                            }
                            return null;
                        };

                        // 1. Check root
                        let val = check(iface);
                        if (val !== null) return val;

                        // 2. Check 'stats'
                        val = check(iface.stats);
                        if (val !== null) return val;

                        // 3. Check 'statistics'
                        val = check(iface.statistics);
                        if (val !== null) return val;

                        return 0;
                    },

                    updateBandwidthFromInterfaces(interfaces) {
                        const now = new Date().getTime();

                        // 1. Process WAN
                        // Robust find: Check keys AND descr/name properties
                        const ifaceList = Object.values(interfaces);
                        let wan = ifaceList.find(i => (i.name && i.name.toLowerCase() === 'wan') || (i.descr && i.descr.toLowerCase() === 'wan'));

                        // Fallback: Check Keys if object (standard object structure)
                        if (!wan && !Array.isArray(interfaces)) {
                            const wanKey = Object.keys(interfaces).find(key => key.toLowerCase() === 'wan');
                            if (wanKey) wan = interfaces[wanKey];
                        }

                        // Fallback: First interface if nothing else found
                        if (!wan && ifaceList.length > 0) wan = ifaceList[0];

                        if (wan) {
                            const bytesIn  = this.extractBytes(wan, 'in');
                            const bytesOut = this.extractBytes(wan, 'out');
                            let inRate  = 0;
                            let outRate = 0;

                            if (wan.in_rate_bps !== undefined && wan.out_rate_bps !== undefined) {
                                // Prefer server-computed rates (backend calculates delta between polls).
                                // Available on OPNsense once the interface byte-snapshot cache is warm.
                                inRate  = parseFloat(wan.in_rate_bps  || 0);
                                outRate = parseFloat(wan.out_rate_bps || 0);
                            } else if (this.lastBytes.time > 0) {
                                // Fall back to client-side delta (requires two successive readings)
                                const timeDiff = (now - this.lastBytes.time) / 1000;
                                if (timeDiff > 0) {
                                    if (bytesIn  >= this.lastBytes.in)  inRate  = ((bytesIn  - this.lastBytes.in)  * 8) / timeDiff;
                                    if (bytesOut >= this.lastBytes.out) outRate = ((bytesOut - this.lastBytes.out) * 8) / timeDiff;
                                }
                            }

                            this.lastBytes = { in: bytesIn, out: bytesOut, time: now };
                            this.bandwidthHistory.shift();
                            this.bandwidthHistory.push({ in: inRate, out: outRate });
                            this.currentTraffic = {
                                in:  this.formatBytes(inRate,  true),
                                out: this.formatBytes(outRate, true)
                            };
                        }


                        // 2. Process ALL interfaces
                        Object.entries(interfaces).forEach(([name, iface]) => {
                            // Initialize history if new
                            if (!this.interfaceHistory[name]) {
                                this.interfaceHistory[name] = new Array(20).fill(0).map(() => ({ in: 0, out: 0 }));
                                this.lastInterfaceBytes[name] = { in: 0, out: 0, time: 0 };
                                this.interfaceRates[name] = { in: '0 bps', out: '0 bps' };
                            }

                            const iBytesIn = this.extractBytes(iface, 'in');
                            const iBytesOut = this.extractBytes(iface, 'out');
                            let iInRate = 0;
                            let iOutRate = 0;
                            const last = this.lastInterfaceBytes[name];

                            if (last.time > 0) {
                                const timeDiff = (now - last.time) / 1000;
                                if (timeDiff > 0) {
                                    if (iBytesIn >= last.in) iInRate = ((iBytesIn - last.in) * 8) / timeDiff;
                                    if (iBytesOut >= last.out) iOutRate = ((iBytesOut - last.out) * 8) / timeDiff;
                                }
                            }

                            this.lastInterfaceBytes[name] = { in: iBytesIn, out: iBytesOut, time: now };
                            this.interfaceHistory[name].shift();
                            this.interfaceHistory[name].push({ in: iInRate, out: iOutRate });
                            this.interfaceRates[name] = {
                                in: this.formatBytes(iInRate, true),
                                out: this.formatBytes(iOutRate, true)
                            };
                        });

                        // Force strict reactivity update for deep object changes
                        this.interfaceRates = { ...this.interfaceRates };
                    },


                    formatBytes(size, isBits = false) {
                        if (!+size) return isBits ? '0 bps' : '0 B';
                        const k = 1024;
                        const decimals = 2;
                        const dm = decimals < 0 ? 0 : decimals;
                        const sizes = isBits
                            ? ['bps', 'Kbps', 'Mbps', 'Gbps', 'Tbps']
                            : ['B', 'KB', 'MB', 'GB', 'TB'];
                        const i = Math.floor(Math.log(size) / Math.log(k));
                        return `${parseFloat((size / Math.pow(k, i)).toFixed(dm))} ${sizes[i]}`;
                    },

                    getInterfaceLabel(iface, name) {
                        const n = (name !== null && name !== undefined) ? String(name).toUpperCase() : '';
                        const d = iface.descr || '';
                        const i = (iface.if || '').toUpperCase();

                        if (!d) return n || i;
                        if (d.toUpperCase() === n) return d;
                        return `${d} (${n})`;
                    },

                    getGraphPoints(type, interfaceName = null) {
                        let history = this.bandwidthHistory;
                        if (interfaceName && this.interfaceHistory[interfaceName]) {
                            history = this.interfaceHistory[interfaceName];
                        }

                        const safeMax = Math.max(...history.map(d => Math.max(Number(d.in) || 0, Number(d.out) || 0))) || 100;
                        const height = 40;
                        const width = 100;
                        const step = width / Math.max(history.length - 1, 1);

                        return history.map((d, i) => {
                            const val = Number(d[type]) || 0;
                            const y = height - ((val / safeMax) * height);
                            const safeY = isFinite(y) ? y : height;
                            return `${i * step},${safeY}`;
                        }).join(' ');
                    },

                    getLoadGraphPoints() {
                        const max = Math.max(...this.loadHistory, 1);
                        const height = 20;
                        const width = 100;
                        const step = width / (this.loadHistory.length - 1);
                        return this.loadHistory.map((val, i) => {
                            const y = height - ((val / max) * height);
                            return `${i * step},${y}`;
                        }).join(' ');
                    },

                    // ── VPN Summary ──────────────────────────────────────────

                    fetchVpnSummary() {
                        fetch('{{ route('firewall.vpn-summary', $firewall) }}?t=' + Date.now())
                            .then(res => res.json())
                            .then(data => {
                                this.vpnLoading = false;
                                if (data.error) {
                                    this.vpnError = 'Unable to fetch VPN status.';
                                } else {
                                    this.vpnData  = data;
                                    this.vpnError = null;
                                }
                            })
                            .catch(() => {
                                this.vpnLoading = false;
                                this.vpnError   = 'Unable to fetch VPN status.';
                            });
                    },

                    hasAnyVpn() {
                        if (!this.vpnData) return false;
                        return (this.vpnData.ipsec?.total     || 0)
                             + (this.vpnData.openvpn?.total   || 0)
                             + (this.vpnData.wireguard?.total || 0) > 0;
                    },

                    // Format seconds as "Xd Yh" / "Xh Ym" / "Xm"
                    formatUptime(seconds) {
                        if (!seconds || seconds <= 0) return '—';
                        const d = Math.floor(seconds / 86400);
                        const h = Math.floor((seconds % 86400) / 3600);
                        const m = Math.floor((seconds % 3600) / 60);
                        if (d > 0) return d + 'd ' + h + 'h';
                        if (h > 0) return h + 'h ' + m + 'm';
                        return m + 'm';
                    },

                    // Convert a unix timestamp to "Xm ago" / "Xh Xm ago" / "never"
                    timeSince(ts) {
                        if (!ts || ts <= 0) return 'never';
                        const elapsed = Math.floor(Date.now() / 1000) - ts;
                        if (elapsed < 0)  return 'just now';
                        if (elapsed < 60) return elapsed + 's ago';
                        const m = Math.floor(elapsed / 60);
                        const h = Math.floor(m / 60);
                        const d = Math.floor(h / 24);
                        if (d > 0)  return d + 'd ' + (h % 24) + 'h ago';
                        if (h > 0)  return h + 'h ' + (m % 60) + 'm ago';
                        return m + 'm ago';
                    },

                    // ────────────────────────────────────────────────────────

                    realtimeMs: {{ ($settings['realtime_interval'] ?? 10) * 1000 }},
                    fallbackMs: {{ ($settings['fallback_interval'] ?? 8) * 1000 }},

                    timer: null,

                    init() {
                        console.log('Initializing Firewall Dashboard...');
                        this.fetchSystemStatus(); // Initial fetch
                        this.fetchInterfaces();
                        this.fetchGateways();
                        this.fetchRules();
                        this.fetchPackages();
                        this.fetchVpnSummary(); // Initial VPN fetch
                        this.setupWebSocket();

                        this.startIntervalManager();

                        // VPN status poll — 5s interval for near-real-time troubleshooting
                        this.vpnTimer = setInterval(() => {
                            this.fetchVpnSummary();
                        }, 5000);
                    },

                    startIntervalManager() {
                        if (this.timer) clearTimeout(this.timer);

                        const isWsHealthy = () =>
                            window.Echo?.connector?.pusher?.connection?.state === 'connected';

                        const run = () => {
                            // fetchSystemStatus() reads from cache only — it does not dispatch jobs.
                            // Safe to suppress when WS is healthy and delivering updates.
                            if (!isWsHealthy()) {
                                this.fetchSystemStatus();
                            }
                            this.timer = setTimeout(run, this.fallbackMs);
                        };

                        // Always schedule at fallbackMs.
                        this.timer = setTimeout(run, this.fallbackMs);
                    },

                    fetchSystemStatus() {
                        // Add timestamp to prevent browser caching
                        fetch('{{ route('firewall.check-status', $firewall) }}?t=' + new Date().getTime())
                            .then(res => res.json())
                            .then(data => {
                                this.systemLoading = false;
                                this.systemConnected = data.online;

                                if (data.status) {
                                    // The check-status endpoint returns:
                                    // { online, status: { online, api_version, data: { flat fields }, gateways, interfaces } }
                                    // The template reads systemStatus.data.* — assign status directly.
                                    // Preserve existing values for any null fields (stale-while-revalidate).
                                    const incoming = data.status;
                                    if (!this.systemStatus) {
                                        this.systemStatus = incoming;
                                    } else {
                                        // Top-level fields
                                        if (incoming.online    !== undefined) this.systemStatus.online     = incoming.online;
                                        if (incoming.api_version)             this.systemStatus.api_version = incoming.api_version;
                                        if (incoming.updated_at)              this.systemStatus.updated_at  = incoming.updated_at;
                                        if (incoming.error !== undefined)      this.systemStatus.error       = incoming.error;
                                        // Nested data fields — merge to preserve unknowns
                                        if (incoming.data && typeof incoming.data === 'object') {
                                            this.systemStatus.data = Object.assign({}, this.systemStatus.data || {}, incoming.data);
                                        }
                                        // Top-level gateways / interfaces (also stored at root by checkStatus)
                                        if (incoming.gateways && incoming.gateways.length > 0)   this.systemStatus.gateways   = incoming.gateways;
                                        if (incoming.interfaces)                                  this.systemStatus.interfaces = incoming.interfaces;
                                    }

                                    // Sync gateways to separate Alpine property (gateway template reads 'gateways')
                                    const gw = this.systemStatus.gateways || this.systemStatus.data?.gateways;
                                    if (gw && gw.length > 0) this.gateways = gw;

                                    const ifaces = this.systemStatus.interfaces || this.systemStatus.data?.interfaces;
                                    if (ifaces) this.updateBandwidthFromInterfaces(ifaces);

                                    // Update Load History
                                    const loadAvg = this.systemStatus.data?.cpu_load_avg;
                                    if (loadAvg?.length > 0) {
                                        this.loadHistory.shift();
                                        this.loadHistory.push(parseFloat(loadAvg[0]) || 0);
                                    }

                                    this.lastUpdated = new Date().toLocaleTimeString();
                                    if (data.online) this.systemError = null;
                                    else this.systemError = incoming.error || 'Firewall reported offline.';
                                }
                            })
                            .catch(err => {
                                this.systemLoading = false;
                                // Do not clear systemStatus on transient network error —
                                // keep showing last-known values.
                                console.error('Fetch error:', err);
                            });
                    },


                    fetchInterfaces() {
                        // Added Accept header to ensure JSON response from StatusController
                        fetch('{{ route('status.interfaces.index', $firewall) }}', {
                            headers: { 'Accept': 'application/json' }
                        })
                            .then(res => res.json())
                            .then(data => {
                                // Handle data wrapper if present
                                const rawIfaces = data.data || data;
                                const ifaces = Array.isArray(rawIfaces) ? rawIfaces : Object.values(rawIfaces || {});
                                this.interfaces = ifaces;
                                this.interfacesLoading = false;

                                // Fallback: Update bandwidth if systemStatus didn't provide interfaces
                                if (ifaces && (!this.systemStatus || !this.systemStatus.interfaces)) {
                                    this.updateBandwidthFromInterfaces(ifaces);
                                }
                            })
                            .catch(err => {
                                console.error('Failed to load interfaces:', err);
                                this.interfacesLoading = false;
                            });
                    },

                    fetchGateways() {
                        fetch('{{ route('status.gateways', $firewall) }}', {
                            headers: { 'Accept': 'application/json' }
                        })
                            .then(res => res.json())
                            .then(data => {
                                const rawGw = data.data || data;
                                this.gateways = Array.isArray(rawGw) ? rawGw : Object.values(rawGw || {});
                                this.gatewaysLoading = false;
                            })
                            .catch(err => {
                                console.error('Failed to load gateways:', err);
                                this.gatewaysLoading = false;
                            });
                    },

                    fetchPackages() {
                        fetch('{{ route('status.packages', $firewall) }}', {
                            headers: { 'Accept': 'application/json' }
                        })
                            .then(res => res.json())
                            .then(data => {
                                this.packages = Array.isArray(data) ? data : [];
                                this.packagesLoading = false;
                            })
                            .catch(err => {
                                console.error('Failed to load packages:', err);
                                this.packagesLoading = false;
                            });
                    },

                    fetchRules() {
                        fetch('{{ route('firewall.rules.index', $firewall) }}', {
                            headers: { 'Accept': 'application/json' }
                        })
                            .then(res => res.json())
                            .then(data => {
                                this.rules = data; // Returns array directly based on Controller change
                                this.rulesLoading = false;
                            })
                            .catch(err => {
                                console.error('Failed to load rules:', err);
                                this.rulesLoading = false;
                            });
                    },

                    setupWebSocket() {
                        const subscribe = () => {
                            if (window.Echo) {
                                console.log('Listening for Websocket updates...');
                                window.Echo.private('firewall.{{ $firewall->id }}')
                                    .listen('.firewall.status.update', (e) => {
                                        // Broadcast sends FLAT fields (e.status.product_version, e.status.gateways, ...).
                                        // Template reads systemStatus.data.* so reconstruct the cache wrapper shape.
                                        const s = e.status || {};
                                        if (!this.systemStatus) this.systemStatus = { data: {} };
                                        if (!this.systemStatus.data) this.systemStatus.data = {};

                                        // Top-level wrapper fields
                                        if (s.online    !== undefined) this.systemStatus.online     = s.online;
                                        if (s.api_version)             this.systemStatus.api_version = s.api_version;
                                        if (s.updated_at)              this.systemStatus.updated_at  = s.updated_at;
                                        if (s.error !== undefined)     this.systemStatus.error       = s.error;

                                        // Promote flat broadcast fields into .data where the template reads them
                                        const dataFields = ['product_version','update_available','api_update_available',
                                                            'cpu_load_avg','cpu_usage','mem_usage','swap_usage','disk_usage',
                                                            'temp_c','uptime','bios_vendor','bios_version','bios_date',
                                                            'cpu_model','cpu_count','platform','version','dns_servers',
                                                            'last_config_change','last_config_change_ts','installed_packages_count'];
                                        dataFields.forEach(k => {
                                            if (s[k] !== null && s[k] !== undefined) this.systemStatus.data[k] = s[k];
                                        });

                                        // gateways / interfaces stay at root AND in data
                                        if (s.gateways   && s.gateways.length > 0)   { this.systemStatus.gateways   = s.gateways;   this.gateways   = s.gateways; }
                                        if (s.interfaces)                             { this.systemStatus.interfaces = s.interfaces; }

                                        this.systemLoading   = false;
                                        this.systemConnected = s.online ?? true;

                                        const ifaces = this.systemStatus.interfaces;
                                        if (ifaces) this.updateBandwidthFromInterfaces(ifaces);

                                        const loadAvg = this.systemStatus.data?.cpu_load_avg;
                                        if (loadAvg?.length > 0) {
                                            this.loadHistory.shift();
                                            this.loadHistory.push(parseFloat(loadAvg[0]) || 0);
                                        }

                                        this.lastUpdated = new Date().toLocaleTimeString();
                                    });
                            } else {
                                setTimeout(subscribe, 500);
                            }
                        };
                        subscribe();
                    }
                }));

                Alpine.data('backupCard', (config) => ({
                    triggerUrl:  config.triggerUrl,
                    statusUrl:   config.statusUrl,
                    downloadUrl: config.downloadUrl,
                    csrf:        config.csrf,
                    status:      (config.initial && config.initial.status)      || 'none',
                    pulledAt:    '',
                    attemptedAt: '',
                    sizeKb:      (config.initial && config.initial.sizeKb)      || '',
                    hash:        (config.initial && config.initial.hash)        || '',
                    errorMsg:    (config.initial && config.initial.error)       || '',
                    _pollTimer:  null,

                    formatDate(isoString) {
                        if (!isoString) return '';
                        try {
                            return new Date(isoString).toLocaleString(undefined, {
                                month: 'short', day: 'numeric', year: 'numeric',
                                hour: 'numeric', minute: '2-digit',
                            });
                        } catch (e) { return isoString; }
                    },

                    init() {
                        this.pulledAt    = this.formatDate(config.initial?.pulledAt    || '');
                        this.attemptedAt = this.formatDate(config.initial?.attemptedAt || '');
                        if (this.status === 'running') this._startPolling();
                    },

                    async runBackup() {
                        if (this.status === 'running') return;
                        this.status = 'running';
                        this.errorMsg = this.pulledAt = this.attemptedAt = this.sizeKb = this.hash = '';
                        try {
                            const res = await fetch(this.triggerUrl, {
                                method: 'POST',
                                headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', 'Content-Type': 'application/json' },
                            });
                            if (!res.ok) {
                                const data = await res.json().catch(() => ({}));
                                this.status = 'failed';
                                this.errorMsg = data.error || 'Could not start backup. Please check firewall settings.';
                                return;
                            }
                        } catch (e) {
                            this.status = 'failed';
                            this.errorMsg = 'Failed to reach the server. Please try again.';
                            return;
                        }
                        this._startPolling();
                    },

                    _startPolling() {
                        if (this._pollTimer) return;
                        this._pollTimer = setInterval(() => this._poll(), 3000);
                    },

                    _stopPolling() {
                        if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null; }
                    },

                    async _poll() {
                        try {
                            const res  = await fetch(this.statusUrl, { headers: { 'Accept': 'application/json' } });
                            const data = await res.json();
                            this.status = data.status;
                            if (data.status === 'success') {
                                this.pulledAt = this.formatDate(data.pulled_at || '');
                                this.sizeKb   = data.size_kb   || '';
                                this.hash     = data.hash      || '';
                                this.errorMsg = '';
                                this._stopPolling();
                            } else if (data.status === 'failed') {
                                this.attemptedAt = this.formatDate(data.attempted_at || '');
                                this.errorMsg    = data.error || '';
                                this._stopPolling();
                            }
                        } catch (e) { /* network blip — keep polling */ }
                    },
                }));
            });
        </script>

        <!-- Delete Confirmation Modal -->
        <!-- Delete Confirmation Modal -->
        <x-modal name="delete-firewall-modal" :show="false" focusable>
            <div x-data="{ confirmEmail: '' }"
                x-on:open-modal.window="if ($event.detail === 'delete-firewall-modal') confirmEmail = ''">
                <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div
                            class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24"
                                stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                            </svg>
                        </div>
                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-100">
                                {{ __('Delete Firewall') }}
                            </h3>
                            <div class="mt-2 text-left">
                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                    {{ __('Are you sure you want to delete this firewall? This action cannot be undone.') }}
                                </p>
                                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                    {{ __('Please type your email address to confirm:') }} <span
                                        class="font-mono font-bold">{{ auth()->user()->email }}</span>
                                </p>

                                <div class="mt-4">
                                    <x-input-label for="confirm_email" value="{{ __('Email Address') }}"
                                        class="sr-only" />

                                    <x-text-input id="confirm_email" name="confirm_email" type="email"
                                        class="block w-full sm:w-3/4" placeholder="{{ __('Email Address') }}"
                                        x-model="confirmEmail"
                                        @keyup.enter="if(confirmEmail === '{{ auth()->user()->email }}') document.getElementById('delete-firewall-form').submit()" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                    <x-danger-button class="sm:ml-3 w-full sm:w-auto justify-center"
                        x-bind:disabled="confirmEmail !== '{{ auth()->user()->email }}'"
                        x-bind:class="{ 'opacity-50 cursor-not-allowed': confirmEmail !== '{{ auth()->user()->email }}' }"
                        @click="document.getElementById('delete-firewall-form').submit()">
                        {{ __('Delete Firewall') }}
                    </x-danger-button>

                    <x-secondary-button class="mt-3 sm:mt-0 w-full sm:w-auto justify-center"
                        @click="$dispatch('close')">
                        {{ __('Cancel') }}
                    </x-secondary-button>
                </div>
            </div>
        </x-modal>
</x-app-layout>
