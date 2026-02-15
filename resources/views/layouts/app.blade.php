<!DOCTYPE html>
@php
    $settings = \App\Models\SystemSetting::pluck('value', 'key')->toArray();
    $theme = $settings['theme'] ?? 'light';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    class="{{ $theme === 'dark' ? 'dark' : '' }} h-full bg-gray-100 dark:bg-gray-900 overflow-hidden">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Favicon -->
    @php
        $favicon = $settings['icon_path'] ?? ($settings['favicon_path'] ?? ($settings['logo_path'] ?? asset('favicon.ico')));
    @endphp
    <link rel="icon" href="{{ $favicon }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    <script>
        const protocol = window.location.protocol;
        const isSecure = protocol === 'https:';
        window.AdmixConfig = {
            reverb: {
                key: "{{ env('VITE_REVERB_APP_KEY') }}",
                host: window.location.hostname,
                port: window.location.port || (isSecure ? 443 : 80),
                scheme: isSecure ? 'https' : 'http'
            }
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        [x-cloak] {
            display: none !important;
        }

        .form-input-reset {
            border: none !important;
            outline: none !important;
            box-shadow: none !important;
            background-color: transparent !important;
        }
    </style>
    <link rel="manifest" href="{{ route('manifest') }}">
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function () {
                navigator.serviceWorker.register('/sw.js').then(function (registration) {
                    console.log('ServiceWorker registration successful with scope: ', registration.scope);
                }, function (err) {
                    console.log('ServiceWorker registration failed: ', err);
                });
            });
        }
    </script>
</head>

<body class="font-sans antialiased h-full overflow-hidden bg-gray-100 dark:bg-gray-900 flex flex-col"
    x-data="{ sidebarOpen: false, collapsed: window.innerWidth < 1536 }"
    @resize.window="collapsed = window.innerWidth < 1536">
    <!-- Global System Update Listener -->
    @if(auth()->user()?->isGlobalAdmin())
        <div x-data="systemUpdateListener()" x-cloak class="z-50 relative shrink-0">
            <!-- Unified Update Bar -->
            <!-- Notification Bar -->
            <div x-show="(updateAvailable || isInstalling || updateComplete) && !dismissedSession" style="display: none;"
                x-transition:enter="transform ease-out duration-300 transition"
                x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
                x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0" :class="{
                                                            'bg-blue-600': updateAvailable && !isInstalling && !updateComplete,
                                                            'bg-blue-500': isInstalling,
                                                            'bg-green-600': updateComplete
                                                        }">
                <div class="max-w-7xl mx-auto py-3 px-3 sm:px-6 lg:px-8">
                    <div class="flex items-center justify-between flex-wrap">
                        <div class="w-0 flex-1 flex items-center">
                            <span class="flex p-2 rounded-lg" :class="{
                                                                        'bg-blue-800': updateAvailable && !isInstalling && !updateComplete,
                                                                        'bg-blue-600': isInstalling,
                                                                        'bg-green-800': updateComplete
                                                                    }">
                                <!-- Icon: Update Available -->
                                <template x-if="updateAvailable && !isInstalling && !updateComplete">
                                    <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                </template>

                                <!-- Icon: Installing (Spinner) -->
                                <template x-if="isInstalling">
                                    <svg class="animate-spin h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </template>

                                <!-- Icon: Complete -->
                                <template x-if="updateComplete">
                                    <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                        viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </template>
                            </span>

                            <p class="ml-3 font-medium text-white truncate">
                                <span x-show="updateAvailable && !isInstalling && !updateComplete">
                                    A new update is available (<span x-text="availableVersion"></span>).
                                </span>
                                <span x-show="isInstalling">
                                    Installation in progress. Please wait...
                                </span>
                                <span x-show="updateComplete">
                                    Update complete. Reload to apply changes.
                                </span>
                            </p>
                        </div>

                        <!-- Actions -->
                        <div class="order-3 mt-2 flex-shrink-0 w-full sm:order-2 sm:mt-0 sm:w-auto">
                            <!-- Button: Update Now -->
                            <button x-show="updateAvailable && !isInstalling && !updateComplete" type="button"
                                @click="startUpdate"
                                class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-blue-600 bg-white hover:bg-blue-50">
                                Update Now
                            </button>

                            <!-- Button: Reload Now -->
                            <button x-show="updateComplete" type="button" @click="reloadPage"
                                class="flex items-center justify-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-green-600 bg-white hover:bg-green-50">
                                Reload Now
                            </button>
                        </div>

                        <!-- Dismiss (Only when Available) -->
                        <div x-show="updateAvailable && !isInstalling && !updateComplete"
                            class="order-2 flex-shrink-0 sm:order-3 sm:ml-3">
                            <button type="button" @click="dismissUpdate()"
                                class="-mr-1 flex p-2 rounded-md hover:bg-blue-500 focus:outline-none focus:ring-2 focus:ring-white sm:-mr-2">
                                <span class="sr-only">Dismiss</span>
                                <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Mobile Header -->
    <div
        class="md:hidden flex items-center justify-between h-16 bg-gray-900 border-b border-gray-700 px-4 z-40 relative">
        <a href="{{ route('dashboard') }}">
            @if(isset($settings['logo_path']))
                <img src="{{ $settings['logo_path'] }}" class="block h-8 w-auto" alt="Logo">
            @else
                <img src="{{ asset('images/logo.png') }}" class="block h-8 w-auto" alt="Logo">
            @endif
        </a>
        <button @click="sidebarOpen = !sidebarOpen" class="text-gray-400 hover:text-white focus:outline-none">
            <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
        </button>
    </div>

    <div class="flex-1 flex bg-gray-100 dark:bg-gray-900 overflow-hidden">
        @include('layouts.sidebar')

        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Page Heading -->
            @isset($header)
                <header class="bg-white dark:bg-gray-800 shadow z-10">
                    <div class="max-w-full mx-auto py-6 px-4 sm:px-6 lg:px-8">
                        {{ $header }}
                    </div>
                </header>
            @endisset

            @if(request()->route('firewall'))
                @include('layouts.navigation')
            @endif

            <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-900 pb-20">

                <!-- Page Content -->
                @isset($firewall)
                    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8 mt-4">
                        <x-apply-changes-banner :firewall="$firewall" />
                    </div>
                @endisset


                {{ $slot }}
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Set Default Options for SweetAlert2 to prevent layout shifts
        const Toast = Swal.mixin({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });

        // Global Override to prevent body padding shift
        const swalFire = Swal.fire;
        Swal.fire = function (args) {
            // If args is an object, merge defaults
            if (typeof args === 'object' && args !== null) {
                args.heightAuto = false;
                args.scrollbarPadding = false;
            }
            return swalFire.apply(this, arguments);
        };
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            @if(session('success'))
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: "{{ session('success') }}",
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000,
                    timerProgressBar: true
                });
            @endif

            @if(session('error'))
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: "{{ session('error') }}",
                    toast: true, // Set to false if you want a modal instead of a toast
                    position: 'top-end', // Center if modal
                    showConfirmButton: true,
                    // timer: 5000 
                });
            @endif
        });
    </script>

    <!-- System Status Widget -->
    <div id="websocket-status" style="display: none;"></div> {{-- Connector for echo.js default behavior --}}

    <!-- System Status Widget (Hidden/Legacy Hook) -->
    <div id="websocket-status" style="display: none;"></div>
    <script>
        // Minimal hook to prevent errors if anything relies on it, though sidebar polls directly.
        window.updateSystemStatus = function (s) { /* No-op or log */ };

        /**
         * Shared Filterable List Mixin
         * Provides URL-persisted filtering, search, and count functionality
         * 
         * Usage in Alpine components:
         * Alpine.data('myComponent', (items) => ({
         *     ...window.filterableMixin(items, 'my-update-event'),
         *     // Your custom properties
         *     myCustomProp: 'value',
         *     
         *     init() {
         *         this.initFilterable();
         *         // Your custom init code
         *     }
         * }))
         */
        window.filterableMixin = function (items, statusEventName = 'device-updated') {
            return {
                // Filter state (initialized from URL)
                search: new URLSearchParams(window.location.search).get('search') || '',
                statusFilter: new URLSearchParams(window.location.search).get('status') || 'all',
                customerFilter: new URLSearchParams(window.location.search).get('customer') || 'all',

                // Data
                items: items,
                itemStatuses: {},

                /**
                 * Initialize filterable functionality
                 * Call this from your component's init() method
                 */
                initFilterable() {
                    // Watch for filter changes and sync to URL
                    this.$watch('search', (val) => this.updateUrl('search', val));
                    this.$watch('statusFilter', (val) => this.updateUrl('status', val));
                    this.$watch('customerFilter', (val) => this.updateUrl('customer', val));

                    // Listen for status updates to track online/offline state
                    window.addEventListener(statusEventName, (e) => {
                        if (e.detail && e.detail.id) {
                            this.itemStatuses[e.detail.id] = { online: e.detail.online };
                        }
                    });
                },

                /**
                 * Update URL query parameters
                 */
                updateUrl(key, value) {
                    const url = new URL(window.location);
                    if (value && value !== 'all') {
                        url.searchParams.set(key, value);
                    } else {
                        url.searchParams.delete(key);
                    }
                    window.history.replaceState(null, '', url);
                },

                /**
                 * Get count of items matching current filters
                 */
                filteredCount() {
                    return this.items.filter(item => {
                        // Search filter
                        const q = this.search.toLowerCase();
                        const matchesSearch = !q || (item.searchData && item.searchData.includes(q)) || (item.staticInfo && item.staticInfo.includes(q));

                        // Status filter
                        let matchesStatus = true;
                        if (this.statusFilter !== 'all') {
                            const status = this.itemStatuses[item.id];
                            const isOnline = status ? status.online : (item.online || false);
                            if (this.statusFilter === 'online' && !isOnline) matchesStatus = false;
                            if (this.statusFilter === 'offline' && isOnline) matchesStatus = false;
                        }

                        // Customer/Company filter
                        let matchesCustomer = true;
                        if (this.customerFilter !== 'all') {
                            if (item.companyName !== this.customerFilter) matchesCustomer = false;
                        }

                        return matchesSearch && matchesStatus && matchesCustomer;
                    }).length;
                }
            };
        };
    </script>
    <!-- PWA INSTALL BUTTON -->
    <div id="pwa-install-container" class="fixed bottom-4 left-1/2 -translate-x-1/2 z-50 flex items-center gap-2"
        style="display: none;">
        <button id="pwa-install-btn"
            class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-3 px-6 rounded-full shadow-lg flex items-center space-x-2 transition-all transform hover:scale-105">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
            </svg>
            <span>Install App</span>
        </button>
        <button id="pwa-dismiss-btn"
            class="bg-gray-800/80 hover:bg-gray-700 text-gray-200 p-2 rounded-full shadow-lg transition-all backdrop-blur-sm"
            title="Dismiss temporarily">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24"
                stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <script>
        (function () {
            // ... existing PWA code ...
            window.deferredPrompt = null;
            const installContainer = document.getElementById('pwa-install-container');
            const installBtn = document.getElementById('pwa-install-btn');
            const dismissBtn = document.getElementById('pwa-dismiss-btn');

            // Global install function for sidebar/other components
            window.pwaInstall = function () {
                if (window.deferredPrompt) {
                    window.deferredPrompt.prompt();
                    window.deferredPrompt.userChoice.then((choiceResult) => {
                        window.deferredPrompt = null;
                        if (choiceResult.outcome === 'accepted') {
                            console.log('PWA: User accepted the A2HS prompt');
                            installContainer.style.display = 'none';
                        } else {
                            console.log('PWA: User dismissed the A2HS prompt');
                        }
                    });
                } else {
                    console.log('PWA: No deferred prompt available');
                }
            };

            window.addEventListener('beforeinstallprompt', (e) => {
                // Prevent Chrome 67 and earlier from automatically showing the prompt
                e.preventDefault();
                // Stash the event so it can be triggered later.
                window.deferredPrompt = e;

                // Dispatch event for other components (like sidebar) to know install is available
                window.dispatchEvent(new CustomEvent('pwa-ready'));

                // Show floating button only if not dismissed in this session
                if (!sessionStorage.getItem('pwa-dismissed')) {
                    installContainer.style.display = 'flex'; // Changed to flex for button alignment
                    console.log('PWA: Install event captured, button shown');
                } else {
                    console.log('PWA: Install event captured, but button dismissed for session');
                }
            });

            installBtn.addEventListener('click', (e) => {
                window.pwaInstall();
            });

            dismissBtn.addEventListener('click', (e) => {
                installContainer.style.display = 'none';
                sessionStorage.setItem('pwa-dismissed', 'true');
                console.log('PWA: Floating button dismissed for session');
            });

            window.addEventListener('appinstalled', () => {
                window.deferredPrompt = null;
                installContainer.style.display = 'none';
                console.log('PWA: App installed');
                // Optional: dispatch event to update sidebar state
                window.dispatchEvent(new CustomEvent('pwa-installed'));
            });
        })();
    </script>


    <script>
        function systemUpdateListener() {
            return {
                pollInterval: null,
                updateComplete: false,
                updateAvailable: false,
                isInstalling: false,
                dismissedSession: false,
                currentVersion: '',
                availableVersion: '',

                init() {
                    // Check session storage first
                    if (sessionStorage.getItem('admix_update_remind_later')) {
                        this.dismissedSession = true;
                    }

                    // Check for active installation first
                    if (localStorage.getItem('admix_update_active')) {
                        this.isInstalling = true;
                        this.startPolling(); // Poll for installation status
                    } else {
                        this.checkAvailability(); // Check for available updates
                    }

                    // Listen for reload from other components
                    window.addEventListener('system-update-reload', () => {
                        this.reloadPage();
                    });
                    window.addEventListener('system-update-started', () => {
                        this.isInstalling = true;
                        this.updateAvailable = false;
                        this.startPolling();
                    });

                    // Listen for install confirmation from Settings Page
                    window.addEventListener('system-update-install-confirmed', () => {
                        this.performUpdate();
                    });
                },

                // ... dismissUpdate ...

                async startUpdate() {
                    const result = await Swal.fire({
                        title: 'Install Update?',
                        text: "The system will be unavailable for a few minutes.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, install it!'
                    });

                    if (result.isConfirmed) {
                        this.performUpdate();
                    }
                },

                performUpdate() {
                    this.isInstalling = true;
                    this.updateAvailable = false;

                    // Dispatch initiating event so Settings UI shows "Queueing..." immediately
                    window.dispatchEvent(new CustomEvent('system-update-initiating'));

                    fetch('{{ route("system.updates.install") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                        .then(response => {
                            if (!response.ok) throw new Error('Update start failed');
                            return response.json().catch(() => ({}));
                        })
                        .then(data => {
                            console.log('Update started', data);
                            localStorage.setItem('admix_update_active', 'true');
                            window.dispatchEvent(new CustomEvent('system-update-started'));
                            this.startPolling();
                        })
                        .catch(error => {
                            console.error('Error starting update:', error);
                            this.isInstalling = false;
                            this.updateAvailable = true;

                            // Dispatch failure event
                            window.dispatchEvent(new CustomEvent('system-update-failed', {
                                detail: { message: 'Could not start the update process.' }
                            }));

                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: 'Could not start the update process. Please check logs.',
                                toast: true,
                                position: 'top-end'
                            });
                        });
                },

                dismissUpdate() {
                    Swal.fire({
                        title: 'Dismiss This Update?',
                        text: "You can hide this notification for now, or skip this specific version entirely.",
                        icon: 'question',
                        showCancelButton: true,
                        showDenyButton: true,
                        confirmButtonText: 'Skip This Version',
                        denyButtonText: 'Remind Me Later',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#3085d6',
                        denyButtonColor: '#6c757d',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            fetch('{{ route("system.updates.dismiss") }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ version: this.availableVersion })
                            })
                                .then(response => {
                                    if (response.ok) {
                                        this.updateAvailable = false;
                                        Swal.fire({
                                            title: 'Dismissed',
                                            text: 'You will not be notified about this version again.',
                                            icon: 'success',
                                            timer: 2000,
                                            showConfirmButton: false
                                        });
                                    } else {
                                        throw new Error('Failed to dismiss');
                                    }
                                })
                                .catch(error => {
                                    console.error('Error dismissing update:', error);
                                    Swal.fire('Error', 'Failed to dismiss update.', 'error');
                                });
                        } else if (result.isDenied) {
                            // Remind Me Later: Set session storage
                            sessionStorage.setItem('admix_update_remind_later', 'true');
                            this.dismissedSession = true;

                            Swal.fire({
                                icon: 'info',
                                title: 'Reminder Set',
                                text: 'We will remind you again when you next login.',
                                toast: true,
                                position: 'top-end',
                                timer: 3000,
                                showConfirmButton: false
                            });
                        }
                    });
                },

                async startUpdate() {
                    const result = await Swal.fire({
                        title: 'Install Update?',
                        text: "The system will be unavailable for a few minutes.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Yes, install it!'
                    });

                    if (result.isConfirmed) {
                        this.performUpdate();
                    }
                },

                performUpdate() {
                    this.isInstalling = true;
                    this.updateAvailable = false;
                    window.dispatchEvent(new CustomEvent('system-update-initiating'));

                    fetch('{{ route("system.updates.install") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        }
                    })
                        .then(response => {
                            // We expect a redirect or json. For now, just assume it started if 200/302.
                            // Ideally the backend returns JSON.
                            // If it redirects, fetch might follow it.
                            // Let's assume the backend starts the process.
                            if (!response.ok) throw new Error('Update start failed');
                            return response.json().catch(() => ({})); // Handle if no json
                        })
                        .then(data => {
                            console.log('Update started', data);
                            localStorage.setItem('admix_update_active', 'true');
                            window.dispatchEvent(new CustomEvent('system-update-started'));
                            this.startPolling();
                        })
                        .catch(error => {
                            console.error('Error starting update:', error);
                            this.isInstalling = false;
                            this.updateAvailable = true;
                            Swal.fire({
                                icon: 'error',
                                title: 'Update Failed',
                                text: 'Could not start the update process. Please check logs.',
                                toast: true,
                                position: 'top-end'
                            });
                        });
                },

                checkAvailability() {
                    fetch('{{ route("system.updates.check-global") }}')
                        .then(response => response.json())
                        .then(data => {
                            this.currentVersion = (data.current_version || 'unknown').replace(/^v/, '');
                            this.availableVersion = (data.version || 'unknown').replace(/^v/, '');
                            if (data.update_available && !this.isInstalling) {
                                this.updateAvailable = true;
                                this.updateComplete = false;
                            } else if (!this.isInstalling) {
                                this.updateAvailable = false;
                            }
                        })
                        .catch(error => {
                            console.error('Error checking update availability:', error);
                            this.updateAvailable = false;
                        });
                },

                startPolling() {
                    if (this.pollInterval) clearInterval(this.pollInterval);
                    this.pollStatus(); // Immediate check
                    this.pollInterval = setInterval(() => {
                        this.pollStatus();
                    }, 2000); // Poll every 2s
                },

                async pollStatus() {
                    try {
                        const response = await fetch('{{ route("system.updates.status") }}');
                        if (!response.ok) return;

                        const data = await response.json();

                        // Dispatch status for other components to sync
                        window.dispatchEvent(new CustomEvent('system-update-status', { detail: data }));

                        if (data.status === 'complete') {
                            this.updateComplete = true;
                            this.isInstalling = false;
                            this.updateAvailable = false;
                            clearInterval(this.pollInterval);
                            // Don't clear localStorage here; let reloadPage handle it
                        } else if (data.status === 'failed' || data.status === 'idle') {
                            clearInterval(this.pollInterval);
                            localStorage.removeItem('admix_update_active');
                            this.isInstalling = false;
                            this.updateComplete = false;

                            // Only re-check availability if it was a failure, or if we were installing
                            if (data.status === 'failed') {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Update Failed',
                                    text: data.last_error || 'Unknown error occurred.',
                                    toast: true,
                                    position: 'top-end'
                                });
                                this.checkAvailability();
                            }
                        } else {
                            // Still installing/downloading
                            this.isInstalling = true;
                            this.updateAvailable = false;
                            this.updateComplete = false;
                        }
                    } catch (e) {
                        console.error('Global poll error', e);
                    }
                },

                reloadPage() {
                    localStorage.removeItem('admix_update_active');
                    window.location.reload();
                }
            }
        }
    </script>
</body>

</html>