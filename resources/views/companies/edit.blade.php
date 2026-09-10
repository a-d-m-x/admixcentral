<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Edit Company') }}: {{ $company->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form action="{{ route('companies.update', $company) }}" method="POST"
                        x-data="addressAutocomplete()" @submit.prevent="submitForm($el)">
                        @csrf
                        @method('PUT')

                        <div class="mb-4">
                            <label for="name"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name', $company->name) }}" required
                                class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Location Search Helper -->
                        <div class="mb-2 relative">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Find Location
                                (Auto-fill)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                                <input type="text" x-model="searchQuery" @input.debounce.300ms="searchAddress()"
                                    placeholder="Search by name, street, or city..."
                                    class="pl-10 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 placeholder-gray-400 dark:placeholder-gray-500">

                                <!-- Loading Spinner -->
                                <div x-show="loading" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                    <svg class="animate-spin h-5 w-5 text-indigo-500" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </div>
                            </div>

                            <!-- Suggestions Dropdown -->
                            <ul x-show="suggestions.length > 0" @click.outside="suggestions = []"
                                class="absolute z-10 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-md shadow-lg mt-1 max-h-60 overflow-y-auto"
                                style="display: none;">
                                <template x-for="(item, index) in suggestions" :key="index">
                                    <li @click="selectAddress(item)"
                                        class="px-4 py-3 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer border-b dark:border-gray-700 last:border-0 transition-colors duration-150">
                                        <div class="flex items-start">
                                            <svg class="h-5 w-5 text-indigo-500 mt-0.5 mr-2 flex-shrink-0" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z">
                                                </path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path>
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

                            <!-- Hidden Inputs for Coordinates -->
                            <input type="hidden" name="latitude" x-model="lat">
                            <input type="hidden" name="longitude" x-model="lon">

                            @error('address')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="description"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <textarea name="description" id="description" rows="3"
                                class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('description', $company->description) }}</textarea>
                            @error('description')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-3">
                            <x-primary-button>{{ __('Update Company') }}</x-primary-button>
                            <a href="{{ route('companies.index') }}">
                                <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                            </a>
                        </div>
                    </form>

                    <script>
                        function addressAutocomplete() {
                            return {
                                searchQuery: '',
                                address: '{{ old('address', $company->address) }}',
                                suggestions: [],
                                lat: '{{ old('latitude', $company->latitude) }}',
                                lon: '{{ old('longitude', $company->longitude) }}',
                                loading: false,

                                searchAddress() {
                                    if (this.searchQuery.length < 3) {
                                        this.suggestions = [];
                                        return;
                                    }

                                    this.loading = true;
                                    // Use 'Suggest' endpoint for better autocomplete/type-ahead matching
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
                                    // Retrieve full details (lat/lon) using the magicKey
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

                                formatSuggestion(item) {
                                    return '';
                                },

                                clearAddress() {
                                    this.address = '';
                                    this.lat = '';
                                    this.lon = '';
                                    this.searchQuery = '';
                                },

                                submitForm(el) {
                                    el.submit();
                                }
                            }
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>