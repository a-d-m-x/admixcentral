<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Add Firewall') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form action="{{ route('firewalls.store') }}" method="POST">
                        @csrf

                        {{-- Company Selection --}}
                        @if($companies->count() > 1 || auth()->user()->isGlobalAdmin())
                            <div class="mb-4" x-data="{
                                    open: false,
                                    filter: '',
                                    selectedId: '{{ old('company_id', request('company_id')) }}',
                                    selectedName: '',
                                    companies: {{ $companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()->toJson() }},
                                    init() {
                                        if (this.selectedId) {
                                            const found = this.companies.find(c => c.id == this.selectedId);
                                            if (found) this.selectedName = found.name;
                                        }
                                        this.$watch('open', value => {
                                            if (value) this.$nextTick(() => this.$refs.search.focus());
                                        });
                                    },
                                    get filteredCompanies() {
                                        if (this.filter === '') return this.companies;
                                        return this.companies.filter(c => c.name.toLowerCase().includes(this.filter.toLowerCase()));
                                    },
                                    select(company) {
                                        this.selectedId = company.id;
                                        this.selectedName = company.name;
                                        this.open = false;
                                        this.filter = '';
                                    }
                                }" @click.outside="open = false">
                                <label
                                    class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Company</label>

                                <input type="hidden" name="company_id" :value="selectedId">

                                <div class="relative">
                                    <button @click="open = !open" type="button"
                                        class="relative w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-md shadow-sm pl-3 pr-10 py-2 text-left cursor-default focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        aria-haspopup="listbox" :aria-expanded="open" aria-labelledby="listbox-label">
                                        <span class="block truncate" x-text="selectedName || 'Select Company...'"
                                            :class="{'text-gray-500': !selectedId, 'text-gray-900 dark:text-gray-300': selectedId}"></span>
                                        <span class="absolute inset-y-0 right-0 flex items-center pr-2 pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                                                viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                <path fill-rule="evenodd"
                                                    d="M10 3a1 1 0 01.707.293l3 3a1 1 0 01-1.414 1.414L10 5.414 7.707 7.707a1 1 0 01-1.414-1.414l3-3A1 1 0 0110 3zm-3.707 9.293a1 1 0 011.414 0L10 14.586l2.293-2.293a1 1 0 011.414 1.414l-3 3a1 1 0 01-1.414 0l-3-3a1 1 0 010-1.414z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                        </span>
                                    </button>

                                    <ul x-show="open" x-transition:leave="transition ease-in duration-100"
                                        x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                                        class="absolute z-10 mt-1 w-full bg-white dark:bg-gray-900 shadow-lg max-h-60 rounded-md py-1 text-base ring-1 ring-black ring-opacity-5 overflow-auto focus:outline-none sm:text-sm"
                                        tabindex="-1" role="listbox" aria-labelledby="listbox-label" style="display: none;">

                                        <div
                                            class="sticky top-0 z-10 bg-white dark:bg-gray-900 px-2 py-2 border-b border-gray-200 dark:border-gray-700">
                                            <input x-model="filter" x-ref="search" type="text"
                                                class="w-full border-gray-300 dark:border-gray-600 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm bg-gray-50 dark:bg-gray-800 dark:text-white"
                                                placeholder="Search...">
                                        </div>

                                        <template x-for="company in filteredCompanies" :key="company.id">
                                            <li @click="select(company)"
                                                class="cursor-pointer select-none relative py-2 pl-3 pr-9 hover:bg-indigo-600 hover:text-white text-gray-900 dark:text-gray-300"
                                                role="option">
                                                <span class="block truncate" x-text="company.name"
                                                    :class="{'font-semibold': selectedId == company.id, 'font-normal': selectedId != company.id}"></span>

                                                <span x-show="selectedId == company.id"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-4 text-indigo-600 hover:text-white">
                                                    <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg"
                                                        viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                                        <path fill-rule="evenodd"
                                                            d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                            clip-rule="evenodd" />
                                                    </svg>
                                                </span>
                                            </li>
                                        </template>

                                        <li x-show="filteredCompanies.length === 0"
                                            class="text-gray-500 p-3 text-center text-sm italic">
                                            No matches found
                                        </li>
                                    </ul>
                                </div>
                                @error('company_id')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        @else
                            {{-- Single company available (Company Admin) --}}
                            <input type="hidden" name="company_id" value="{{ $companies->first()->id }}">
                            <div class="mb-4">
                                <label
                                    class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Company</label>
                                <input type="text" disabled value="{{ $companies->first()->name }}"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-700 dark:text-gray-400">
                            </div>
                        @endif

                        <div class="mb-4">
                            <label for="name"
                                class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Firewall
                                Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="e.g., Office Firewall">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="mb-4">
                            <label for="url"
                                class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">URL</label>
                            <input type="url" name="url" id="url" value="{{ old('url') }}" required
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                placeholder="https://192.168.1.1:443">
                            @error('url')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div x-data="{ authMethod: '{{ old('auth_method', 'basic') }}' }">
                            <div class="mb-4">
                                <label for="auth_method"
                                    class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Authentication
                                    Method</label>
                                <select name="auth_method" id="auth_method" x-model="authMethod"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="basic">Basic Auth (Username/Password)</option>
                                    <option value="token">Bearer Token</option>
                                </select>
                            </div>

                            <div x-show="authMethod === 'basic'">
                                <div class="mb-4">
                                    <label for="api_key"
                                        class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">API
                                        Username</label>
                                    <input type="text" name="api_key" id="api_key" value="{{ old('api_key') }}"
                                        :required="authMethod === 'basic'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="admin">
                                    @error('api_key')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="mb-4">
                                    <label for="api_secret"
                                        class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">API
                                        Password</label>
                                    <input type="password" name="api_secret" id="api_secret"
                                        :required="authMethod === 'basic'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    @error('api_secret')
                                        <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div x-show="authMethod === 'token'">
                                <div class="mb-4">
                                    <label for="api_token"
                                        class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">API
                                        Token</label>
                                    <textarea name="api_token" id="api_token" rows="3"
                                        :required="authMethod === 'token'"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                        placeholder="ey..."></textarea>
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
                                <input type="number" name="ssh_port" id="ssh_port" value="{{ old('ssh_port', 22) }}" required
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                @error('ssh_port')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="mb-4">
                                <label for="ssh_username" class="block text-sm font-medium mb-2">SSH Username</label>
                                <input type="text" name="ssh_username" id="ssh_username" value="{{ old('ssh_username') }}"
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
                                    placeholder="Enter SSH password">
                                @error('ssh_password')
                                    <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <div class="mb-4">
                            <label for="description"
                                class="block text-sm font-medium mb-2 text-gray-700 dark:text-gray-300">Description
                                (Optional)</label>
                            <textarea name="description" id="description" rows="3"
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">{{ old('description') }}</textarea>
                            @error('description')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex gap-3">
                            <x-primary-button>
                                {{ __('Create Firewall') }}
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
</x-app-layout>