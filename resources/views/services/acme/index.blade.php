<x-app-layout>
    <x-slot name="header">
    <x-slot name="header">
        <x-firewall-header title="{{ __('ACME Certificates') }}" :firewall="$firewall" />
    </x-slot>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            
            {{-- Tabs --}}
            <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                    <a href="{{ route('services.acme.certificates', $firewall) }}"
                       class="{{ $tab === 'certificates' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Certificates
                    </a>
                    <a href="{{ route('services.acme.account-keys', $firewall) }}"
                       class="{{ $tab === 'account_keys' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        Account Keys
                    </a>
                    <a href="{{ route('services.acme.settings', $firewall) }}"
                       class="{{ $tab === 'settings' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                        General Settings
                    </a>
                </nav>
            </div>

            {{-- Validation Errors & Success Messages --}}
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Success!</strong>
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                    <strong class="font-bold">Error!</strong>
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif
            
            @if ($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <x-confirm-delete-modal>
                
                {{-- Account Keys Tab --}}
                @if($tab === 'account_keys')
                    <div class="mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Account Keys</h3>
                            <x-button-add @click="open = !open">
                                Add/Toggle Key Form
                            </x-button-add>
                        </div>
                        
                        {{-- Create Form --}}
                        <div x-data="{ open: false }" @toggle-create-key.window="open = !open" class="mb-6">

                            <div x-show="open" class="mt-4 p-4 border border-gray-200 dark:border-gray-700 rounded-lg">
                                <form action="{{ route('services.acme.account-keys.store', $firewall) }}" method="POST">
                                    @csrf
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="name" :value="__('Name')" />
                                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" required />
                                        </div>
                                        <div>
                                            <x-input-label for="descr" :value="__('Description')" />
                                            <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr" />
                                        </div>
                                        <div>
                                            <x-input-label for="email" :value="__('Email')" />
                                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" required />
                                        </div>
                                        <div>
                                            <x-input-label for="server" :value="__('ACME Server')" />
                                            <x-select-input id="server" name="server" class="block mt-1 w-full">
                                                <option value="letsencrypt-production-2">Let's Encrypt Production 2</option>
                                                <option value="letsencrypt-staging-2">Let's Encrypt Staging 2</option>
                                                <option value="google-production">Google Public CA</option>
                                                <option value="zerossl">ZeroSSL</option>
                                            </x-select-input>
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="register" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" checked>
                                            <span class="ml-2 text-gray-700 dark:text-gray-300">Create new account key (Register)</span>
                                        </label>
                                    </div>
                                    <div class="mt-4 flex justify-end">
                                        <x-primary-button>
                                            {{ __('Save & Register') }}
                                        </x-primary-button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        {{-- List --}}
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Name</th>
                                        <th scope="col" class="px-6 py-3">Description</th>
                                        <th scope="col" class="px-6 py-3">Server</th>
                                        <th scope="col" class="px-6 py-3">Status</th>
                                        <th scope="col" class="px-6 py-3">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($accountKeys as $key)
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $key['name'] ?? 'N/A' }}
                                            </td>
                                            <td class="px-6 py-4">{{ $key['descr'] ?? '' }}</td>
                                            <td class="px-6 py-4">{{ $key['server'] ?? '' }}</td>
                                            <td class="px-6 py-4">
                                                {{-- Status might be inferred or explicit --}}
                                                N/A
                                            </td>
                                            <td class="px-6 py-4 text-right">
                                                <button type="button" @click="openDelete('{{ route('services.acme.account-keys.destroy', ['firewall' => $firewall, 'id' => $key['id'] ?? $key['name']]) }}', '{{ addslashes($key['name'] ?? 'this key') }}')" class="font-medium text-red-600 dark:text-red-500 hover:underline">Delete</button>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                            <td colspan="5" class="px-6 py-4 text-center">No Account Keys found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                {{-- Certificates Tab --}}
                @if($tab === 'certificates')
                    <div class="mb-6">
                        {{-- Read-Only Warning Banner --}}
                        @if(isset($readOnly) && $readOnly)
                            <div class="mb-4 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                                <div class="flex items-start">
                                    <svg class="h-5 w-5 text-amber-500 mr-3 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                    <div>
                                        <h3 class="font-medium text-amber-800 dark:text-amber-200">Read-Only View</h3>
                                        <p class="text-sm text-amber-700 dark:text-amber-300 mt-1">
                                            ACME certificate management is read-only. The pfSense REST API does not expose ACME endpoints. 
                                            To manage certificates, please use the 
                                            <a href="https://{{ $firewall->ip_address ?? '' }}:{{ $firewall->port ?? '444' }}/acme/acme_certificates.php" 
                                               target="_blank" class="underline font-medium">pfSense Web Interface</a>.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        {{-- Error Display --}}
                        @if(isset($error) && $error)
                            <div class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                <p class="text-red-700 dark:text-red-300">{{ $error }}</p>
                            </div>
                        @endif

                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Certificates</h3>
                            <a href="https://{{ $firewall->ip_address ?? '' }}:{{ $firewall->port ?? '444' }}/acme/acme_certificates.php" 
                               target="_blank"
                               class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                <svg class="h-4 w-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                                </svg>
                                Manage in pfSense
                            </a>
                        </div>

                        {{-- Certificate List --}}
                        <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                            <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                    <tr>
                                        <th scope="col" class="px-6 py-3">Name</th>
                                        <th scope="col" class="px-6 py-3">Domains (SAN)</th>
                                        <th scope="col" class="px-6 py-3">Account Key</th>
                                        <th scope="col" class="px-6 py-3">Key Length</th>
                                        <th scope="col" class="px-6 py-3">Last Renewal</th>
                                        <th scope="col" class="px-6 py-3">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($certificates as $cert)
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                            <td class="px-6 py-4 font-medium text-gray-900 whitespace-nowrap dark:text-white">
                                                {{ $cert['name'] ?? 'N/A' }}
                                                @if(!empty($cert['descr']))
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $cert['descr'] }}</div>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 max-w-xs">
                                                @if(!empty($cert['san']))
                                                    <div class="text-xs text-gray-600 dark:text-gray-300 break-words" title="{{ $cert['san'] }}">
                                                        {{ Str::limit($cert['san'], 50) }}
                                                    </div>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4">{{ $cert['acme_account_key'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4">{{ $cert['keylength'] ?? '2048' }} bit</td>
                                            <td class="px-6 py-4">
                                                @if(!empty($cert['lastrenewal']))
                                                    {{ $cert['lastrenewal'] }}
                                                @elseif(!empty($cert['lastrenewtime']))
                                                    {{ date('Y-m-d H:i', (int)$cert['lastrenewtime']) }}
                                                @else
                                                    <span class="text-gray-400">Never</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4">
                                                @php
                                                    $status = $cert['status'] ?? 'unknown';
                                                    $statusClass = match($status) {
                                                        'active' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300',
                                                        'disabled' => 'bg-gray-100 text-gray-800 dark:bg-gray-600 dark:text-gray-300',
                                                        default => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300',
                                                    };
                                                @endphp
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusClass }}">
                                                    {{ ucfirst($status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                            <td colspan="6" class="px-6 py-4 text-center">
                                                No ACME Certificates found. The ACME package may not be installed or configured.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- Account Keys Section --}}
                        @if(count($accountKeys) > 0)
                            <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mt-8 mb-4">Account Keys</h4>
                            <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
                                <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                        <tr>
                                            <th scope="col" class="px-6 py-3">Name</th>
                                            <th scope="col" class="px-6 py-3">Email</th>
                                            <th scope="col" class="px-6 py-3">ACME Server</th>
                                            <th scope="col" class="px-6 py-3">Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($accountKeys as $key)
                                            <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                                <td class="px-6 py-4 font-medium text-gray-900 dark:text-white">
                                                    {{ $key['name'] ?? 'N/A' }}
                                                    @if(!empty($key['descr']))
                                                        <div class="text-xs text-gray-500">{{ $key['descr'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="px-6 py-4">{{ $key['email'] ?? '-' }}</td>
                                                <td class="px-6 py-4">{{ $key['acmeserver'] ?? '-' }}</td>
                                                <td class="px-6 py-4">
                                                    @if(($key['accountkey'] ?? '') === 'Registered')
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-300">
                                                            Registered
                                                        </span>
                                                    @else
                                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-300">
                                                            Not Registered
                                                        </span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @endif

                {{-- Settings Tab --}}
                @if($tab === 'settings')
                    <div class="mb-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">General Settings</h3>
                        <form action="{{ route('services.acme.settings.update', $firewall) }}" method="POST">
                            @csrf
                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="cron_entry" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ isset($settings['cron_entry']) ? 'checked' : '' }}>
                                        <span class="ml-2 text-gray-700 dark:text-gray-300">Enable Cron Entry for Auto-Renewal</span>
                                    </label>
                                    <p class="text-sm text-gray-500 mt-1 ml-6">Automatically attempts to renew certificates that are expiring.</p>
                                </div>
                                
                                <div>
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="write_certificates" value="1" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ isset($settings['write_certificates']) ? 'checked' : '' }}>
                                        <span class="ml-2 text-gray-700 dark:text-gray-300">Write Certificates to Configuration</span>
                                    </label>
                                </div>
                            </div>
                            <div class="mt-6 flex justify-end">
                                <x-primary-button>
                                    {{ __('Update Settings') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                @endif
                
                </x-confirm-delete-modal>
            </div>
        </div>
    </div>
</x-app-layout>
