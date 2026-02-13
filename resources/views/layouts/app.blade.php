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

<body class="font-sans antialiased h-full overflow-hidden bg-gray-100 dark:bg-gray-900"
    x-data="{ sidebarOpen: false, collapsed: window.innerWidth < 1536 }"
    @resize.window="collapsed = window.innerWidth < 1536">
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

    <div class="flex h-dvh bg-gray-100 dark:bg-gray-900 md:h-dvh h-[calc(100dvh-4rem)]">
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
</body>

</html>