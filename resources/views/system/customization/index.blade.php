<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('System Settings') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            <form method="POST" action="{{ route('system.settings.update') }}" enctype="multipart/form-data" x-data="{ 
                      activeTab: 'configuration',
                      sidebarBg: '{{ $settings['sidebar_bg'] ?? '#1f2937' }}', 
                      sidebarText: '{{ $settings['sidebar_text'] ?? '#d1d5db' }}',
                      recommendedText() {
                        const r = parseInt(this.sidebarBg.substr(1,2),16);
                        const g = parseInt(this.sidebarBg.substr(3,2),16);
                        const b = parseInt(this.sidebarBg.substr(5,2),16);
                        const yiq = ((r*299)+(g*587)+(b*114))/1000;
                        return (yiq >= 128) ? '#1f2937' : '#ffffff';
                      }
                  }">
                @csrf
                @method('POST')

                <!-- Tabs Navigation -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                        <button type="button" @click="activeTab = 'configuration'"
                            :class="activeTab === 'configuration' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150">
                            System Configuration
                        </button>

                        <button type="button" @click="activeTab = 'maintenance'"
                            :class="activeTab === 'maintenance' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300'"
                            class="whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm transition-colors duration-150">
                            Maintenance & Operations
                        </button>
                    </nav>
                </div>

                <!-- Maintenance Tab Content -->
                <div x-show="activeTab === 'maintenance'" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0" style="display: none;">

                    <!-- Section: System Updates -->
                    <div class="card-modern" x-data="systemUpdater()">
                        <div class="card-header-modern">
                            <div class="card-icon-wrapper">
                                <svg class="card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="card-title-modern">System Updates</h3>
                                <p class="card-subtitle-modern">Update your system to the latest version.</p>
                            </div>
                        </div>
                        <div class="card-body-modern">
                            <div class="flex items-center justify-between">
                                <div class="flex flex-col">
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">
                                        Current Version: <span
                                            class="font-bold">v{{ $currentVersion ?? '0.0.0' }}</span>
                                    </div>
                                    <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                                        @if($updateAvailable ?? false)
                                            <span class="text-green-600 font-semibold">Update Available!</span>
                                        @else
                                            Your system is up to date.
                                        @endif
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="checkForUpdates" :disabled="checking"
                                        class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                        <svg x-show="checking"
                                            class="animate-spin -ml-1 mr-2 h-4 w-4 text-gray-700 dark:text-gray-200"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                        <span x-text="checking ? 'Checking...' : 'Check for Updates'"></span>
                                    </button>

                                    <button x-show="updateAvailable" type="button" @click="installUpdate" :disabled="installing"
                                        class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                                        <span x-show="!installing">Install Update &rarr;</span>
                                        <span x-show="installing">Queueing...</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Inline Feedback -->
                            <div x-show="message" x-transition class="mt-4 text-sm"
                                :class="isError ? 'text-red-600' : 'text-green-600'" x-text="message"></div>
                        </div>
                    </div>

                    <script>
                        function systemUpdater() {
                            return {
                                checking: false,
                                installing: false,
                                updateAvailable: {{ ($updateAvailable ?? false) ? 'true' : 'false' }},
                                message: '',
                                isError: false,
                                async checkForUpdates() {
                                    this.checking = true;
                                    this.message = '';
                                    this.isError = false;

                                    try {
                                        const response = await fetch('{{ route("system.updates.check") }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'Accept': 'application/json'
                                            }
                                        });

                                        const data = await response.json();

                                        if (data.update_available) {
                                            this.updateAvailable = true;
                                            this.message = 'New update found: ' + data.version;
                                        } else {
                                            this.message = 'System is up to date.';
                                        }

                                    } catch (e) {
                                        console.error(e);
                                        this.isError = true;
                                        this.message = 'Error checking for updates.';
                                    } finally {
                                        this.checking = false;
                                    }
                                },
                                async installUpdate() {
                                    if (!confirm('Are you sure you want to install this update? The system may be unavailable for a few minutes.')) return;

                                    this.installing = true;
                                    this.message = 'Queueing update...';
                                    this.isError = false;

                                    try {
                                        const response = await fetch('{{ route("system.updates.install") }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                                'Accept': 'application/json'
                                            }
                                        });

                                        const data = await response.json();

                                        if (response.ok) {
                                            this.message = data.message;
                                            this.updateAvailable = false; // Hide button
                                        } else {
                                            this.isError = true;
                                            this.message = data.message || 'Failed to queue update.';
                                        }
                                    } catch (e) {
                                        console.error(e);
                                        this.isError = true;
                                        this.message = 'Error initiating update.';
                                    } finally {
                                        this.installing = false;
                                    }
                                }
                            }
                        }
                    </script>

                    <!-- Section: System Backups -->
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <div class="card-icon-wrapper">
                                <svg class="card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="card-title-modern">System Backups</h3>
                                <p class="card-subtitle-modern">Manage system configuration backups and restoration.</p>
                            </div>
                        </div>
                        <div class="card-body-modern">
                            <div class="flex items-center justify-between">
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    Create encrypted backups of your system configuration, tenants, and users.
                                </div>
                                <a href="{{ route('system.backups.index') }}"
                                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                    Manage Backups &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Configuration Tab Content -->
                <div x-show="activeTab === 'configuration'" x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 translate-y-2"
                    x-transition:enter-end="opacity-100 translate-y-0">

                    <!-- Section 1: Branding & Identity -->
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <div class="card-icon-wrapper">
                                <svg class="card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="card-title-modern">Branding & Identity</h3>
                                <p class="card-subtitle-modern">Customize the look and feel of your application.</p>
                            </div>
                        </div>
                        <div class="card-body-modern grid grid-cols-1 md:grid-cols-2 gap-6 space-y-0">

                            <!-- Logo Upload -->
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Custom
                                    Logo
                                    (Top Left)</label>
                                <div
                                    class="flex items-center gap-4 p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    @if(isset($settings['logo_path']))
                                        <div class="shrink-0 relative group">
                                            <img src="{{ $settings['logo_path'] }}" alt="Logo"
                                                class="h-12 w-auto object-contain p-1 rounded bg-gray-200 dark:bg-gray-700"
                                                :style="'background-color: ' + sidebarBg">
                                            <button type="button" onclick="restoreDefault('logo')"
                                                class="absolute -top-2 -right-2 bg-red-100 text-red-600 rounded-full p-1 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
                                                title="Restore Default">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <div
                                            class="h-12 w-12 bg-gray-200 dark:bg-gray-600 rounded flex items-center justify-center text-gray-400">
                                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                                </path>
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="flex-1" x-data="{ fileName: null }">
                                        <input type="file" name="logo" id="logo" class="hidden" x-ref="logoInput"
                                            accept="image/*"
                                            @change="fileName = $refs.logoInput.files[0] ? $refs.logoInput.files[0].name : null">
                                        <button type="button" @click="$refs.logoInput.click()"
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Change Logo
                                        </button>
                                        <p x-show="fileName" x-text="fileName"
                                            class="mt-1 text-xs text-indigo-600 dark:text-indigo-400 truncate"></p>
                                        <p x-show="!fileName" class="mt-1 text-xs text-gray-500">PNG, JPG, SVG up to 2MB
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Favicon Upload -->
                            <div class="col-span-1">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Custom
                                    Favicon</label>
                                <div
                                    class="flex items-center gap-4 p-4 border-2 border-dashed border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    @if(isset($settings['favicon_path']))
                                        <div class="shrink-0 relative group">
                                            <img src="{{ $settings['favicon_path'] }}" alt="Favicon"
                                                class="h-10 w-10 object-contain p-1 rounded bg-white border border-gray-200">
                                            <button type="button" onclick="restoreDefault('favicon')"
                                                class="absolute -top-2 -right-2 bg-red-100 text-red-600 rounded-full p-1 shadow-sm opacity-0 group-hover:opacity-100 transition-opacity"
                                                title="Restore Default">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M6 18L18 6M6 6l12 12"></path>
                                                </svg>
                                            </button>
                                        </div>
                                    @else
                                        <div
                                            class="h-10 w-10 bg-gray-200 dark:bg-gray-600 rounded-full flex items-center justify-center text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1">
                                                </path>
                                            </svg>
                                        </div>
                                    @endif
                                    <div class="flex-1" x-data="{ fileName: null }">
                                        <input type="file" name="favicon" id="favicon" class="hidden" x-ref="favInput"
                                            accept=".ico,.png"
                                            @change="fileName = $refs.favInput.files[0] ? $refs.favInput.files[0].name : null">
                                        <button type="button" @click="$refs.favInput.click()"
                                            class="inline-flex items-center px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm leading-4 font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                            Change Favicon
                                        </button>
                                        <p x-show="fileName" x-text="fileName"
                                            class="mt-1 text-xs text-indigo-600 dark:text-indigo-400 truncate"></p>
                                        <p x-show="!fileName" class="mt-1 text-xs text-gray-500">ICO, PNG up to 1MB</p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Section 2: Interface Customization -->
                    <!-- Section 2: Interface Customization -->
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <div class="card-icon-wrapper">
                                <svg class="card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="card-title-modern">Interface Customization</h3>
                                <p class="card-subtitle-modern">Manage themes and white-labeling colors.</p>
                            </div>
                        </div>
                        <div class="card-body-modern">

                            <!-- Theme Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-4">System
                                    Theme</label>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-w-md">
                                    <!-- Light Mode -->
                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="theme" value="light" class="peer sr-only" {{ ($settings['theme'] ?? 'light') === 'light' ? 'checked' : '' }}>
                                        <div
                                            class="p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 bg-white hover:border-indigo-300 peer-checked:border-indigo-600 peer-checked:bg-indigo-50 transition-all peer-checked:[&_.radio-circle]:bg-indigo-600 peer-checked:[&_.radio-circle]:border-indigo-600 peer-checked:[&_.radio-dot]:opacity-100">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div class="p-2 rounded-full bg-gray-100 text-yellow-500">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z">
                                                            </path>
                                                        </svg>
                                                    </div>
                                                    <span class="font-medium text-gray-900">Light Mode</span>
                                                </div>
                                                <!-- Radio Circle -->
                                                <div
                                                    class="radio-circle w-5 h-5 rounded-full border border-gray-300 bg-white flex items-center justify-center transition-all">
                                                    <div
                                                        class="radio-dot w-2 h-2 rounded-full bg-white opacity-0 transition-opacity">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                    <!-- Dark Mode -->
                                    <label class="relative cursor-pointer group">
                                        <input type="radio" name="theme" value="dark" class="peer sr-only" {{ ($settings['theme'] ?? 'light') === 'dark' ? 'checked' : '' }}>
                                        <div
                                            class="p-4 rounded-lg border-2 border-gray-200 dark:border-gray-700 bg-gray-800 hover:border-indigo-300 peer-checked:border-indigo-600 peer-checked:bg-gray-800 transition-all peer-checked:[&_.radio-circle]:bg-indigo-600 peer-checked:[&_.radio-circle]:border-indigo-600 peer-checked:[&_.radio-dot]:opacity-100">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center gap-3">
                                                    <div class="p-2 rounded-full bg-gray-700 text-blue-300">
                                                        <svg class="w-6 h-6" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z">
                                                            </path>
                                                        </svg>
                                                    </div>
                                                    <span class="font-medium text-white">Dark Mode</span>
                                                </div>
                                                <!-- Radio Circle -->
                                                <div
                                                    class="radio-circle w-5 h-5 rounded-full border border-gray-300 bg-white flex items-center justify-center transition-all">
                                                    <div
                                                        class="radio-dot w-2 h-2 rounded-full bg-white opacity-0 transition-opacity">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>

                            <!-- Sidebar Customization -->
                            <div class="pt-6 border-t border-gray-200 dark:border-gray-700">
                                <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">Sidebar
                                    Appearance
                                    (White Labeling)</h4>

                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                                    <div class="space-y-4">
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Background
                                            Color</label>
                                        <div class="flex items-center gap-3">
                                            <input type="color" x-model="sidebarBg" name="sidebar_bg"
                                                class="h-10 w-16 p-0 border-0 rounded cursor-pointer shadow-sm shrink-0">
                                            <input type="text" x-model="sidebarBg"
                                                class="uppercase flex-1 rounded-md border-gray-300 shadow-sm sm:text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>
                                    </div>
                                    <div class="space-y-4">
                                        <label for="sidebar_text"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Text
                                            Color</label>
                                        <div class="flex items-center gap-3">
                                            <!-- Color Swatch -->
                                            <input type="color" x-model="sidebarText" name="sidebar_text"
                                                id="sidebar_text"
                                                class="h-10 w-16 p-0 border-0 rounded cursor-pointer shadow-sm shrink-0">

                                            <!-- Hex Input -->
                                            <input type="text" x-model="sidebarText"
                                                class="uppercase flex-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-white">

                                            <!-- Auto-Detect Button -->
                                            <button type="button" @click="sidebarText = recommendedText()"
                                                class="shrink-0 inline-flex items-center gap-1.5 px-3 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                                                title="Auto-detect best contrast color">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                                    fill="currentColor" class="w-4 h-4 text-indigo-500">
                                                    <path
                                                        d="M15.98 1.804a1 1 0 00-1.96 0l-.24 1.192a1 1 0 01-.784.785l-1.192.238a1 1 0 000 1.96l1.192.238a1 1 0 01.785.785l.238 1.192a1 1 0 001.96 0l.238-1.192a1 1 0 01.785-.785l1.192-.238a1 1 0 000-1.96l-1.192-.238a1 1 0 01-.785-.785l-.238-1.192zM6.949 5.684a1 1 0 00-1.898 0l-.683 2.051a1 1 0 01-.633.633l-2.051.683a1 1 0 000 1.898l2.051.683a1 1 0 01.633.633l.683 2.051a1 1 0 001.898 0l.683-2.051a1 1 0 01.633-.633l2.051-.683a1 1 0 000-1.898l-2.051-.683a1 1 0 01-.633-.633L6.95 5.684zM13.949 13.684a1 1 0 00-1.898 0l-.184.551a1 1 0 01-.632.633l-.551.183a1 1 0 000 1.898l.551.183a1 1 0 01.633.633l.183.551a1 1 0 001.898 0l.184-.551a1 1 0 01.632-.633l.551-.183a1 1 0 000-1.898l-.551-.184a1 1 0 01-.633-.632l-.183-.551z" />
                                                </svg>
                                                <span class="hidden xl:inline">Auto</span>
                                            </button>
                                        </div>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Click Auto to pick the
                                            best
                                            text color for contrast.</p>
                                    </div>

                                    <!-- Live Preview -->
                                    <div class="lg:col-span-1">
                                        <div class="flex justify-between items-center mb-2">
                                            <label
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Live
                                                Preview</label>
                                            <button type="button"
                                                @click="sidebarBg = '#1f2937'; sidebarText = '#d1d5db'"
                                                class="text-xs font-medium text-indigo-600 hover:text-indigo-500 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300">
                                                Reset to Default
                                            </button>
                                        </div>
                                        <div class="h-24 w-full rounded-lg shadow-inner flex flex-col justify-center px-4 transition-colors duration-200 border border-gray-200 dark:border-gray-700"
                                            :style="'background-color: ' + sidebarBg">
                                            <div class="flex items-center gap-2 mb-2" :style="'color: ' + sidebarText">
                                                <div class="w-2 h-2 rounded-full"
                                                    :style="'background-color: ' + sidebarText"></div>
                                                <span class="text-sm font-medium opacity-100">Dashboard</span>
                                            </div>
                                            <div class="flex items-center gap-2" :style="'color: ' + sidebarText">
                                                <div class="w-2 h-2 rounded-full opacity-50"
                                                    :style="'background-color: ' + sidebarText"></div>
                                                <span class="text-sm font-medium opacity-75">Firewalls</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- Section: Site Configuration -->
                    <!-- Section: Site Configuration -->
                    <div class="card-modern" x-data="sslInstaller()">
                        <div class="card-header-modern">
                            <div class="card-icon-wrapper">
                                <svg class="card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="card-title-modern">Site Configuration</h3>
                                <p class="card-subtitle-modern">Manage your primary application URL and security
                                    settings.
                                </p>
                            </div>
                        </div>

                        <div class="card-body-modern">
                            <div class="grid grid-cols-1 gap-8">
                                <!-- Hostname & Protocol -->
                                <div>
                                    <label
                                        class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Primary
                                        Site URL</label>
                                    <div
                                        class="relative flex items-stretch rounded-xl shadow-sm border border-gray-300 dark:border-gray-700 focus-within:border-indigo-600 focus-within:ring-1 focus-within:ring-indigo-600 overflow-hidden">
                                        <div
                                            class="flex flex-shrink-0 items-center bg-gray-50 dark:bg-gray-800 border-r border-gray-200 dark:border-gray-700 px-3">
                                            <span class="text-gray-500 sm:text-sm font-medium">
                                                {{ $settings['site_protocol'] ?? 'http' }}://
                                            </span>
                                            <input type="hidden" name="site_protocol"
                                                value="{{ $settings['site_protocol'] ?? 'http' }}">
                                        </div>
                                        <input type="text" name="site_url" value="{{ $settings['site_url'] ?? '' }}"
                                            placeholder="central.example.com"
                                            class="block flex-1 border-0 bg-transparent py-2.5 pl-3 text-gray-900 dark:text-white placeholder:text-gray-400 focus:ring-0 sm:text-sm sm:leading-6">
                                        <div class="flex-shrink-0">
                                            <button type="button" id="verifyDomainBtn"
                                                onclick="verifyReachabilityOnly()"
                                                class="inline-flex items-center gap-x-2 px-4 py-2.5 text-sm font-semibold text-indigo-600 bg-transparent dark:bg-transparent hover:bg-indigo-50 dark:hover:bg-gray-700 transition-colors h-full rounded-r-xl">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24"
                                                    stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                </svg>
                                                Verify
                                            </button>
                                        </div>
                                    </div>
                                    <p class="mt-2 text-xs text-red-500 dark:text-red-400 flex items-start gap-1">
                                        <svg class="h-4 w-4 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24"
                                            stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <span>Changing this will update Nginx configurations. Ensure the hostname is
                                            resolvable.</span>
                                    </p>
                                </div>

                                <!-- SSL Upgrade -->
                                @if(($settings['site_protocol'] ?? 'http') === 'http')
                                    <div
                                        class="rounded-md bg-yellow-50 dark:bg-yellow-900/30 p-4 border border-yellow-200 dark:border-yellow-700/50">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor"
                                                    aria-hidden="true">
                                                    <path fill-rule="evenodd"
                                                        d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3 flex-1 md:flex md:justify-between">
                                                <p class="text-sm text-yellow-700 dark:text-yellow-300">
                                                    <span class="font-medium">Connection Not Secure.</span>
                                                    Admix Central recommends using HTTPS. You can automatically provision a
                                                    free
                                                    Let's Encrypt SSL certificate.
                                                </p>
                                                <p class="mt-3 text-sm md:ml-6 md:mt-0">
                                                    <button type="button"
                                                        @click="openModal('{{ $settings['site_url'] ?? '' }}')"
                                                        class="whitespace-nowrap font-medium text-indigo-600 dark:text-indigo-400 hover:text-indigo-500 hover:underline">
                                                        Install SSL Certificate
                                                        <span aria-hidden="true"> &rarr;</span>
                                                    </button>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @elseif(($settings['site_protocol'] ?? 'http') === 'https')
                                    <div
                                        class="rounded-md bg-green-50 dark:bg-green-900/30 p-4 border border-green-200 dark:border-green-700/50">
                                        <div class="flex">
                                            <div class="flex-shrink-0">
                                                <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor"
                                                    aria-hidden="true">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd" />
                                                </svg>
                                            </div>
                                            <div class="ml-3 flex-1 md:flex md:justify-between">
                                                <p class="text-sm text-green-700 dark:text-green-300">
                                                    <span class="font-medium">Secure Connection.</span>
                                                    SSL is installed and active.
                                                </p>
                                                <p class="mt-3 text-sm md:ml-6 md:mt-0">
                                                    <button type="button" @click="confirmUninstall()"
                                                        class="whitespace-nowrap font-medium text-red-600 dark:text-red-400 hover:text-red-500 hover:underline">
                                                        Uninstall SSL
                                                    </button>
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- SSL Modal -->
                        <x-modal name="install-ssl-modal" :show="$errors->isNotEmpty()" focusable maxWidth="md">
                            <div class="p-6">
                                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-white mb-4">Install
                                    Let's Encrypt SSL</h3>

                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Domain
                                            Name</label>
                                        <input type="text" x-model="domain"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            placeholder="central.example.com">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Email
                                            Address (for renewal)</label>
                                        <input type="email" x-model="email"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                            placeholder="admin@example.com">
                                    </div>

                                    <!-- Error Message -->
                                    <div x-show="error" class="text-red-500 text-sm mt-2" x-text="error"></div>
                                </div>

                                <div class="mt-6 flex justify-end gap-3">
                                    <button type="button" x-on:click="$dispatch('close')"
                                        class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                                    <button type="button" @click="installSsl" :disabled="loading"
                                        class="rounded-md bg-indigo-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 disabled:opacity-50 flex items-center gap-2">
                                        <svg x-show="loading" class="animate-spin h-4 w-4 text-white" fill="none"
                                            viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                        <span x-text="loading ? 'Installing...' : 'Install Certificate'"></span>
                                    </button>
                                </div>
                            </div>
                        </x-modal>
                    </div>

                    <!-- Section: Email Configuration -->
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <div class="card-icon-wrapper">
                                <svg class="card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="card-title-modern">Email Configuration</h3>
                                <p class="card-subtitle-modern">Configure email delivery settings for notifications and
                                    magic logins.</p>
                            </div>
                        </div>
                        <div class="card-body-modern space-y-6"
                            x-data="{ driver: '{{ $settings['mail_driver'] ?? 'log' }}' }">

                            <!-- Driver Selection -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mail
                                    Driver</label>
                                <select name="mail_driver" x-model="driver"
                                    class="mt-1 block w-full rounded-md border-gray-300 py-2 pl-3 pr-10 text-base focus:border-indigo-500 focus:outline-none focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    <option value="log">Local Logging (No Email Sent)</option>
                                    <option value="mailgun">Mailgun (Recommended)</option>
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Mailgun is recommended for
                                    production.</p>
                            </div>

                            <!-- Mailgun Settings -->
                            <div x-show="driver === 'mailgun'"
                                class="space-y-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                <!-- Mailgun Specific Settings -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <!-- Domain -->
                                    <div>
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mailgun
                                            Domain</label>
                                        <input type="text" name="mailgun_domain"
                                            value="{{ $settings['mailgun_domain'] ?? '' }}"
                                            placeholder="mg.yourdomain.com"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>

                                    <!-- Secret -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">API
                                            Key (Secret)</label>
                                        <input type="password" name="mailgun_secret"
                                            value="{{ $settings['mailgun_secret'] ?? '' }}"
                                            placeholder="key-xxxxxxxxxxxxxxxx"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>

                                    <!-- Region/Endpoint -->
                                    <div class="col-span-1 md:col-span-2">
                                        <label
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mailgun
                                            Region</label>
                                        <select name="mailgun_endpoint"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                            <option value="api.mailgun.net" {{ ($settings['mailgun_endpoint'] ?? 'api.mailgun.net') === 'api.mailgun.net' ? 'selected' : '' }}>US
                                                (api.mailgun.net)</option>
                                            <option value="api.eu.mailgun.net" {{ ($settings['mailgun_endpoint'] ?? '') === 'api.eu.mailgun.net' ? 'selected' : '' }}>EU (api.eu.mailgun.net)
                                            </option>
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Select EU if your
                                            Mailgun
                                            domain is in the European region.</p>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- From Address -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">From
                                            Address</label>
                                        <input type="email" name="mail_from_address"
                                            value="{{ $settings['mail_from_address'] ?? 'hello@example.com' }}"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>

                                    <!-- From Name -->
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">From
                                            Name</label>
                                        <input type="text" name="mail_from_name"
                                            value="{{ $settings['mail_from_name'] ?? config('app.name') }}"
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                    </div>
                                </div>

                                <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700"
                                    x-data="emailTester()">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Test
                                        Configuration
                                    </h4>
                                    <div class="flex gap-4 items-end">
                                        <div class="flex-1">
                                            <label
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Send
                                                Test Email To</label>
                                            <input type="email" x-model="testEmail" placeholder="your@email.com"
                                                class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>
                                        <button type="button" @click="sendTestEmail" :disabled="loading"
                                            class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50">
                                            <span x-show="!loading">Send Test Email</span>
                                            <span x-show="loading">Sending...</span>
                                        </button>
                                    </div>
                                    <!-- Feedback Messages -->
                                    <div x-show="successMessage" x-transition
                                        class="mt-4 p-3 rounded-md bg-green-50 text-green-700 dark:bg-green-900/50 dark:text-green-300">
                                        <p x-text="successMessage"></p>
                                    </div>
                                    <div x-show="errorMessage" x-transition
                                        class="mt-4 p-3 rounded-md bg-red-50 text-red-700 dark:bg-red-900/50 dark:text-red-300">
                                        <p x-text="errorMessage"></p>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <script>
                        function emailTester() {
                            return {
                                testEmail: '{{ auth()->user()->email }}',
                                loading: false,
                                successMessage: null,
                                errorMessage: null,
                                async sendTestEmail() {
                                    if (!this.testEmail) {
                                        this.errorMessage = 'Please enter an email address.';
                                        return;
                                    }
                                    this.loading = true;
                                    this.errorMessage = null;
                                    this.successMessage = null;

                                    try {
                                        const response = await fetch('{{ route("system.settings.test-email") }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                            },
                                            body: JSON.stringify({ test_email: this.testEmail })
                                        });

                                        const data = await response.json();

                                        if (data.success) {
                                            this.successMessage = data.message;
                                        } else {
                                            this.errorMessage = data.message || 'Failed to send test email.';
                                        }
                                    } catch (e) {
                                        this.errorMessage = 'An error occurred while sending the email.';
                                    } finally {
                                        this.loading = false;
                                    }
                                }
                            }
                        }

                        function sslInstaller() {
                            return {
                                showModal: false,
                                domain: '',
                                email: '',
                                loading: false,
                                error: null,
                                openModal(currentUrl) {
                                    this.domain = currentUrl.replace(/^https?:\/\//, '').replace(/\/$/, '');
                                    this.error = null;
                                    window.dispatchEvent(new CustomEvent('open-modal', { detail: 'install-ssl-modal' }));
                                },
                                async installSsl() {
                                    if (!this.domain || !this.email) {
                                        this.error = 'Please fill in all fields.';
                                        return;
                                    }

                                    this.loading = true;
                                    this.error = null;

                                    try {
                                        const response = await fetch('{{ route("system.ssl.install") }}', {
                                            method: 'POST',
                                            headers: {
                                                'Content-Type': 'application/json',
                                                'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                            },
                                            body: JSON.stringify({ domain: this.domain, email: this.email })
                                        });

                                        const data = await response.json();

                                        if (data.success) {
                                            window.location.href = data.redirect;
                                        } else {
                                            throw new Error(data.message);
                                        }
                                    } catch (e) {
                                        this.error = e.message || 'Installation failed.';
                                    } finally {
                                        this.loading = false;
                                    }
                                }
                            }
                        }
                    </script>



                    <!-- Section 3: Performance -->
                    <div class="card-modern">
                        <div class="card-header-modern">
                            <div class="card-icon-wrapper">
                                <svg class="card-icon" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M13 10V3L4 14h7v7l9-11h-7z" />
                                </svg>
                            </div>
                            <div>
                                <h3 class="card-title-modern">System Performance</h3>
                                <p class="card-subtitle-modern">Fine-tune refresh rates and caching strategies.</p>
                            </div>
                        </div>
                        <div class="card-body-modern">

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <!-- Real-time -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Websocket
                                        Refresh (Seconds)</label>
                                    <div class="mt-1">
                                        <input type="number" name="realtime_interval" min="2" max="300"
                                            value="{{ $settings['realtime_interval'] ?? 10 }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <p class="mt-1 text-xs text-gray-500">Update frequency when connected to
                                            WebSocket.
                                        </p>
                                    </div>
                                </div>
                                <!-- Fallback -->
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Polling
                                        Refresh (Seconds)</label>
                                    <div class="mt-1">
                                        <input type="number" name="fallback_interval" min="5" max="600"
                                            value="{{ $settings['fallback_interval'] ?? 30 }}"
                                            class="block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        <p class="mt-1 text-xs text-gray-500">Update frequency when WebSockets are
                                            disconnected.</p>
                                    </div>
                                </div>

                                <!-- System Status Check (Locked) -->
                                <div x-data="{ 
                                    locked: true, 
                                    unlock() { 
                                        this.locked = false; 
                                        window.dispatchEvent(new CustomEvent('close-modal', { detail: 'status-check-warning' }));
                                        $nextTick(() => $refs.intervalInput.focus()); 
                                    } 
                                }">
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">System
                                        Status
                                        Check Interval</label>
                                    <div class="mt-1 relative rounded-md shadow-sm w-full">
                                        <input type="number" name="status_check_interval" x-ref="intervalInput" min="5"
                                            max="300" value="{{ $settings['status_check_interval'] ?? 5 }}"
                                            :readonly="locked"
                                            :class="locked ? 'bg-gray-100 text-gray-500 cursor-not-allowed dark:bg-gray-800' : 'bg-white text-gray-900 dark:bg-gray-700 dark:text-white'"
                                            class="block w-full rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm dark:border-gray-600">

                                        <div x-show="locked" class="absolute inset-y-0 right-0 flex items-center pr-2">
                                            <button type="button"
                                                @click="$dispatch('open-modal', 'status-check-warning')"
                                                class="bg-gray-200 dark:bg-gray-700 px-2 py-1 text-xs rounded border border-gray-300 dark:border-gray-600 hover:bg-gray-300 dark:hover:bg-gray-600 text-gray-600 dark:text-gray-300">Unlock</button>
                                        </div>
                                    </div>

                                    <!-- Warning Modal -->
                                    <x-modal name="status-check-warning" maxWidth="sm" focusable>
                                        <div class="bg-white dark:bg-gray-800 px-4 pb-4 pt-5 sm:p-6 sm:pb-4">
                                            <div
                                                class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 dark:bg-red-900/20 mb-4">
                                                <svg class="h-6 w-6 text-red-600 dark:text-red-400" fill="none"
                                                    viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                            </div>
                                            <div class="text-center">
                                                <h3
                                                    class="text-base font-semibold leading-6 text-gray-900 dark:text-white">
                                                    Caution Recommended</h3>
                                                <div class="mt-2 text-sm text-gray-500 dark:text-gray-400">Changing
                                                    this value too low might cause high server load. Are you sure?
                                                </div>
                                            </div>
                                            <div class="mt-5 sm:mt-6 grid grid-cols-2 gap-3">
                                                <button type="button" x-on:click="$dispatch('close')"
                                                    class="inline-flex w-full justify-center rounded-md bg-white px-3 py-2 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50">Cancel</button>
                                                <button type="button" @click="unlock()"
                                                    class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500">Unlock</button>
                                            </div>
                                        </div>
                                    </x-modal>
                                </div>

                            </div>

                            <!-- Status Cache (MOVED TO BOTTOM) -->
                            <div
                                class="pt-6 border-t border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                <div class="flex flex-col">
                                    <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Enable Status
                                        Caching</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">If disabled, status shows
                                        "Unknown" until fresh check.</span>
                                </div>
                                <button type="button"
                                    x-data="{ on: {{ ($settings['enable_status_cache'] ?? 1) ? 'true' : 'false' }} }"
                                    @click="on = !on; $refs.cacheInput.value = on ? '1' : '0'"
                                    :class="on ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
                                    class="relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-600 focus:ring-offset-2">
                                    <span aria-hidden="true" :class="on ? 'translate-x-5' : 'translate-x-0'"
                                        class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"></span>
                                    <input type="hidden" name="enable_status_cache" x-ref="cacheInput"
                                        :value="on ? '1' : '0'">
                                </button>
                            </div>

                        </div>
                    </div>



                </div>

                <!-- Footer / Save (Only visible in Configuration Tab) -->
                <div class="flex items-center justify-end gap-x-6 pb-12" x-show="activeTab === 'configuration'">
                    @if (session('success'))
                        <div x-data="{ show: true }" x-show="show" x-transition
                            x-init="setTimeout(() => show = false, 3000)"
                            class="text-sm font-medium text-green-600 dark:text-green-400">
                            {{ session('success') }}
                        </div>
                    @endif
                    <button type="button" id="saveButton" onclick="verifyAndSubmit()"
                        class="rounded-md bg-indigo-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 transition-colors">
                        Save System Settings
                    </button>
                    <!-- Loading Spinner (Hidden by default) -->
                    <div id="verifyingSpinner" class="hidden flex items-center gap-2 text-indigo-600">
                        <svg class="animate-spin h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4">
                            </circle>
                            <path class="opacity-75" fill="currentColor"
                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                            </path>
                        </svg>
                        <span class="text-sm font-medium">Verifying reachability...</span>
                    </div>
                </div>

                <!-- Force Save Warning Modal -->
                <div x-data="{ 
                        errorMessage: '',
                        hostname: '',
                        url: '',
                        open(msg, host, link) { 
                            this.errorMessage = msg; this.hostname = host; this.url = link; 
                            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'force-save-modal' }));
                        },
                        confirm() { document.getElementById('saveButton').closest('form').submit(); }
                    }"
                    @open-force-save-modal.window="open($event.detail.error, $event.detail.hostname, $event.detail.url)">

                    <x-modal name="force-save-modal" focusable maxWidth="lg">
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
                                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">
                                        Verification Failed</h3>
                                    <div class="mt-2">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Could not automatically verify access to <span
                                                class="font-mono font-medium text-gray-700 dark:text-gray-300"
                                                x-text="hostname"></span>.
                                        </p>

                                        <div class="mt-3 mb-3">
                                            <a :href="url" target="_blank"
                                                class="inline-flex items-center gap-1 text-sm font-medium text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 underline">
                                                <span>Open site in new tab to verify manually</span>
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14">
                                                    </path>
                                                </svg>
                                            </a>
                                        </div>

                                        <p class="text-sm text-red-600 dark:text-red-400 font-mono bg-red-50 dark:bg-red-900/10 p-2 rounded text-xs break-all"
                                            x-text="errorMessage"></p>
                                        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                            If you are sure DNS is configured correctly (e.g., using a local IP
                                            or internal DNS), you can force save these settings.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                            <button type="button" @click="confirm()"
                                class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">
                                Proceed Anyway
                            </button>
                            <button type="button" x-on:click="$dispatch('close')"
                                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto">
                                Cancel
                            </button>
                        </div>
                    </x-modal>
                </div>

            </form>
        </div>
    </div>

    <!-- Uninstall SSL Warning Modal -->
    <x-modal name="uninstall-ssl-modal" focusable maxWidth="lg">
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
                <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                    <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white">Uninstall
                        SSL Certificate?</h3>
                    <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            Are you sure you want to remove the SSL certificate? This will revert your site
                            to <span
                                class="font-mono text-xs bg-gray-100 dark:bg-gray-700 px-1 py-0.5 rounded">http://</span>
                            and traffic will no longer be encrypted.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
            <form method="POST" action="{{ route('system.ssl.uninstall') }}">
                @csrf
                @method('DELETE')
                <button type="submit"
                    class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">
                    Uninstall
                </button>
            </form>
            <button type="button" x-on:click="$dispatch('close')"
                class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto">
                Cancel
            </button>
        </div>
    </x-modal>

    <!-- Restore Branding Modal -->
    <div x-data="{ type: '' }"
        @open-restore-modal.window="type = $event.detail.type; $dispatch('open-modal', 'restore-branding-modal')">
        <x-modal name="restore-branding-modal" focusable maxWidth="md">
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
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                        <h3 class="text-base font-semibold leading-6 text-gray-900 dark:text-white"
                            x-text="'Restore Default ' + type.charAt(0).toUpperCase() + type.slice(1)"></h3>
                        <div class="mt-2">
                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                Are you sure you want to restore the default <span x-text="type"></span>? This
                                cannot be undone.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="bg-gray-50 dark:bg-gray-700/50 px-4 py-3 sm:flex sm:flex-row-reverse sm:px-6">
                <form method="POST" action="{{ route('system.settings.restore') }}">
                    @csrf
                    <input type="hidden" name="type" :value="type">
                    <button type="submit"
                        class="inline-flex w-full justify-center rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-red-500 sm:ml-3 sm:w-auto">
                        Restore
                    </button>
                </form>
                <button type="button" x-on:click="$dispatch('close')"
                    class="mt-3 inline-flex w-full justify-center rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-300 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700 sm:mt-0 sm:w-auto">
                    Cancel
                </button>
            </div>
        </x-modal>
    </div> <!-- Toast Notification -->
    <div x-data="{ show: false, message: '', type: 'success' }"
        @notify.window="show = true; message = $event.detail.message; type = $event.detail.type || 'success'; setTimeout(() => show = false, 4000)"
        x-show="show" x-transition:enter="transform ease-out duration-300 transition"
        x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0" x-cloak
        class="fixed bottom-0 right-0 z-50 m-6 max-w-sm w-full bg-white dark:bg-gray-800 shadow-lg rounded-lg pointer-events-auto ring-1 ring-black ring-opacity-5 overflow-hidden">
        <div class="p-4">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <svg x-show="type === 'success'" class="h-6 w-6 text-green-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <svg x-show="type === 'error'" class="h-6 w-6 text-red-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div class="ml-3 w-0 flex-1 pt-0.5">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100" x-text="message"></p>
                </div>
                <div class="ml-4 flex-shrink-0 flex">
                    <button @click="show = false"
                        class="bg-transparent rounded-md inline-flex text-gray-400 hover:text-gray-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                        <span class="sr-only">Close</span>
                        <svg class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function restoreDefault(type) {
            window.dispatchEvent(new CustomEvent('open-restore-modal', { detail: { type: type } }));
        }

        function confirmUninstall() {
            window.dispatchEvent(new CustomEvent('open-modal', { detail: 'uninstall-ssl-modal' }));
        }

        async function verifyAndSubmit() {
            const btn = document.getElementById('saveButton');
            const spinner = document.getElementById('verifyingSpinner');
            const form = btn.closest('form');

            // Get values
            // Get values
            const hostnameInput = form.querySelector('input[name="site_url"]');
            // const protocolElement = form.querySelector('[name="site_protocol"]');

            const hostname = hostnameInput.value.trim();
            const protocol = 'http'; // Force HTTP for verification

            if (!hostname) {
                form.submit();
                return;
            }

            // UI State
            btn.classList.add('hidden');
            spinner.classList.remove('hidden');

            // Construct Link URL
            let cleanHost = hostname.replace(/^https?:\/\//, '').replace(/\/$/, '');
            const checkLink = `${protocol}://${cleanHost}`;

            // Use server-side proxy to check reachability
            // This avoids Mixed Content (HTTPS -> HTTP) and CORS issues
            const proxyUrl = '{{ route("system.proxy-check") }}';

            try {
                const response = await fetch(proxyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ url: checkLink })
                });

                const data = await response.json();

                if (data.status === 'ok') {
                    form.submit();
                } else {
                    throw new Error(data.message || 'Validation failed');
                }

            } catch (error) {
                console.error('Verification failed:', error);

                // Reset UI buttons first so they don't get stuck if user cancels modal
                btn.classList.remove('hidden');
                spinner.classList.add('hidden');

                // Dispatch event to open modal
                window.dispatchEvent(new CustomEvent('open-force-save-modal', {
                    detail: {
                        error: error.message,
                        hostname: hostname,
                        url: checkLink
                    }
                }));
            }
        }

        window.verifyReachabilityOnly = async function () {
            console.log('Verify Button Clicked');
            const btn = document.getElementById('verifyDomainBtn');

            // Use the hidden input for protocol now (since dropdown is gone)
            // But verifyAndSubmit used querySelector('[name="site_protocol"]').
            // My previous fix in Step 1135 ensured it finds it.

            const hostnameInput = document.querySelector('input[name="site_url"]');
            // const protocolElement = document.querySelector('[name="site_protocol"]');

            const hostname = hostnameInput.value.trim();
            const protocol = 'http'; // Force HTTP for verification

            if (!hostname) return;

            const originalText = btn.innerText;
            btn.disabled = true;
            btn.innerText = '...';


            // Construct Link URL
            let cleanHost = hostname.replace(/^https?:\/\//, '').replace(/\/$/, '');
            const checkLink = `${protocol}://${cleanHost}`;
            const targetUrl = `${checkLink}/system/check-hostname`;

            console.log(`Verifying reachability of: ${targetUrl}`);

            // Use server-side proxy
            const proxyUrl = '{{ route("system.proxy-check") }}';

            try {
                const response = await fetch(proxyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ url: checkLink })
                });

                const data = await response.json();

                if (data.status === 'ok') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: `Domain is accessible via HTTP`,
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    });
                } else {
                    throw new Error(data.message || 'Verification Failed');
                }
            } catch (error) {
                console.error(error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: `Not Accessible: ${error.message || 'Unknown Error'}`,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 5000,
                    timerProgressBar: true
                });
            } finally {
                btn.disabled = false;
                btn.innerText = originalText;
            }
        }
    </script>
</x-app-layout>