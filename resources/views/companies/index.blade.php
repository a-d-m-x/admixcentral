<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Companies') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                {{-- Map Section (2/3 width) --}}
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg h-full"
                    x-data="companyMap()" x-init="initMap()">
                    <div class="p-2 text-sm text-gray-600 bg-gray-100 dark:bg-gray-700 dark:text-gray-300"
                        x-show="status" x-text="status"></div>
                    <div id="company-map" style="height: 100%; min-height: 400px; width: 100%; z-index: 0;"></div>
                </div>

                {{-- Stats Widgets (1/3 width, stacked) --}}
                <div class="flex flex-col gap-4">
                    <!-- Total Companies -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex justify-between items-center flex-1">
                        <div>
                            <div
                                class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Total Companies</div>
                            <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}
                            </div>
                        </div>
                        <div
                            class="p-3 rounded-full bg-indigo-100 dark:bg-indigo-900/20 text-indigo-600 dark:text-indigo-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4">
                                </path>
                            </svg>
                        </div>
                    </div>

                    <!-- Without Firewalls -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex justify-between items-center flex-1">
                        <div>
                            <div
                                class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Without Firewalls</div>
                            <div class="mt-2 text-3xl font-bold text-red-600 dark:text-red-400">
                                {{ $stats['no_firewalls'] }}
                            </div>
                        </div>
                        <div class="p-3 rounded-full bg-red-100 dark:bg-red-900/20 text-red-600 dark:text-red-400">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z">
                                </path>
                            </svg>
                        </div>
                    </div>

                    <!-- Without Address -->
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex justify-between items-center flex-1">
                        <div>
                            <div
                                class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Missing Addresses</div>
                            <div class="mt-2 text-3xl font-bold text-gray-700 dark:text-gray-200">
                                {{ $stats['no_address'] }}
                            </div>
                        </div>
                        <div class="p-3 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                </path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>

            <script>
                function companyMap() {
                    return {
                        map: null,
                        markers: [],
                        heatLayer: null,
                        status: '',
                        locale: '{{ app()->getLocale() }}',
                        companies: [], // Will require hydration from the other component or global variable if not passed directly. 
                        // However, looking at the code structure, there is a separate x-data below for the list.
                        // We need to access the companies list. 
                        // Simplest way for now: Hydrate it here directly from PHP as well.

                        initMap() {
                            // Hydrate companies data directly for the map
                            this.companies = {!! json_encode($companies->map(fn($c) => [
    'name' => $c->name,
    'address' => $c->address ?? '',
    'latitude' => $c->latitude,
    'longitude' => $c->longitude
])) !!};

                            console.log('Initializing map with locale:', this.locale);

                            const mapEl = document.getElementById('company-map');
                            if (mapEl) mapEl.style.height = '400px';

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
                            const mapEl = document.getElementById('company-map');
                            if (!mapEl) return;
                            mapEl.style.height = '400px';

                            if (!this.map) {
                                this.map = L.map('company-map', { scrollWheelZoom: false }).setView([20, 0], 2);

                                L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}&hl=' + this.locale, {
                                    attribution: '&copy; Google Maps',
                                    maxZoom: 20
                                }).addTo(this.map);
                            } else {
                                this.map.invalidateSize();
                            }

                            this.markers.forEach(m => this.map.removeLayer(m));
                            this.markers = [];
                            if (this.heatLayer) {
                                this.map.removeLayer(this.heatLayer);
                            }

                            const bounds = [];
                            const heatPoints = [];
                            const geocodeQueue = [];

                            this.companies.forEach(c => {
                                const lat = parseFloat(c.latitude);
                                const lng = parseFloat(c.longitude);
                                if (!isNaN(lat) && !isNaN(lng) && (lat !== 0 || lng !== 0)) {
                                    this.addMarker(lat, lng, c.name, c.address);
                                    bounds.push([lat, lng]);
                                    // Balanced intensity
                                    heatPoints.push([lat, lng, 1.2]);
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
                            } else {
                                this.status = '';
                            }
                        },

                        addMarker(lat, lng, name, address) {
                            const marker = L.circleMarker([lat, lng], {
                                radius: 5,
                                fillColor: '#6366f1', // Indigo 500
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

                            console.log(`Attempting to geocode ${queue.length} addresses via proxy...`);
                            this.status = `Geocoding ${queue.length} addresses...`;

                            for (let i = 0; i < queue.length; i++) {
                                const company = queue[i];
                                this.status = `Geocoding ${i + 1}/${queue.length}: ${company.name}`;

                                try {
                                    const url = `{{ route('users.geocode') }}?address=${encodeURIComponent(company.address)}`;

                                    const response = await fetch(url);
                                    if (!response.ok) {
                                        console.warn(`Geocode failed: ${response.status}`);
                                        if (response.status === 404) console.warn('Route not found (check route:clear)');
                                    } else {
                                        const data = await response.json();

                                        if (data && data.length > 0) {
                                            const lat = parseFloat(data[0].lat);
                                            const lon = parseFloat(data[0].lon);

                                            this.addMarker(lat, lon, company.name, company.address);
                                            bounds.push([lat, lon]);

                                            heatPoints.push([lat, lon, 1.2]);

                                            if (bounds.length > 0) {
                                                this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 15 });
                                            }

                                            if (this.heatLayer) {
                                                this.map.removeLayer(this.heatLayer);
                                            }
                                            if (L.heatLayer) {
                                                this.heatLayer = L.heatLayer(heatPoints, {
                                                    radius: 35,
                                                    blur: 35,
                                                    maxZoom: 12,
                                                    minOpacity: 0.3,
                                                    gradient: { 0.3: 'indigo', 0.5: 'purple', 0.7: 'orange', 1: 'red' }
                                                }).addTo(this.map);
                                            }
                                        } else {
                                            console.warn(`Could not geocode address: ${company.address}`);
                                        }
                                    }
                                } catch (error) {
                                    console.error('Geocoding error:', error);
                                }

                                await new Promise(r => setTimeout(r, 1000));
                            }
                            this.status = 'Geocoding complete.';
                            setTimeout(() => this.status = '', 5000);
                        }
                    };
                }
            </script>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" x-data="{
                search: '',
                sortBy: 'name',
                sortAsc: true,
                companies: [],
                get filteredRows() {
                    let result = this.companies.filter(c => {
                        return this.search === '' || c.name.toLowerCase().includes(this.search.toLowerCase());
                    });
                    
                    return result.sort((a, b) => {
                        let valA = a[this.sortBy];
                        let valB = b[this.sortBy];

                        // Special handling for address to sort by presence
                        if (this.sortBy === 'address') {
                            valA = !!valA;
                            valB = !!valB;
                        }
                        
                        // Handle strings case-insensitive
                        if (typeof valA === 'string') valA = valA.toLowerCase();
                        if (typeof valB === 'string') valB = valB.toLowerCase();
                        
                        if (valA < valB) return this.sortAsc ? -1 : 1;
                        if (valA > valB) return this.sortAsc ? 1 : -1;
                        return 0;
                    });
                }
            }" x-init="
                // Hydrate data from PHP
                companies = {{ json_encode($companies->map(fn($c) => [
    'id' => $c->id,
    'name' => $c->name,
    'description' => $c->description ?? '',
    'address' => $c->address ?? '',
    'latitude' => $c->latitude,
    'longitude' => $c->longitude,
    'users_count' => $c->users_count,
    'admins_count' => $c->admins_count,
    'firewalls_count' => $c->firewalls_count,
    'showUrl' => route('companies.show', $c),
    'editUrl' => route('companies.edit', $c),
    'deleteUrl' => route('companies.destroy', $c)
])) }};
            ">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex flex-col space-y-4 mb-6">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                {{ __('Managed Companies') }}
                            </h3>
                            @if(!auth()->user()->isReadOnly())
                            <a href="{{ route('companies.create') }}"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-medium text-sm text-white hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Company
                            </a>
                            @endif
                        </div>

                        <!-- Toolbar -->
                        <div
                            class="flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg mb-6">
                            <!-- Search -->
                            <div class="relative w-full sm:w-64 lg:w-auto lg:flex-1">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                    </svg>
                                </div>
                                <input type="text" x-model="search" placeholder="Search companies..."
                                    class="pl-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                    @input="filteredRows">
                                <button x-show="search.length > 0" @click="search = ''; filteredRows"
                                    class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none"
                                    style="display: none;">
                                    <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </div>

                            <!-- Count -->
                            <div class="text-sm text-gray-500 dark:text-gray-400 self-center">
                                Showing <span class="font-medium" x-text="filteredRows.length"></span> of <span
                                    class="font-medium" x-text="companies.length"></span> companies
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
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
                                            @click="sortBy = 'users'; sortAsc = !sortAsc">
                                            <div class="flex items-center gap-1">
                                                Users
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'users', 'rotate-180': sortBy === 'users' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                            @click="sortBy = 'admins'; sortAsc = !sortAsc">
                                            <div class="flex items-center gap-1">
                                                Admins
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'admins', 'rotate-180': sortBy === 'admins' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                            @click="sortBy = 'firewalls'; sortAsc = !sortAsc">
                                            <div class="flex items-center gap-1">
                                                Firewalls
                                                <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                    :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'firewalls', 'rotate-180': sortBy === 'firewalls' && !sortAsc }"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                </svg>
                                            </div>
                                        </th>
                                        <th scope="col" class="px-6 py-3 w-12"></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <template x-for="company in filteredRows" :key="company.id">
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <a :href="company.showUrl"
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium"
                                                    x-text="company.name">
                                                </a>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500 dark:text-gray-400">
                                                <template x-if="company.address">
                                                    <a :href="'https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(company.address)"
                                                        target="_blank"
                                                        class="has-tooltip inline-flex items-center justify-center text-indigo-600 hover:text-indigo-900 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                                        :data-tippy-content="company.address">
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
                                                <template x-if="!company.address">
                                                    <svg class="w-5 h-5 text-red-300 dark:text-red-700 mx-auto has-tooltip"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                                        data-tippy-content="No Address Provided">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                                    </svg>
                                                </template>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center gap-1">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z">
                                                        </path>
                                                    </svg>
                                                    <span x-text="company.users_count"></span>
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center gap-1">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z">
                                                        </path>
                                                    </svg>
                                                    <span x-text="company.admins_count"></span>
                                                </div>
                                            </td>
                                            <td
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center gap-1">
                                                    <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z">
                                                        </path>
                                                    </svg>
                                                    <span x-text="company.firewalls_count"></span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                @if(!auth()->user()->isReadOnly())
                                                <div class="relative flex justify-end" x-data="{ open: false, dropTop: 0, dropRight: 0 }" @click.away="open = false" @scroll.window.capture="open = false">
                                                    <button @click="const r = $event.currentTarget.getBoundingClientRect(); dropTop = r.bottom + 4; dropRight = window.innerWidth - r.right; open = !open"
                                                        class="p-1.5 rounded-md text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors focus:outline-none"
                                                        aria-label="Row actions">
                                                        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M10 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4zm0 6a2 2 0 110-4 2 2 0 010 4z"/>
                                                        </svg>
                                                    </button>
                                                    <div x-show="open" x-transition
                                                        :style="'top:' + dropTop + 'px;right:' + dropRight + 'px;'"
                                                        class="fixed w-36 bg-white dark:bg-gray-800 rounded-lg shadow-lg border border-gray-200 dark:border-gray-700 z-[9999] py-1">
                                                        <a :href="company.editUrl"
                                                            class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700">
                                                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                                            Edit
                                                        </a>
                                                        <button type="button"
                                                            @click="open = false; Swal.fire({ title: 'Delete Company?', text: 'This will permanently delete all associated users and firewalls.', icon: 'warning', showCancelButton: true, confirmButtonColor: '#dc2626', cancelButtonColor: '#6b7280', confirmButtonText: 'Yes, delete it!' }).then(r => { if (r.isConfirmed) { let f = document.createElement('form'); f.method = 'POST'; f.action = company.deleteUrl; f.innerHTML = '<input type=hidden name=_token value={{ csrf_token() }}><input type=hidden name=_method value=DELETE>'; document.body.appendChild(f); f.submit(); } })"
                                                            class="flex items-center gap-2 w-full px-4 py-2 text-sm text-red-600 dark:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20">
                                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                                            Delete
                                                        </button>
                                                    </div>
                                                </div>
                                                @endif
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="filteredRows.length === 0">
                                        <td colspan="6" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No companies found.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</x-app-layout>