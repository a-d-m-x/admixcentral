<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Users') }}
            </h2>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            {{-- Stats Widgets --}}
            <div
                class="grid grid-cols-1 md:grid-cols-2 {{ auth()->user()->isGlobalAdmin() ? 'lg:grid-cols-4' : 'lg:grid-cols-2' }} gap-4 mb-6">
                <!-- Total Users -->
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex justify-between items-center">
                    <div>
                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Total Users</div>
                        <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</div>
                    </div>
                    <div class="p-3 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-500 dark:text-gray-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Users -->
                @if(auth()->user()->isGlobalAdmin())
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex justify-between items-center">
                        <div>
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Standard Users</div>
                            <div class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['users'] }}</div>
                        </div>
                        <div class="p-3 rounded-full bg-green-100 dark:bg-green-900/20 text-green-600 dark:text-green-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                            </svg>
                        </div>
                    </div>
                @endif

                <!-- Admins -->
                <div
                    class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex justify-between items-center">
                    <div>
                        <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Admins</div>
                        <div class="mt-2 text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['admins'] }}
                        </div>
                    </div>
                    <div class="p-3 rounded-full bg-blue-100 dark:bg-blue-900/20 text-blue-600 dark:text-blue-400">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                            stroke="currentColor" class="w-6 h-6">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M15 9h3.75M15 12h3.75M15 15h3.75M4.5 19.5h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5zm6-10.125a1.875 1.875 0 11-3.75 0 1.875 1.875 0 013.75 0zm1.294 6.336a6.721 6.721 0 01-3.17.789 6.721 6.721 0 01-3.168-.789 3.376 3.376 0 016.338 0z" />
                        </svg>
                    </div>
                </div>

                <!-- Global Admins -->
                @if(auth()->user()->isGlobalAdmin())
                    <div
                        class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 flex justify-between items-center">
                        <div>
                            <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                Global Admins</div>
                            <div class="mt-2 text-3xl font-bold text-purple-600 dark:text-purple-400">
                                {{ $stats['global_admins'] }}
                            </div>
                        </div>
                        <div
                            class="p-3 rounded-full bg-purple-100 dark:bg-purple-900/20 text-purple-600 dark:text-purple-400">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5"
                                stroke="currentColor" class="w-6 h-6">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                    d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" />
                            </svg>
                        </div>
                    </div>
                @endif
            </div>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                @php
                    $uniqueCompanies = $users->pluck('company.name')->unique()->sort()->values();
                @endphp
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg" x-data="{
                search: '',
                roleFilter: 'all',
                companyFilter: 'all',
                sortBy: 'name',
                sortAsc: true,
                companies: {{ json_encode($uniqueCompanies) }},
                selectedIds: [],
                bulkAction: '',
                users: [],
                authUserId: {{ auth()->id() }},
                csrfToken: '{{ csrf_token() }}',
                
                init() {
                    this.users = {{ json_encode($users->map(fn($u) => [
    'id' => $u->id,
    'name' => $u->name,
    'email' => $u->email,
    'role' => $u->role,
    'role_type' => ($u->role === 'admin' && !$u->company_id) ? 'global_admin' : (($u->role === 'admin') ? 'company_admin' : 'user'),
    'role_label' => ($u->role === 'admin' && !$u->company_id) ? 'Global Admin' : (($u->role === 'admin') ? 'Company Admin' : 'User'),
    'company_id' => $u->company_id,
    'company_name' => $u->company ? $u->company->name : 'MSP / Global',
    'company_url' => $u->company ? route('companies.show', $u->company) : null,
    'edit_url' => route('users.edit', $u),
    'delete_url' => route('users.destroy', $u)
])) }};
                },

                get filteredRows() {
                    let result = this.users.filter(u => {
                        const matchesSearch = this.search === '' || 
                            u.name.toLowerCase().includes(this.search.toLowerCase()) || 
                            u.email.toLowerCase().includes(this.search.toLowerCase());
                        
                        const matchesRole = this.roleFilter === 'all' || u.role === this.roleFilter;
                        
                        // Handle Company Filter
                        // If companyFilter is 'all', show all.
                        // If specific company selected, match company_name.
                        const matchesCompany = this.companyFilter === 'all' || u.company_name === this.companyFilter;

                        return matchesSearch && matchesRole && matchesCompany;
                    });
                    
                    return result.sort((a, b) => {
                        let valA = a[this.sortBy];
                        let valB = b[this.sortBy];
                        
                        if (typeof valA === 'string') valA = valA.toLowerCase();
                        if (typeof valB === 'string') valB = valB.toLowerCase();
                        
                        if (valA < valB) return this.sortAsc ? -1 : 1;
                        if (valA > valB) return this.sortAsc ? 1 : -1;
                        return 0;
                    });
                },

                get allFilteredIds() {
                    return this.filteredRows.map(u => u.id);
                },

                toggleAll() {
                    const visibleIds = this.allFilteredIds.filter(id => id !== this.authUserId);
                    const allSelected = visibleIds.length > 0 && visibleIds.every(id => this.selectedIds.includes(id));
                    
                    if (allSelected) {
                        this.selectedIds = this.selectedIds.filter(id => !visibleIds.includes(id));
                    } else {
                        const newIds = visibleIds.filter(id => !this.selectedIds.includes(id));
                        this.selectedIds = [...this.selectedIds, ...newIds];
                    }
                },

                submitBulkAction() {
                    if (!this.bulkAction) return;
                    if (this.selectedIds.length === 0) {
                        alert('Please select at least one user.');
                        return;
                    }
                    
                    if (confirm('Are you sure you want to perform this action on ' + this.selectedIds.length + ' users?')) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '{{ route('users.bulk-action') }}';
                        
                        const csrfInput = document.createElement('input');
                        csrfInput.type = 'hidden';
                        csrfInput.name = '_token';
                        csrfInput.value = this.csrfToken;
                        form.appendChild(csrfInput);
                        
                        const actionInput = document.createElement('input');
                        actionInput.type = 'hidden';
                        actionInput.name = 'action';
                        actionInput.value = this.bulkAction;
                        form.appendChild(actionInput);
                        
                        this.selectedIds.forEach(id => {
                            const idInput = document.createElement('input');
                            idInput.type = 'hidden';
                            idInput.name = 'ids[]';
                            idInput.value = id;
                            form.appendChild(idInput);
                        });
                        
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            }">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="flex flex-col space-y-4 mb-6">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                    {{ __('Managed Users') }}
                                </h3>
                                @if(!auth()->user()->isUser())
                                    <a href="{{ route('users.create') }}"
                                        class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-medium text-sm text-white hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"
                                            xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 4v16m8-8H4"></path>
                                        </svg>
                                        Add User
                                    </a>
                                @endif
                            </div>

                            <!-- Toolbar -->
                            <div
                                class="flex flex-col lg:flex-row gap-4 justify-between items-start lg:items-center bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg mb-6">

                                <!-- Left Side: Filters & Search -->
                                <div class="flex flex-col sm:flex-row gap-4 w-full lg:flex-1">
                                    <!-- Search -->
                                    <div class="relative w-full sm:w-64 lg:w-auto lg:flex-1">
                                        <div
                                            class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <svg class="h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg"
                                                fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                        </div>
                                        <input type="text" x-model="search" placeholder="Search users..."
                                            class="pl-10 w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100 placeholder-gray-500 dark:placeholder-gray-400 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                            @input="filteredUsers">
                                        <button x-show="search.length > 0" @click="search = ''; filteredUsers"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-500 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none"
                                            style="display: none;">
                                            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none"
                                                viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M6 18L18 6M6 6l12 12" />
                                            </svg>
                                        </button>
                                    </div>

                                    <!-- Filters -->
                                    <div class="flex gap-2 w-full sm:w-auto shrink-0">
                                        <!-- Role Filter -->
                                        <select x-model="roleFilter" @change="filteredUsers"
                                            class="w-1/2 sm:w-auto rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 text-sm focus:ring-indigo-500 focus:border-indigo-500">
                                            <option value="all">All Roles</option>
                                            <option value="admin">Admins</option>
                                            <option value="user">Users</option>
                                        </select>

                                        <!-- Company Filter (Only if Global Admin) -->
                                        @if(auth()->user()->isGlobalAdmin())
                                            <div class="relative w-1/2 sm:w-auto min-w-[160px]"
                                                x-data="{ open: false, filter: '' }">
                                                <button @click="open = !open" type="button"
                                                    class="flex items-center justify-between w-full rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 dark:text-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                                    <span x-text="companyFilter === 'all' ? 'All Companies' : companyFilter"
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
                                                    <div @click="companyFilter = 'all'; filteredUsers; open = false"
                                                        class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm">
                                                        All Companies</div>
                                                    <template
                                                        x-for="c in companies.filter(x => x && x.toLowerCase().includes(filter.toLowerCase()))"
                                                        :key="c">
                                                        <div @click="companyFilter = c; filteredUsers; open = false"
                                                            class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm truncate"
                                                            x-text="c"></div>
                                                    </template>
                                                </div>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Right Side: Bulk Actions -->
                                <div
                                    class="flex gap-2 w-full lg:w-auto items-center border-t lg:border-t-0 lg:border-l lg:pl-4 pt-4 lg:pt-0 border-gray-200 dark:border-gray-600">
                                    <span class="text-sm text-gray-500 whitespace-nowrap hidden xl:inline">With
                                        selected:</span>
                                    <div class="flex gap-2 w-full">
                                        @if(!auth()->user()->isUser())
                                            <select x-model="bulkAction"
                                                class="block w-full lg:w-40 rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-900 dark:border-gray-700 dark:text-gray-300 sm:text-sm">
                                                <option value="">Actions...</option>
                                                <option value="delete">Delete</option>
                                            </select>
                                            <x-secondary-button type="button" @click="submitBulkAction" class="rounded-lg">
                                                Apply
                                            </x-secondary-button>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                    <thead class="bg-gray-50 dark:bg-gray-700">
                                        <tr>
                                            <th class="px-6 py-3 text-left">
                                                @if(!auth()->user()->isUser())
                                                    <input type="checkbox" @click="toggleAll"
                                                        :checked="selectedIds.length > 0 && allFilteredIds.length > 0 && allFilteredIds.every(id => selectedIds.includes(id))">
                                                @endif
                                            </th>
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
                                                class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                                @click="sortBy = 'email'; sortAsc = !sortAsc">
                                                <div class="flex items-center gap-1">
                                                    Email
                                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                        :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'email', 'rotate-180': sortBy === 'email' && !sortAsc }"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </div>
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                                @click="sortBy = 'role_label'; sortAsc = !sortAsc">
                                                <div class="flex items-center gap-1">
                                                    Role
                                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                        :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'role_label', 'rotate-180': sortBy === 'role_label' && !sortAsc }"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </div>
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none group"
                                                @click="sortBy = 'company_name'; sortAsc = !sortAsc">
                                                <div class="flex items-center gap-1">
                                                    Company
                                                    <svg class="w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:group-hover:text-gray-200 transition-colors"
                                                        :class="{ 'text-indigo-600 dark:text-indigo-400': sortBy === 'company_name', 'rotate-180': sortBy === 'company_name' && !sortAsc }"
                                                        fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                                    </svg>
                                                </div>
                                            </th>
                                            <th scope="col"
                                                class="px-6 py-3 text-right text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody
                                        class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                        <template x-for="user in filteredRows" :key="user.id">
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    @if(!auth()->user()->isUser())
                                                        <div x-show="user.id !== authUserId">
                                                            <input type="checkbox" :value="user.id" x-model="selectedIds">
                                                        </div>
                                                        <div x-show="user.id === authUserId">
                                                            <input type="checkbox" disabled
                                                                class="opacity-50 cursor-not-allowed">
                                                        </div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="font-medium text-gray-900 dark:text-white"
                                                        x-text="user.name"></div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"
                                                    x-text="user.email"></td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <span
                                                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                                        :class="{
                                                        'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200': user.role_type === 'global_admin',
                                                        'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200': user.role_type === 'company_admin',
                                                        'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300': user.role_type === 'user'
                                                    }" x-text="user.role_label">
                                                    </span>
                                                </td>
                                                <td
                                                    class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                    <template x-if="user.company_url">
                                                        <a :href="user.company_url"
                                                            class="text-indigo-600 hover:text-indigo-900 hover:underline dark:text-indigo-400 dark:hover:text-indigo-300"
                                                            x-text="user.company_name">
                                                        </a>
                                                    </template>
                                                    <template x-if="!user.company_url">
                                                        <span class="text-gray-500 italic dark:text-gray-600"
                                                            x-text="user.company_name"></span>
                                                    </template>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                    <div class="flex justify-end gap-2">
                                                        @if(!auth()->user()->isUser())
                                                            <a :href="user.edit_url"
                                                                class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">Edit</a>

                                                            <template x-if="user.id !== authUserId">
                                                                <form :action="user.delete_url" method="POST"
                                                                    @submit.prevent="if(confirm('Are you sure you want to delete ' + user.name + '?')) $el.submit()">
                                                                    <input type="hidden" name="_token" :value="csrfToken">
                                                                    <input type="hidden" name="_method" value="DELETE">
                                                                    <button type="submit"
                                                                        class="text-red-600 dark:text-red-400 hover:text-red-900">Delete</button>
                                                                </form>
                                                            </template>
                                                        @else
                                                            <span
                                                                class="text-gray-400 dark:text-gray-500 text-xs italic">Read
                                                                Only</span>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        </template>
                                        <tr x-show="filteredRows.length === 0">
                                            <td colspan="6"
                                                class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                                No users found.
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
</x-app-layout>