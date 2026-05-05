<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Firewalls') }}
            </h2>
            <div class="mt-1 flex flex-col sm:flex-row sm:items-center">
                <div class="text-sm text-gray-500 dark:text-gray-400 flex items-center">
                    <a href="{{ route('dashboard') }}"
                        class="font-medium text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 transition-colors duration-200">
                        {{ __('Dashboard') }}
                    </a>
                    <span class="mx-2 text-gray-300 dark:text-gray-600">/</span>
                    <span class="font-medium">
                        {{ __('Firewalls') }}
                    </span>
                </div>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="bg-green-500 text-white p-4 rounded-lg mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('bulk_results'))
                <div class="bg-blue-100 border-l-4 border-blue-500 text-blue-700 p-4 mb-4" role="alert">
                    <p class="font-bold">Bulk Action Results:</p>
                    <ul class="list-disc pl-5">
                        @foreach(session('bulk_results') as $result)
                            <li>{{ $result }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Top Section: Map & Stats -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">

                {{-- Map Section (2/3 width) --}}
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg h-full"
                    x-data="firewallMap()" x-init="initMap()">
                    <div class="p-2 text-sm text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300"
                        x-show="status" x-text="status"></div>
                    <div id="firewall-map" style="height: 100%; min-height: 480px; width: 100%; z-index: 0;"></div>
                </div>

                {{-- Stats Widgets (1/3 width, stacked) --}}
                <div x-data="firewallStats({{ $firewalls->pluck('id') }})" class="flex flex-col gap-4">
                    <!-- Total Firewalls -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center flex-1">
                        <div
                            class="p-3 rounded-full bg-blue-100 dark:bg-blue-900 text-blue-500 dark:text-blue-300 mr-4">
                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Firewalls</p>
                            <div class="flex items-center">
                                <p class="text-4xl font-bold text-gray-900 dark:text-gray-100" x-text="totalCount">
                                    {{ $totalFirewalls }}
                                </p>
                                <template x-if="offlineCount === 0">
                                    <span
                                        class="ml-3 flex items-center text-xs font-medium px-2 py-1 rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        All Online
                                    </span>
                                </template>
                                <template x-if="offlineCount > 0">
                                    <span
                                        class="ml-3 flex items-center text-xs font-medium px-2 py-1 rounded-full bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                        <span x-text="offlineCount"></span> <span class="ml-1">Offline</span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>

                    <!-- System Updates -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center flex-1">
                        <div
                            class="p-3 rounded-full bg-orange-100 dark:bg-orange-900 text-orange-600 dark:text-orange-300 mr-4">
                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">System Updates Pending</p>
                            <template x-if="!hasData">
                                <div class="h-8 w-12 bg-gray-200 dark:bg-gray-700 rounded animate-pulse mt-1"></div>
                            </template>
                            <template x-if="hasData">
                                <div class="flex items-center text-4xl font-bold text-gray-900 dark:text-gray-100">
                                    <span x-text="sysCount"></span>
                                    <span
                                        class="ml-3 flex items-center text-xs font-medium px-2 py-1 rounded-full align-middle text-indigo-600 dark:text-indigo-400 bg-indigo-100 dark:bg-indigo-900">
                                        <span x-text="apiCount"></span> <span class="ml-1">API Updates</span>
                                    </span>
                                </div>
                            </template>
                        </div>
                    </div>



                    <!-- Missing Address -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex items-center flex-1">
                        <div
                            class="p-3 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300 mr-4">
                            <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Missing Addresses</p>
                            <p class="text-4xl font-bold text-gray-900 dark:text-gray-100">
                                {{ $missingAddresses }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function firewallMap() {
                    return {
                        map: null,
                        markers: [],
                        heatLayer: null,
                        status: '',
                        locale: '{{ app()->getLocale() }}',
                        items: [],

                        initMap() {
                            this.items = {!! json_encode($firewalls->map(fn($f) => [
    'name' => $f->name,
    'address' => $f->address ?? '',
    'latitude' => $f->latitude,
    'longitude' => $f->longitude
])) !!};

                            const mapEl = document.getElementById('firewall-map');
                            if (mapEl) mapEl.style.height = '100%';

                            this.loadResources().then(() => {
                                this.renderMap();
                            });
                        },

                        loadResources() {
                            return new Promise((resolve) => {
                                let resourcesLoaded = 0;
                                const totalResources = 3;

                                const checkDone = () => {
                                    resourcesLoaded++;
                                    if (resourcesLoaded === totalResources) resolve();
                                };

                                if (!document.getElementById('leaflet-css')) {
                                    const link = document.createElement('link');
                                    link.id = 'leaflet-css';
                                    link.rel = 'stylesheet';
                                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                    link.onload = checkDone;
                                    document.head.appendChild(link);
                                } else {
                                    checkDone();
                                }

                                if (!window.L) {
                                    const script = document.createElement('script');
                                    script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                    script.onload = () => {
                                        const heatScript = document.createElement('script');
                                        heatScript.src = 'https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js';
                                        heatScript.onload = checkDone;
                                        document.head.appendChild(heatScript);
                                        checkDone();
                                    };
                                    document.head.appendChild(script);
                                } else {
                                    checkDone();
                                    if (!L.heatLayer) {
                                        const heatScript = document.createElement('script');
                                        heatScript.src = 'https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js';
                                        heatScript.onload = checkDone;
                                        document.head.appendChild(heatScript);
                                    } else {
                                        checkDone();
                                    }
                                }
                            });
                        },

                        renderMap() {
                            if (!this.map) {
                                this.map = L.map('firewall-map', { scrollWheelZoom: false }).setView([20, 0], 2);
                                L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}&hl=' + this.locale, {
                                    attribution: '&copy; Google Maps',
                                    maxZoom: 20
                                }).addTo(this.map);
                            } else {
                                this.map.invalidateSize();
                            }

                            this.markers.forEach(m => this.map.removeLayer(m));
                            this.markers = [];
                            if (this.heatLayer) this.map.removeLayer(this.heatLayer);

                            const bounds = [];
                            const heatPoints = [];
                            const geocodeQueue = [];

                            this.items.forEach(c => {
                                const lat = parseFloat(c.latitude);
                                const lng = parseFloat(c.longitude);
                                if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
                                    this.addMarker(lat, lng, c.name, c.address);
                                    bounds.push([lat, lng]);
                                    heatPoints.push([lat, lng, 1.2]); // Balanced Intensity
                                } else if (c.address) {
                                    geocodeQueue.push(c);
                                }
                            });

                            if (heatPoints.length > 0 && L.heatLayer) {
                                this.heatLayer = L.heatLayer(heatPoints, {
                                    radius: 35,
                                    blur: 35,
                                    maxZoom: 12,
                                    minOpacity: 0.3,
                                    gradient: { 0.3: 'indigo', 0.5: 'purple', 0.7: 'orange', 1: 'red' }
                                }).addTo(this.map);
                            }

                            if (bounds.length > 0) {
                                this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
                            }

                            if (geocodeQueue.length > 0) {
                                this.processGeocodeQueue(geocodeQueue, bounds, heatPoints);
                            }
                        },

                        addMarker(lat, lng, name, address) {
                            const marker = L.circleMarker([lat, lng], {
                                radius: 5,
                                fillColor: '#6366f1',
                                color: '#ffffff',
                                weight: 1,
                                opacity: 1,
                                fillOpacity: 0.9
                            })
                                .addTo(this.map)
                                .bindPopup(`<b>${name}</b><br>${address}`);
                            this.markers.push(marker);
                        },

                        async processGeocodeQueue(queue, bounds, heatPoints) {
                            if (queue.length === 0) return;
                            this.status = `Geocoding ${queue.length} addresses...`;

                            for (let i = 0; i < queue.length; i++) {
                                const item = queue[i];
                                this.status = `Geocoding ${i + 1}/${queue.length}: ${item.name}`;

                                try {
                                    const url = `{{ route('users.geocode') }}?address=${encodeURIComponent(item.address)}`;
                                    const response = await fetch(url);
                                    if (response.ok) {
                                        const data = await response.json();
                                        if (data && data.length > 0) {
                                            const lat = parseFloat(data[0].lat);
                                            const lon = parseFloat(data[0].lon);

                                            this.addMarker(lat, lon, item.name, item.address);
                                            bounds.push([lat, lon]);
                                            heatPoints.push([lat, lon, 1.2]);

                                            if (bounds.length > 0) this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });

                                            if (this.heatLayer) this.map.removeLayer(this.heatLayer);
                                            if (L.heatLayer) {
                                                this.heatLayer = L.heatLayer(heatPoints, {
                                                    radius: 35,
                                                    blur: 35,
                                                    maxZoom: 12,
                                                    minOpacity: 0.3,
                                                    gradient: { 0.3: 'indigo', 0.5: 'purple', 0.7: 'orange', 1: 'red' }
                                                }).addTo(this.map);
                                            }
                                        }
                                    }
                                } catch (error) { console.error(error); }
                                await new Promise(r => setTimeout(r, 1000));
                            }
                            this.status = '';
                        }
                    };
                }
            </script>

            @php
                $uniqueCustomers = $firewalls->pluck('company.name')->unique()->sort()->values();

                // Pre-load all firewall status caches in one batch so the firewalls array
                // renders with last-known values instead of '-' placeholders.
                // Prevents sysVersion/apiVersion/status flash on page load.
                $firewallCaches = [];
                foreach ($firewalls as $fw) {
                    $cached = \Illuminate\Support\Facades\Cache::get('firewall_status_' . $fw->id);
                    if ($cached) {
                        // refreshSystemStatus() flattens everything into cache['data'] directly.
                        // product_version, api_version, gateways etc. are all at cache['data'][key].
                        $flat = is_array($cached['data'] ?? null) ? $cached['data'] : [];
                        $firewallCaches[$fw->id] = [
                            'online'               => $cached['online']           ?? null,
                            'api_version'          => $cached['api_version'] ?? $flat['api_version'] ?? null,
                            'product_version'      => $flat['product_version']   ?? $flat['version'] ?? null,
                            'update_available'     => $flat['update_available']   ?? false,
                            'api_update_available' => $flat['api_update_available'] ?? false,
                        ];
                    } else {
                        $firewallCaches[$fw->id] = null;
                    }
                }
            @endphp
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" x-data="{
                search: '',
                statusFilter: 'all',
                customerFilter: 'all',
                sortBy: 'name',
                sortAsc: true,
                firewalls: [],
                customers: {{ json_encode($uniqueCustomers) }},
                deleteModalOpen: false,
                deleteAction: '',
                firewallName: '',
                confirmEmail: '',
                
                init() {
                    this.firewalls = {{ json_encode($firewalls->map(fn($f) => [
    'id'                 => $f->id,
    'name'               => $f->name,
    'status'             => ($firewallCaches[$f->id]['online'] ?? $f->is_online) === true ? 'online'
                            : (($firewallCaches[$f->id]['online'] ?? $f->is_online) === false ? 'offline' : 'unknown'),
    'isOnline'           => $firewallCaches[$f->id]['online'] ?? $f->is_online,
    'url'                => $f->url,
    'displayUrl'         => preg_replace('#^https?://#', '', $f->url),
    'linkUrl'            => \Illuminate\Support\Str::startsWith($f->url, ['http://', 'https://']) ? $f->url : 'https://' . $f->url,
    'address'            => $f->address,
    'sysVersion'         => $firewallCaches[$f->id]['product_version']      ?? '-',
    'sysUpdateAvailable' => $firewallCaches[$f->id]['update_available']      ?? false,
    'apiVersion'         => $firewallCaches[$f->id]['api_version']           ?? '-',
    'apiUpdateAvailable' => $firewallCaches[$f->id]['api_update_available']  ?? false,
    'company_name'       => $f->company->name,
    'company_url'        => route('companies.show', $f->company),
    'dashboard_url'      => route('firewall.dashboard', $f),
    'edit_url'           => route('firewalls.edit', $f),
    'delete_url'         => route('firewalls.destroy', $f),
    'check_status_url'   => route('firewall.check-status', $f),
    'searchData'         => strtolower($f->name . ' ' . $f->company->name . ' ' . $f->url . ' ' . $f->hostname)
])) }};

                    // Listen for updates dispatched by the per-firewall WS listener below
                    window.addEventListener('firewall-updated', (e) => {
                        const idx = this.firewalls.findIndex(f => f.id === e.detail.id);
                        if (idx !== -1) {
                            const f = this.firewalls[idx];
                            f.isOnline           = e.detail.online;
                            f.sysUpdateAvailable = e.detail.sys;
                            f.apiUpdateAvailable = e.detail.api;
                            // e.detail.data is serviceResult (r.data) which may wrap actual fields under .data
                            const s = e.detail.data || {};
                            const sysData = (s.data && typeof s.data === 'object') ? s.data : s;
                            f.sysVersion = sysData.product_version || sysData.version || '-';
                            f.apiVersion = e.detail.apiVersion || '-';
                        }
                    });

                    // If we have unknown statuses (Cache Disabled), trigger a background refresh
                    if (this.firewalls.some(f => f.isOnline === null)) {
                        fetch('{{ route("firewalls.refresh-all") }}', {
                            method: 'POST',
                            headers: { 
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ sync: false }) // Async Mode
                        }).then(r => r.json()).then(data => {
                            console.log('Background Refresh Triggered', data);
                        }).catch(e => console.error(e));
                    }
                },
                
                get filteredRows() {
                    let result = this.firewalls.filter(f => {
                         const matchesSearch = this.search === '' || f.searchData.includes(this.search.toLowerCase());
                         
                         const matchesStatus = this.statusFilter === 'all' 
                              || (this.statusFilter === 'online' && f.isOnline === true)
                              || (this.statusFilter === 'offline' && f.isOnline === false);

                         const matchesCustomer = this.customerFilter === 'all' || f.company_name === this.customerFilter;

                         return matchesSearch && matchesStatus && matchesCustomer;
                    });
                    
                    return result.sort((a, b) => {
                        let valA = a[this.sortBy];
                        let valB = b[this.sortBy];
                        
                        // Handle specific fields
                        if (this.sortBy === 'company') {
                             valA = a.company_name;
                             valB = b.company_name;
                        }

                        if (this.sortBy === 'address') {
                            valA = !!valA;
                            valB = !!valB;
                        }

                        if (typeof valA === 'string') valA = valA.toLowerCase();
                        if (typeof valB === 'string') valB = valB.toLowerCase();
                        
                        if (valA < valB) return this.sortAsc ? -1 : 1;
                        if (valA > valB) return this.sortAsc ? 1 : -1;
                        return 0;
                    });
                },
                
                 openDeleteModal(action, name) {
                    this.deleteAction = action;
                    this.firewallName = name;
                    this.confirmEmail = '';
                    $dispatch('open-modal', 'delete-firewall-modal');
                }
            }">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex flex-col space-y-4 mb-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ __('Managed Firewalls') }}
                            </h3>
                            @if(!auth()->user()->isReadOnly())
                            <a href="{{ route('firewalls.create') }}"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-medium text-sm text-white hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                {{ __('Add Firewall') }}
                            </a>
                            @endif
                        </div>

                        <!-- Toolbar -->
                        <div
                            class="flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg mb-6">

                            <!-- Left Side: Filters & Search (Flex Grow) -->
                            <div class="flex flex-col sm:flex-row gap-4 w-full lg:flex-1">
                                <!-- Search (Expandable) -->
                                <div class="relative w-full sm:w-64 lg:w-auto lg:flex-1">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg"
                                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                        </svg>
                                    </div>
                                    <input type="text" x-model="search" placeholder="Search firewalls..."
                                        class="pl-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                    <button x-show="search.length > 0" @click="search = ''"
                                        class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none"
                                        style="display: none;">
                                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                            viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <!-- Filters Group -->
                                <div class="flex gap-2 w-full sm:w-auto shrink-0">
                                    <!-- Customer Filter -->
                                    <div class="relative w-1/2 sm:w-auto min-w-[160px]"
                                        x-data="{ open: false, filter: '' }">
                                        <button @click="open = !open" type="button"
                                            class="flex items-center justify-between w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                            <span x-text="customerFilter === 'all' ? 'All Customers' : customerFilter"
                                                class="truncate block text-left"></span>
                                            <svg class="h-4 w-4 ml-2 text-gray-500 transform transition-transform duration-200"
                                                :class="{'rotate-180': open}" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round"
                                                    stroke-linejoin="round" />
                                            </svg>
                                        </button>
                                        <div x-show="open" @click.outside="open = false" x-transition
                                            class="absolute z-10 mt-1 w-full sm:w-[200px] bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg shadow-xl max-h-60 overflow-y-auto">
                                            <div
                                                class="p-2 border-b dark:border-gray-700 sticky top-0 bg-white dark:bg-gray-800">
                                                <input x-model="filter" type="text" placeholder="Search..."
                                                    class="w-full text-xs rounded border-gray-300 dark:bg-gray-700 dark:border-gray-600">
                                            </div>
                                            <div @click="customerFilter = 'all'; open = false"
                                                class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm">
                                                All Customers</div>
                                            <template
                                                x-for="c in customers.filter(x => x.toLowerCase().includes(filter.toLowerCase()))"
                                                :key="c">
                                                <div @click="customerFilter = c; open = false"
                                                    class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm truncate"
                                                    x-text="c"></div>
                                            </template>
                                        </div>
                                    </div>

                                    <!-- Status Filter -->
                                    <select x-model="statusFilter"
                                        class="w-1/2 sm:w-auto rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                        <option value="all">All Status</option>
                                        <option value="online">Online</option>
                                        <option value="offline">Offline</option>
                                    </select>

                                    <span
                                        class="text-xs text-gray-500 font-normal self-center whitespace-nowrap sm:ml-2 hidden sm:block"
                                        x-text="'Showing ' + filteredRows.length + ' of ' + firewalls.length + ' firewalls'"></span>
                                </div>
                            </div>

                            <!-- Right Side: Bulk Actions -->
                            @if(!auth()->user()->isReadOnly())
                            <div
                                class="flex gap-2 w-full lg:w-auto items-center border-t lg:border-t-0 lg:border-l lg:pl-4 pt-4 lg:pt-0 border-gray-200 dark:border-gray-600">
                                <span class="text-sm text-gray-500 whitespace-nowrap hidden xl:inline">With
                                    selected:</span>
                                <div class="flex gap-2 w-full">
                                    <select id="bulkActionSelect" name="action" form="bulkForm"
                                        class="block w-full lg:w-48 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300 sm:text-sm">
                                        <option value="">Bulk Actions...</option>
                                        <optgroup label="System">
                                            <option value="reboot">Reboot</option>
                                            <option value="update">System Update</option>
                                            <option value="update_rest_api">Update REST API</option>
                                            <option value="create_package">Install Package</option>
                                        </optgroup>
                                        <optgroup label="Configuration (Add to All)">
                                            <option value="create_alias">Add Alias</option>
                                            <option value="create_nat">Add NAT 1:1 / Port Forward</option>
                                            <option value="create_rule">Add Firewall Rule</option>
                                            <option value="create_ipsec">Add IPSec Tunnel</option>
                                        </optgroup>
                                    </select>
                                    <x-secondary-button type="button" onclick="submitBulkAction()" class="rounded-lg">
                                        Apply
                                    </x-secondary-button>
                                </div>
                            </div>
                            @endif
                        </div>

                        <form id="bulkForm" action="{{ route('firewalls.bulk.action') }}" method="POST">
                            @csrf
                            {{-- Hidden form, inputs will reference it by ID --}}
                        </form>

                        <div class="overflow-x-auto">

                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        @if(!auth()->user()->isReadOnly())
                                        <th class="px-6 py-3 text-left">
                                            <input type="checkbox" id="selectAll" onclick="toggleSelectAll()">
                                        </th>
                                        @endif
                                        @if(auth()->user()->isGlobalAdmin())
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                                @click="sortBy = 'company'; sortAsc = !sortAsc">
                                                <div class="flex items-center gap-1">
                                                    Company
                                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                        :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'company', 'rotate-180': sortBy === 'company' && !sortAsc }"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </div>
                                            </th>
                                        @endif
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                            @click="sortBy = 'name'; sortAsc = !sortAsc">
                                            <div class="flex items-center gap-1">
                                                Name
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'name', 'rotate-180': sortBy === 'name' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                            @click="sortBy = 'displayUrl'; sortAsc = !sortAsc">
                                            <div class="flex items-center gap-1">
                                                Host
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'displayUrl', 'rotate-180': sortBy === 'displayUrl' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                            @click="sortBy = 'address'; sortAsc = !sortAsc">
                                            <div class="flex items-center justify-center gap-1">
                                                Address
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'address', 'rotate-180': sortBy === 'address' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                            @click="sortBy = 'sysUpdateAvailable'; sortAsc = !sortAsc">
                                            <div class="flex items-center gap-1">
                                                System
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'sysUpdateAvailable', 'rotate-180': sortBy === 'sysUpdateAvailable' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                            @click="sortBy = 'apiUpdateAvailable'; sortAsc = !sortAsc">
                                            <div class="flex items-center gap-1">
                                                API
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'apiUpdateAvailable', 'rotate-180': sortBy === 'apiUpdateAvailable' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>

                                        @if(!auth()->user()->isReadOnly())
                                        <th scope="col"
                                            class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions
                                        </th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <template x-for="firewall in filteredRows" :key="firewall.id">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <input type="checkbox" name="firewall_ids[]" :value="firewall.id"
                                                    form="bulkForm" class="firewall-checkbox">
                                            </td>
                                            @endif
                                            @if(auth()->user()->isGlobalAdmin())
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <a :href="firewall.company_url"
                                                        class="text-indigo-600 hover:text-indigo-900 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                                        x-text="firewall.company_name">
                                                    </a>
                                                </td>
                                            @endif
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a :href="firewall.dashboard_url"
                                                    class="font-medium text-indigo-600 hover:text-indigo-900 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                                    x-text="firewall.name">
                                                </a>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <!-- Online/Offline Badge -->
                                                <template x-if="firewall.isOnline !== null">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                        :class="{
                                                            'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200': firewall.isOnline === true,
                                                            'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200': firewall.isOnline === false
                                                        }">
                                                        <svg class="-ml-0.5 mr-1.5 h-2 w-2" :class="{
                                                                'text-green-400': firewall.isOnline === true,
                                                                'text-red-400': firewall.isOnline === false
                                                            }" fill="currentColor" viewBox="0 0 8 8">
                                                            <circle cx="4" cy="4" r="3" />
                                                        </svg>
                                                        <span x-text="firewall.isOnline === true ? 'Online' : 'Offline'"></span>
                                                    </span>
                                                </template>
                                                <!-- Skeleton Loading State (Null) -->
                                                <template x-if="firewall.isOnline === null">
                                                    <span class="inline-block w-16 h-5 bg-gray-200 dark:bg-gray-700 rounded-full animate-pulse"></span>
                                                </template>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <a :href="firewall.linkUrl" target="_blank" rel="noopener noreferrer"
                                                    class="text-indigo-600 hover:text-indigo-900 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                                    x-text="firewall.displayUrl">
                                                </a>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                                <template x-if="firewall.address">
                                                    <a :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(firewall.address)"
                                                        target="_blank"
                                                        class="has-tooltip inline-flex items-center justify-center text-indigo-600 hover:text-indigo-900 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                                        :data-tippy-content="firewall.address">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                            </path>
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z">
                                                            </path>
                                                        </svg>
                                                    </a>
                                                </template>
                                                <template x-if="!firewall.address">
                                                    <svg class="w-5 h-5 text-red-300 dark:text-red-700 mx-auto"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                        title="No Address Provided">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </template>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center space-x-2">
                                                    <span x-text="firewall.sysVersion"></span>
                                                    <template x-if="firewall.sysUpdateAvailable">
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"
                                                            title="Update Available">Update</span>
                                                    </template>
                                                    <template
                                                        x-if="!firewall.sysUpdateAvailable && firewall.sysVersion !== '-'">
                                                        <svg class="w-5 h-5 text-green-500" fill="none"
                                                            stroke="currentColor" viewBox="0 0 24 24"
                                                            title="Up to Date">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                        </svg>
                                                    </template>
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center space-x-2">
                                                    <!-- Error State: Long string (likely an error message) -->
                                                    <template
                                                        x-if="firewall.apiVersion && firewall.apiVersion !== '-' && firewall.apiVersion.length > 20">
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 has-tooltip"
                                                            :data-tippy-content="'Error Details: ' + firewall.apiVersion">
                                                            Error
                                                        </span>
                                                    </template>

                                                    <!-- Valid Version State (including '-' or empty) -->
                                                    <template
                                                        x-if="!firewall.apiVersion || firewall.apiVersion === '-' || firewall.apiVersion.length <= 20">
                                                        <div class="flex items-center space-x-2">
                                                            <span x-text="firewall.apiVersion || '-'"></span>

                                                            <!-- Update Badge -->
                                                            <template x-if="firewall.apiUpdateAvailable">
                                                                <span
                                                                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200"
                                                                    title="Update Available">Update</span>
                                                            </template>

                                                            <!-- Checkmark (Up to Date) -->
                                                            <template
                                                                x-if="!firewall.apiUpdateAvailable && firewall.apiVersion && firewall.apiVersion !== '-' && firewall.apiVersion !== 'N/A'">
                                                                <svg class="w-5 h-5 text-green-500" fill="none"
                                                                    stroke="currentColor" viewBox="0 0 24 24"
                                                                    title="Up to Date">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                                </svg>
                                                            </template>
                                                        </div>
                                                    </template>
                                                </div>
                                            </td>

                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex items-center justify-end space-x-3">
                                                    <a :href="firewall.edit_url"
                                                        class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                        Edit
                                                    </a>
                                                    <button type="button"
                                                        @click="openDeleteModal(firewall.delete_url, firewall.name)"
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">
                                                        Delete
                                                    </button>
                                                </div>
                                            </td>
                                            @endif
                                        </tr>
                                    </template>
                                    <tr x-show="filteredRows.length === 0">
                                        <td colspan="{{ (auth()->user()->isGlobalAdmin() ? 9 : 8) - (auth()->user()->isReadOnly() ? 2 : 0) }}"
                                            class="px-6 py-4 text-center text-gray-500">
                                            No firewalls found. <a href="{{ route('firewalls.create') }}"
                                                class="text-blue-600 hover:underline">Add one now</a>.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <x-modal name="delete-firewall-modal" :show="false" focusable>
                                <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                    <div class="sm:flex sm:items-start">
                                        <div
                                            class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/20 sm:mx-0 sm:h-10 sm:w-10">
                                            <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none"
                                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                            </svg>
                                        </div>
                                        <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left w-full">
                                            <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-gray-100">
                                                {{ __('Delete Firewall') }}
                                            </h3>
                                            <div class="mt-2">
                                                <p class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ __('Are you sure you want to delete the firewall ') }} <span
                                                        class="font-bold text-gray-900 dark:text-gray-100"
                                                        x-text="firewallName"></span>?
                                                    {{ __('This action cannot be undone.') }}
                                                </p>
                                                <p class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                                                    {{ __('Please type your email address to confirm:') }} <span
                                                        class="font-mono font-bold">{{ auth()->user()->email }}</span>
                                                </p>

                                                <form :action="deleteAction" method="POST" id="delete-firewall-form"
                                                    class="mt-4">
                                                    @csrf
                                                    @method('DELETE')
                                                    <x-input-label for="confirm_email"
                                                        value="{{ __('Email Address') }}" class="sr-only" />

                                                    <x-text-input id="confirm_email" name="confirm_email" type="email"
                                                        class="block w-full sm:w-3/4"
                                                        placeholder="{{ __('Email Address') }}" x-model="confirmEmail"
                                                        @keyup.enter="if(confirmEmail === '{{ auth()->user()->email }}') document.getElementById('delete-firewall-form').submit()" />
                                                </form>
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
                            </x-modal>

                            {{-- Re-implement Delete Buttons properly since they were inside the form --}}
                            {{-- Actually, delete forms need to be outside. --}}
                            {{-- I will fix the wrapping in the next step or adjust now. --}}
                            {{-- Strategy: Use JS for Bulk submit, don't wrap table in form. --}}

                        </div>
                    </div>
                </div>
            </div>

            <script>
                document.addEventListener('alpine:init', () => {
                    Alpine.data('firewallStats', (allIds) => ({
                        stats: {},
                        loading: true,
                        search: '',
                        statusFilter: 'all',
                        customerFilter: 'all',
                        get totalCount() { return allIds.length; },
                        get offlineCount() { return Object.values(this.stats).filter(s => !s.online).length; },
                        get sysCount() { return Object.values(this.stats).filter(s => s.sys).length; },
                        get apiCount() { return Object.values(this.stats).filter(s => s.api).length; },
                        get hasData() { return Object.keys(this.stats).length > 0; },

                        init() {
                            // Smart Trigger: Wait for WS connection to avoid "Simultaneous Batch" (Sync Mode) 
                            // and ensure we don't miss events (Race Condition).
                            const checkAndTrigger = () => {
                                if (window.Echo && window.Echo.connector && window.Echo.connector.pusher) {
                                    const state = window.Echo.connector.pusher.connection.state;

                                    // If connected, go!
                                    if (state === 'connected') {
                                        this.triggerUpdate();
                                        return;
                                    }

                                    // If connecting, wait for it...
                                    if (state === 'connecting' || state === 'initialized') {
                                        const onConnect = () => {
                                            this.triggerUpdate();
                                            window.Echo.connector.pusher.connection.unbind('connected', onConnect);
                                        };
                                        window.Echo.connector.pusher.connection.bind('connected', onConnect);
                                        // Fallback if connection takes too long
                                        setTimeout(() => {
                                            if (this.loading) this.triggerUpdate(); // Will force sync if still not connected
                                        }, 3000);
                                        return;
                                    }
                                }

                                // If disconnected or no Echo, just trigger (will use Sync Fallback)
                                this.triggerUpdate();
                            };

                            checkAndTrigger();

                            // 3. Listen for WebSocket updates from the jobs
                            window.addEventListener('firewall-updated', (e) => {
                                const { id, online, sys, api } = e.detail;
                                this.stats[id] = { online, sys, api };
                                if (Object.keys(this.stats).length >= this.totalCount) {
                                    this.loading = false;
                                }
                            });

                            // Safety fallback
                            setTimeout(() => { this.loading = false; }, 10000);

                            // Dynamic Intervals from Settings
                            this.realtimeMs = {{ ($settings['realtime_interval'] ?? 10) * 1000 }};
                            this.fallbackMs = {{ ($settings['fallback_interval'] ?? 30) * 1000 }};
                            this.timer = null;

                            this.startIntervalManager();
                        },

                        startIntervalManager() {
                            if (this.timer) clearTimeout(this.timer);

                            const run = () => {
                                // triggerUpdate() dispatches CheckFirewallStatusJob per firewall.
                                // It is currently the only mechanism keeping the pfSense cache fresh.
                                // Must always run regardless of WS state until Phase 1.5 adds a
                                // true backend scheduler. Step 4 deduplication prevents WS floods.
                                this.triggerUpdate();
                                this.timer = setTimeout(run, this.fallbackMs);
                            };

                            // Always schedule at fallbackMs — realtimeMs is retired for this loop.
                            this.timer = setTimeout(run, this.fallbackMs);
                        },

                        async triggerUpdate() {
                            const tokenEl = document.querySelector('meta[name="csrf-token"]');
                            const token = tokenEl ? tokenEl.getAttribute('content') : '';

                            // Intelligent Failover:
                            // Only force synchronous check if WebSocket is EXPLICITLY disconnected/dead.
                            // If it's 'connecting', we trust the backend jobs will broadcast eventually.
                            let url = '{{ route("firewalls.refresh-all") }}';

                            const state = window.Echo?.connector?.pusher?.connection?.state;
                            let isExplicitlyDisconnected = (state === 'disconnected' || state === 'failed' || state === 'unavailable');
                            if (!window.Echo) isExplicitlyDisconnected = true;

                            if (isExplicitlyDisconnected) {
                                // console.warn('WebSocket disconnected. Forcing synchronous update.');
                                url += '?sync=true';
                            }

                            try {
                                const res = await fetch(url, {
                                    method: 'POST',
                                    headers: {
                                        'Content-Type': 'application/json',
                                        'X-CSRF-TOKEN': token
                                    },
                                    body: JSON.stringify({ ids: allIds })
                                });
                                const data = await res.json();

                                // If we get immediate results (Sync Mode), use them to update UI
                                if (data.results) {
                                    Object.entries(data.results).forEach(([id, r]) => {
                                        // r is the Wrapper: { online: bool, data: ServiceResult, api_version: ... }
                                        let serviceResult = r.data || {};
                                        let realData = (serviceResult.data && typeof serviceResult.data === 'object') ? serviceResult.data : serviceResult;

                                        const sysUp = (realData.update_available === true);
                                        const apiUp = (realData.api_update_available === true);

                                        window.dispatchEvent(new CustomEvent('firewall-updated', {
                                            detail: {
                                                id: parseInt(id),
                                                online: r.online,
                                                sys: sysUp,
                                                api: apiUp,
                                                data: serviceResult,
                                                apiVersion: r.api_version || realData.api_version || '-'
                                            }
                                        }));
                                    });
                                }
                            } catch (err) {
                                console.error('Update trigger failed:', err);
                            }
                        }
                    }));

                    Alpine.data('firewallRow', (id, statusData, checkPath, searchData, companyName) => ({
                        id: id,
                        // statusData is the Wrapper { online: bool, data: ServiceResult }
                        isOnline: statusData && statusData.online,

                        // Helper to extract nested version
                        sysVersion: (function () {
                            if (!statusData || !statusData.data || !statusData.data.data) return '-';
                            return statusData.data.data.product_version || statusData.data.data.version || '-';
                        })(),

                        apiVersion: (statusData && statusData.api_version) ||
                            (statusData && statusData.data && statusData.data.api_version) ||
                            '-',

                        sysUpdateAvailable: (statusData && statusData.data && statusData.data.data && statusData.data.data.update_available === true),
                        apiUpdateAvailable: (statusData && statusData.data && statusData.data.data && statusData.data.data.api_update_available === true),

                        // Only show loading skeleton if we have NO data at all
                        loading: !statusData,
                        searchData: searchData,
                        companyName: companyName,

                        checkVisibility(search, statusFilter, customerFilter) {
                            search = (search || '').toLowerCase();
                            const matchesSearch = search === '' || this.searchData.includes(search);

                            const matchesStatus = statusFilter === 'all'
                                || (statusFilter === 'online' && this.isOnline)
                                || (statusFilter === 'offline' && !this.isOnline);

                            const matchesCustomer = customerFilter === 'all' || this.companyName === customerFilter;

                            return matchesSearch && matchesStatus && matchesCustomer;
                        },

                        init() {
                            // 1. Universal Update Listener (Handles both HTTP/Sync and internal events)
                            // This allows the row to update when the parent 'triggerUpdate' fetches data via HTTP
                            window.addEventListener('firewall-updated', (e) => {
                                if (e.detail.id === this.id) {
                                    this.isOnline = e.detail.online;
                                    this.loading  = false;

                                    // e.detail.data is the flat e.status object — no .data nesting.
                                    const s = e.detail.data || {};
                                    this.sysVersion         = s.product_version || '-';
                                    this.apiVersion         = e.detail.apiVersion || '-';
                                    this.sysUpdateAvailable = e.detail.sys  === true;
                                    this.apiUpdateAvailable = e.detail.api  === true;
                                }
                            });

                            // 2. WebSocket Listener
                            if (window.Echo) {
                                window.Echo.private('firewall.' + this.id)
                                    .listen('.firewall.status.update', (e) => {
                                        // Flat payload — access e.status.* directly, no .data nesting.
                                        const s = e.status || {};
                                        this.isOnline           = s.online ?? true;
                                        this.loading            = false;
                                        this.sysVersion         = s.product_version || '-';
                                        this.apiVersion         = s.api_version || '-';
                                        this.sysUpdateAvailable = s.update_available     === true;
                                        this.apiUpdateAvailable = s.api_update_available === true;

                                        // Dispatch to parent (firewallStats) and main Alpine component
                                        window.dispatchEvent(new CustomEvent('firewall-updated', {
                                            detail: {
                                                id:         this.id,
                                                online:     this.isOnline,
                                                sys:        this.sysUpdateAvailable,
                                                api:        this.apiUpdateAvailable,
                                                data:       s,          // flat — callers read s.product_version etc.
                                                apiVersion: this.apiVersion
                                            }
                                        }));
                                    });
                            }

                            // 4. Fallback Watchdog
                            setTimeout(() => {
                                if (this.loading) {
                                    // console.warn('Firewall ' + this.id + ' stuck loading.');
                                }
                            }, 15000);
                        },

                        checkStatus(url) {
                            // Manual refresh fallback

                            fetch(url + '?t=' + Date.now())
                                .then(res => res.json())
                                .then(data => {
                                    this.isOnline = data.online;
                                    // .. logic here is similar but likely unused if we rely on triggerUpdate()
                                    // keeping minimal fallback
                                    this.loading = false;
                                })
                                .catch(() => { this.loading = false; });
                        }
                    }));
                });

                function toggleSelectAll() {
                    const checkboxes = document.querySelectorAll('.firewall-checkbox');
                    const selectAll = document.getElementById('selectAll');
                    checkboxes.forEach(cb => cb.checked = selectAll.checked);
                }

                function submitBulkAction() {
                    const action = document.getElementById('bulkActionSelect').value;
                    if (!action) {
                        alert('Please select an action.');
                        return;
                    }

                    const checkboxes = document.querySelectorAll('.firewall-checkbox:checked');
                    const ids = Array.from(checkboxes).map(cb => cb.value);

                    if (ids.length === 0) {
                        alert('Please select at least one firewall.');
                        return;
                    }

                    if (action.startsWith('create_')) {
                        // Redirect to create page
                        const type = action.replace('create_', '');
                        const url = `{{ url('/firewalls/bulk/create') }}/${type}`;
                        // Append IDs
                        const queryString = ids.map(id => `firewall_ids[]=${id}`).join('&');
                        window.location.href = `${url}?${queryString}`;
                    } else {
                        // POST action (reboot/update)
                        if (action === 'reboot') {
                            if (!confirm('WARNING: Are you sure you want to REBOOT the selected firewalls? usage of this command will cause network downtime.')) {
                                return;
                            }
                        } else if (!confirm('Are you sure you want to perform this action on selected firewalls?')) {
                            return;
                        }
                        const form = document.getElementById('bulkForm');
                        form.submit();
                    }
                }
            </script>
</x-app-layout>