<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Edit Firewall</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-0.5">{{ $firewall->name }}</p>
            </div>
            <a href="{{ route('firewall.dashboard', $firewall) }}"
               class="inline-flex items-center text-sm text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200 transition-colors">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                </svg>
                Back to Dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-8">
        <div class="mx-auto sm:px-6 lg:px-8">
            <form action="{{ route('firewalls.update', $firewall) }}" method="POST">
                @csrf
                @method('PUT')

                {{-- 2-column grid on lg+, stacked on mobile --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- ── Identity ─────────────────────────────────────── --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Identity</h3>
                        </div>
                        <div class="px-6 py-5 flex flex-col gap-4 flex-1">

                            @if(auth()->user()->isGlobalAdmin())
                                <div>
                                    <label for="company_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Company</label>
                                    <select name="company_id" id="company_id" required
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}" {{ (old('company_id', $firewall->company_id) == $company->id) ? 'selected' : '' }}>
                                                {{ $company->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('company_id')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            @else
                                <input type="hidden" name="company_id" value="{{ $firewall->company_id }}">
                            @endif

                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Firewall Name</label>
                                <input type="text" name="name" id="name" value="{{ old('name', $firewall->name) }}" required
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Description <span class="text-gray-400 font-normal">(optional)</span>
                                </label>
                                <textarea name="description" id="description" rows="3"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm resize-y">{{ old('description', $firewall->description) }}</textarea>
                                @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                        </div>
                    </div>

                    {{-- ── API Connection ───────────────────────────────── --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex flex-col"
                         x-data="{ authMethod: '{{ old('auth_method', $firewall->auth_method ?? 'basic') }}' }">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">API Connection</h3>
                        </div>
                        <div class="px-6 py-5 flex flex-col gap-4 flex-1">

                            <div>
                                <label for="url" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Firewall URL</label>
                                <input type="url" name="url" id="url" value="{{ old('url', $firewall->url) }}" required
                                    placeholder="https://192.168.1.1"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                @error('url')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                            <div>
                                <label for="auth_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Authentication Method</label>
                                <select name="auth_method" id="auth_method" x-model="authMethod"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    <option value="basic">Basic Auth (Username / Password)</option>
                                    <option value="token">Bearer Token</option>
                                </select>
                            </div>

                            <div x-show="authMethod === 'basic'" class="grid grid-cols-2 gap-4" x-cloak>
                                <div>
                                    <label for="api_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Username</label>
                                    <input type="text" name="api_key" id="api_key"
                                        value="{{ old('api_key', $firewall->api_key) }}"
                                        :required="authMethod === 'basic'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    @error('api_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="api_secret" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">API Password</label>
                                    <input type="password" name="api_secret" id="api_secret"
                                        placeholder="Leave blank to keep current"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    @error('api_secret')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div x-show="authMethod === 'token'" x-cloak>
                                <label for="api_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bearer Token</label>
                                <textarea name="api_token" id="api_token" rows="4"
                                    :required="authMethod === 'token'"
                                    placeholder="ey…"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono">{{ old('api_token', $firewall->api_token) }}</textarea>
                                @error('api_token')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                        </div>
                    </div>

                    {{-- ── SSH Backup ────────────────────────────────────── --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700">
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">SSH — Config Backup</h3>
                        </div>
                        <div class="px-6 py-5 flex flex-col gap-4 flex-1">

                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <label for="ssh_port" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Port</label>
                                    <input type="number" name="ssh_port" id="ssh_port"
                                        value="{{ old('ssh_port', $firewall->ssh_port ?? 22) }}"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    @error('ssh_port')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="ssh_username" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Username</label>
                                    <input type="text" name="ssh_username" id="ssh_username"
                                        value="{{ old('ssh_username', $firewall->ssh_username) }}"
                                        placeholder="admin"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    @error('ssh_username')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="ssh_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Password</label>
                                    <input type="password" name="ssh_password" id="ssh_password"
                                        placeholder="Leave blank to keep current"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    @error('ssh_password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-auto">
                                Used for SFTP config backup only. Separate from API credentials above.
                            </p>

                        </div>
                    </div>

                    {{-- ── Location ─────────────────────────────────────── --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex flex-col"
                         x-data="addressAutocomplete()">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center justify-between">
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Location</h3>
                            <span x-show="lat" class="inline-flex items-center gap-1 text-xs text-green-600 dark:text-green-400" style="display:none;">
                                <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                Map location set
                            </span>
                        </div>
                        <div class="px-6 py-5 flex flex-col gap-3 flex-1">

                            <div class="relative" @click.outside="suggestions = []">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Address</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <template x-if="!address">
                                            <svg class="h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/>
                                            </svg>
                                        </template>
                                        <template x-if="address">
                                            <svg class="h-4 w-4 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                        </template>
                                    </div>
                                    <input type="text"
                                        x-model="searchQuery"
                                        @input.debounce.300ms="searchAddress()"
                                        :readonly="!!address"
                                        :class="address ? 'bg-gray-50 dark:bg-gray-700/50 text-gray-700 dark:text-gray-200 cursor-default' : 'dark:bg-gray-900 dark:text-gray-300'"
                                        class="pl-9 pr-10 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 text-sm transition-colors"
                                        :placeholder="address ? '' : 'Search for an address…'"
                                        autocomplete="off">
                                    <div x-show="loading" class="absolute inset-y-0 right-8 flex items-center pr-1" style="display:none;">
                                        <svg class="animate-spin h-4 w-4 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </div>
                                    <button type="button" x-show="address" @click="clearAddress()" style="display:none;"
                                        class="absolute inset-y-0 right-0 flex items-center px-3 text-gray-400 hover:text-red-500 dark:hover:text-red-400 transition-colors">
                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </div>
                                <ul x-show="suggestions.length > 0" style="display:none;"
                                    class="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md shadow-lg mt-1 max-h-56 overflow-y-auto">
                                    <template x-for="(item, index) in suggestions" :key="index">
                                        <li @click="selectAddress(item)"
                                            class="px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer flex items-start gap-2 border-b dark:border-gray-700 last:border-0 transition-colors">
                                            <svg class="h-4 w-4 text-indigo-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            <span class="text-sm text-gray-800 dark:text-gray-200" x-text="item.text"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-auto">
                                Pins the firewall on the map view. Click × to change the address.
                            </p>

                            <input type="hidden" name="address"   x-model="address">
                            <input type="hidden" name="latitude"  x-model="lat">
                            <input type="hidden" name="longitude" x-model="lon">

                            @error('address')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                        </div>
                    </div>

                </div>{{-- /grid --}}

                <div class="mt-6 flex items-center gap-3">
                    <x-primary-button>{{ __('Save Changes') }}</x-primary-button>
                    <a href="{{ route('firewall.dashboard', $firewall) }}">
                        <x-secondary-button>{{ __('Cancel') }}</x-secondary-button>
                    </a>
                </div>

            </form>
        </div>
    </div>

    <script>
        function addressAutocomplete() {
            return {
                searchQuery: '{{ old('address', $firewall->address) }}',
                address:     '{{ old('address', $firewall->address) }}',
                suggestions: [],
                lat:         '{{ old('latitude',  $firewall->latitude) }}',
                lon:         '{{ old('longitude', $firewall->longitude) }}',
                loading:     false,

                searchAddress() {
                    if (this.address) return;
                    if (this.searchQuery.length < 3) { this.suggestions = []; return; }
                    this.loading = true;
                    fetch(`{{ route('geocode.suggest') }}?q=${encodeURIComponent(this.searchQuery)}`)
                        .then(r => r.json())
                        .then(data => { this.suggestions = data.suggestions; this.loading = false; })
                        .catch(() => { this.loading = false; });
                },

                selectAddress(item) {
                    this.loading = true;
                    fetch(`{{ route('geocode.retrieve') }}?magicKey=${item.magicKey}`)
                        .then(r => r.json())
                        .then(data => {
                            this.address     = data.address;
                            this.searchQuery = data.address;
                            this.lat         = data.location.y;
                            this.lon         = data.location.x;
                            this.loading     = false;
                        });
                    this.suggestions = [];
                },

                clearAddress() {
                    this.address = this.searchQuery = this.lat = this.lon = '';
                    this.suggestions = [];
                    this.$nextTick(() => this.$el.querySelector('input[type=text]').focus());
                },
            };
        }
    </script>
</x-app-layout>