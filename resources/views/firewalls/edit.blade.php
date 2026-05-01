<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Firewall') }}: {{ $firewall->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form action="{{ route('firewalls.update', $firewall) }}" method="POST">
                        @csrf
                        @method('PUT')

                        @if(auth()->user()->isGlobalAdmin())
                            <div class="mb-4">
                                <label for="company_id" class="block text-sm font-medium mb-2">Company</label>
                                <select name="company_id" id="company_id" required
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}" {{ (old('company_id', $firewall->company_id) == $company->id) ? 'selected' : '' }}>
                                            {{ $company->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('company_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            <input type="hidden" name="company_id" value="{{ $firewall->company_id }}">
                        @endif

                        <div class="mb-4">
                            <label for="name" class="block text-sm font-medium mb-2">Firewall Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $firewall->name) }}" required
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="url" class="block text-sm font-medium mb-2">URL</label>
                            <input type="url" name="url" id="url" value="{{ old('url', $firewall->url) }}" required
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                            @error('url')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-data="{ authMethod: '{{ old('auth_method', $firewall->auth_method ?? 'basic') }}' }">
                            <div class="mb-4">
                                <label for="auth_method" class="block text-sm font-medium mb-2">Authentication
                                    Method</label>
                                <select name="auth_method" id="auth_method" x-model="authMethod"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    <option value="basic">Basic Auth (Username/Password)</option>
                                    <option value="token">Bearer Token</option>
                                </select>
                            </div>

                            <div x-show="authMethod === 'basic'">
                                <div class="mb-4">
                                    <label for="api_key" class="block text-sm font-medium mb-2">API Username</label>
                                    <input type="text" name="api_key" id="api_key"
                                        value="{{ old('api_key', $firewall->api_key) }}"
                                        :required="authMethod === 'basic'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                    @error('api_key')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="api_secret" class="block text-sm font-medium mb-2">API Password</label>
                                    <input type="password" name="api_secret" id="api_secret"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        placeholder="Enter new password or leave unchanged">
                                    <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
                                    @error('api_secret')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div x-show="authMethod === 'token'">
                                <div class="mb-4">
                                    <label for="api_token" class="block text-sm font-medium mb-2">API Token</label>
                                    <textarea name="api_token" id="api_token" rows="3"
                                        :required="authMethod === 'token'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                        placeholder="ey...">{{ old('api_token', $firewall->api_token) }}</textarea>
                                    @error('api_token')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 mb-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">SSH Access for Config Backup</h3>
                            
                            <div class="mb-4">
                                <label for="ssh_port" class="block text-sm font-medium mb-2">SSH Port</label>
                                <input type="number" name="ssh_port" id="ssh_port" value="{{ old('ssh_port', $firewall->ssh_port ?? 22) }}" required
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @error('ssh_port')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="ssh_username" class="block text-sm font-medium mb-2">SSH Username</label>
                                <input type="text" name="ssh_username" id="ssh_username" value="{{ old('ssh_username', $firewall->ssh_username) }}"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    placeholder="admin">
                                @error('ssh_username')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="ssh_password" class="block text-sm font-medium mb-2">SSH Password</label>
                                <input type="password" name="ssh_password" id="ssh_password"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300"
                                    placeholder="Enter new password or leave unchanged">
                                <p class="text-xs text-gray-500 mt-1">Leave blank to keep current password</p>
                                @error('ssh_password')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Location Search & Address Wrapper -->
                        <div x-data="addressAutocomplete()">
                            <!-- Location Search Helper -->
                            <div class="mb-2 relative" @click.outside="suggestions = []">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Find
                                    Location (Auto-fill)</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                        <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                                            viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd"
                                                d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <input type="text" x-model="searchQuery" @input.debounce.300ms="searchAddress()"
                                        class="pl-10 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="Search for an address to auto-complete..." autocomplete="off">

                                    <div x-show="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                        <svg class="animate-spin h-5 w-5 text-indigo-500"
                                            xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                                stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor"
                                                d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                            </path>
                                        </svg>
                                    </div>
                                </div>

                                <!-- Suggestions Dropdown -->
                                <ul x-show="suggestions.length > 0"
                                    class="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto"
                                    style="display: none;">
                                    <template x-for="(item, index) in suggestions" :key="index">
                                        <li @click="selectAddress(item)"
                                            class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b dark:border-gray-700 last:border-0 transition-colors duration-150">
                                            <div class="flex items-start">
                                                <svg class="h-5 w-5 text-indigo-500 mt-0.5 mr-2 flex-shrink-0"
                                                    fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                    </path>
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                </svg>
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100"
                                                        x-text="item.text"></p>
                                                </div>
                                            </div>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <!-- Actual Address Field -->
                            <div class="mb-4">
                                <label for="address"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 flex justify-between">
                                    <span>Address (Auto-filled)</span>
                                    <div class="flex items-center gap-2">
                                        <template x-if="address">
                                            <button type="button" @click="clearAddress()"
                                                class="text-xs text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                                Clear
                                            </button>
                                        </template>
                                        <span class="text-xs font-normal"
                                            :class="lat ? 'text-green-600 dark:text-green-400' : 'text-gray-500'">
                                            <span x-show="lat" class="flex items-center"><svg class="w-3 h-3 mr-1"
                                                    fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"
                                                        clip-rule="evenodd"></path>
                                                </svg> Map Location Linked</span>
                                            <span x-show="!lat">No map location linked</span>
                                        </span>
                                    </div>
                                </label>
                                <div class="mt-1 block w-full rounded-md shadow-sm border border-gray-300 dark:border-gray-700 bg-gray-50 dark:bg-gray-900 text-gray-500 dark:text-gray-400 px-3 py-2 text-sm select-none cursor-not-allowed opacity-75"
                                    x-text="address ? address : 'Search location above to auto-fill address...'"></div>
                                <input type="hidden" name="address" x-model="address">

                                <input type="hidden" name="latitude" x-model="lat">
                                <input type="hidden" name="longitude" x-model="lon">

                                @error('address')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="description" class="block text-sm font-medium mb-2">Description
                                (Optional)</label>
                            <textarea name="description" id="description" rows="3"
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">{{ old('description', $firewall->description) }}</textarea>
                            @error('description')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex gap-3">
                            <x-primary-button>
                                {{ __('Update Firewall') }}
                            </x-primary-button>
                            <a href="{{ route('firewalls.index') }}">
                                <x-secondary-button>
                                    {{ __('Cancel') }}
                                </x-secondary-button>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        function addressAutocomplete() {
            return {
                searchQuery: '',
                address: '{{ old('address', $firewall->address) }}',
                suggestions: [],
                lat: '{{ old('latitude', $firewall->latitude) }}',
                lon: '{{ old('longitude', $firewall->longitude) }}',
                loading: false,

                searchAddress() {
                    if (this.searchQuery.length < 3) {
                        this.suggestions = [];
                        return;
                    }

                    this.loading = true;
                    // Use 'Suggest' endpoint
                    fetch(`{{ route('geocode.suggest') }}?q=${encodeURIComponent(this.searchQuery)}`)
                        .then(response => response.json())
                        .then(data => {
                            this.suggestions = data.suggestions;
                            this.loading = false;
                        })
                        .catch(() => {
                            this.loading = false;
                        });
                },

                selectAddress(item) {
                    this.loading = true;
                    // Retrieve full details using magicKey
                    fetch(`{{ route('geocode.retrieve') }}?magicKey=${item.magicKey}`)
                        .then(response => response.json())
                        .then(data => {
                            this.address = data.address;
                            this.lat = data.location.y;
                            this.lon = data.location.x;
                            this.loading = false;
                        });

                    this.suggestions = [];
                    this.searchQuery = '';
                },

                clearAddress() {
                    this.address = '';
                    this.lat = '';
                    this.lon = '';
                    this.searchQuery = '';
                }
            }
        }
    </script>
</x-app-layout>