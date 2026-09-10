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
            <form action="{{ route('firewalls.update', $firewall) }}" method="POST" enctype="multipart/form-data"
                  x-data="firewallEdit()"
                  @submit.prevent="handleSubmit($el)">
                @csrf
                @method('PUT')

                {{-- 2-column grid on lg+, stacked on mobile --}}
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

                    {{-- ── Identity ─────────────────────────────────────── --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2.5">
                            <span class="flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 text-[10px] font-bold shrink-0">1</span>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Identity</h3>
                        </div>
                        <div class="px-6 py-5 flex flex-col gap-4 flex-1">

                            {{-- Platform / OS --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Operating System / Platform</label>
                                <div class="grid grid-cols-2 gap-3">
                                    <label class="flex items-center p-2.5 border rounded-lg cursor-pointer transition"
                                           :class="osType === 'pfsense' ? 'border-indigo-600 bg-indigo-50/50 dark:bg-indigo-950/30 ring-2 ring-indigo-500' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" name="os_type" value="pfsense" x-model="osType" class="text-indigo-600 focus:ring-indigo-500">
                                        <img src="https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/pfsense.svg"
                                             alt="pfSense" width="22" height="22"
                                             class="ml-2 shrink-0 rounded">
                                        <div class="ml-2">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">pfSense</span>
                                            <span class="block text-[10px] text-gray-500 dark:text-gray-400">pfRest API</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center p-2.5 border rounded-lg cursor-pointer transition"
                                           :class="osType === 'opnsense' ? 'border-amber-600 bg-amber-50/50 dark:bg-amber-950/30 ring-2 ring-amber-500' : 'border-gray-300 dark:border-gray-700'">
                                        <input type="radio" name="os_type" value="opnsense" x-model="osType" class="text-amber-600 focus:ring-amber-500">
                                        <img src="https://cdn.jsdelivr.net/gh/homarr-labs/dashboard-icons/svg/opnsense.svg"
                                             alt="OPNsense" width="22" height="22"
                                             class="ml-2 shrink-0 rounded">
                                        <div class="ml-2">
                                            <span class="block text-xs font-semibold text-gray-900 dark:text-gray-100">OPNsense</span>
                                            <span class="block text-[10px] text-gray-500 dark:text-gray-400">Native Core API</span>
                                        </div>
                                    </label>
                                </div>
                            </div>

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
                                <textarea name="description" id="description" rows="2"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm resize-y">{{ old('description', $firewall->description) }}</textarea>
                                @error('description')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                        </div>
                    </div>

                    {{-- ── API Connection ───────────────────────────────── --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex flex-col"
                         x-data="{ authMethod: {{ Illuminate\Support\Js::from(old('auth_method', $firewall->auth_method ?? 'basic')) }} }">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2.5">
                            <span class="flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 text-[10px] font-bold shrink-0">2</span>
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

                            @include('firewalls.partials.tls-trust')

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
                                    <label for="api_key" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                           x-text="osType === 'opnsense' ? 'OPNsense API Key' : 'API Username'"></label>
                                    <input type="text" name="api_key" id="api_key"
                                        value="{{ old('api_key', $firewall->os_type === 'opnsense' ? '' : $firewall->api_key) }}"
                                        placeholder="{{ !empty($firewall->api_key) ? 'Configured (Leave blank to keep current)' : 'Enter API Key / Username' }}"
                                        class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                                    @if(!empty($firewall->api_key) && $firewall->os_type === 'opnsense')
                                        <p class="flex items-center gap-1 text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Configured — leave blank to keep current
                                        </p>
                                    @endif
                                    @error('api_key')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <label for="api_secret" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1"
                                           x-text="osType === 'opnsense' ? 'OPNsense API Secret' : 'API Password'"></label>
                                    <div class="relative" x-data="{ show: false }">
                                        <input :type="show ? 'text' : 'password'" name="api_secret" id="api_secret"
                                            placeholder="Leave blank to keep current"
                                            class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm pr-9">
                                        <button type="button" @click="show = !show"
                                                class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                                :title="show ? 'Hide' : 'Show'">
                                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                        </button>
                                    </div>
                                    @if(!empty($firewall->api_secret))
                                        <p class="flex items-center gap-1 text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                                            <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Configured — leave blank to keep current
                                        </p>
                                    @endif
                                    @error('api_secret')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div x-show="authMethod === 'token'" x-cloak>
                                <label for="api_token" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Bearer Token</label>
                                <textarea name="api_token" id="api_token" rows="3"
                                    placeholder="{{ !empty($firewall->api_token) ? 'Configured (Leave blank to keep current)' : 'ey…' }}"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono"></textarea>
                                @if(!empty($firewall->api_token))
                                    <p class="flex items-center gap-1 text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                                        <svg class="w-3 h-3 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                        Configured — leave blank to keep current
                                    </p>
                                @endif
                                @error('api_token')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                            </div>

                        </div>
                    </div>

                    {{-- ── SSH Backup ────────────────────────────────────── --}}
                    <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg flex flex-col">
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-2.5">
                            <span class="flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 text-[10px] font-bold shrink-0">3</span>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">SSH — Config Backup</h3>
                            @if(!$firewall->ssh_username && !$firewall->ssh_password)
                                <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-gray-100 dark:bg-gray-700 text-gray-400 dark:text-gray-500 text-[10px] font-medium">
                                    Not configured
                                </span>
                            @else
                                <span class="ml-auto inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-[10px] font-medium">
                                    <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                                    Configured
                                </span>
                            @endif
                        </div>
                        <div class="px-6 py-5 flex flex-col gap-4 flex-1">

                            {{-- Empty state info banner --}}
                            @if(!$firewall->ssh_username && !$firewall->ssh_password)
                                <div class="flex gap-3 rounded-lg bg-gray-50 dark:bg-gray-700/40 border border-gray-200 dark:border-gray-700 p-3.5">
                                    <svg class="w-4 h-4 text-gray-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">SSH backup is not configured. Fill in a username and password below to enable automatic config backups via SFTP.</p>
                                </div>
                            @endif

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
                                    <div class="relative" x-data="{ show: false }">
                                        <input :type="show ? 'text' : 'password'" name="ssh_password" id="ssh_password"
                                            placeholder="Leave blank to keep current"
                                            class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm pr-9">
                                        <button type="button" @click="show = !show"
                                                class="absolute inset-y-0 right-0 px-3 flex items-center text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 transition-colors"
                                                :title="show ? 'Hide' : 'Show'">
                                            <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                                            <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                                        </button>
                                    </div>
                                    @if(!empty($firewall->ssh_password))
                                        <p class="flex items-center gap-1 text-[10px] text-gray-400 dark:text-gray-500 mt-1">
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                            Configured — leave blank to keep current
                                        </p>
                                    @endif
                                    @error('ssh_password')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
                                </div>
                            </div>

                            <div>
                                <label for="ssh_host_key_fingerprint" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Verified SSH host key fingerprint</label>
                                <input type="text" name="ssh_host_key_fingerprint" id="ssh_host_key_fingerprint"
                                    value="{{ old('ssh_host_key_fingerprint', $firewall->ssh_host_key_fingerprint) }}"
                                    placeholder="SHA256:"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 font-mono text-sm">
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Obtain from the firewall console or another trusted channel. SSH backups will refuse connections if the key doesn't match. If connection details change, you'll be prompted to re-enter your password.</p>
                                @error('ssh_host_key_fingerprint')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
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
                            <div class="flex items-center gap-2.5">
                                <span class="flex items-center justify-center w-5 h-5 rounded-full bg-indigo-100 dark:bg-indigo-900/50 text-indigo-600 dark:text-indigo-400 text-[10px] font-bold shrink-0">4</span>
                                <h3 class="text-xs font-semibold uppercase tracking-widest text-gray-400 dark:text-gray-500">Location</h3>
                            </div>
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
                            <!-- Mini map preview — Leaflet, matches /firewalls map pin style -->
                            <div class="mt-3 rounded-lg overflow-hidden border border-gray-200 dark:border-gray-700 shadow-sm relative">
                                <div id="edit-fw-map" style="height:180px; width:100%;"
                                     :class="lat && lon ? ''  : 'grayscale opacity-40 pointer-events-none'"></div>
                                <!-- Placeholder overlay when no address is set -->
                                <div x-show="!lat || !lon" style="display:none;"
                                     class="absolute inset-0 flex flex-col items-center justify-center gap-1.5 bg-white/60 dark:bg-gray-900/60 pointer-events-none">
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    </svg>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 font-medium">Search for an address to pin this firewall</p>
                                </div>
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
                    <button type="submit"
                            x-bind:disabled="!dirty"
                            x-bind:class="!dirty ? 'opacity-50 cursor-not-allowed' : ''"
                            x-bind:title="!dirty ? 'No changes to save' : ''"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-900 transition ease-in-out duration-150">
                        {{ __('Save Changes') }}
                    </button>
                    <a href="{{ route('firewall.dashboard', $firewall) }}">
                        <x-secondary-button>{{ __('Cancel') }}</x-secondary-button>
                    </a>
                    <span x-show="!dirty" x-cloak
                          class="flex items-center gap-1 text-xs text-gray-400 dark:text-gray-500 ml-1">
                        <svg class="w-3.5 h-3.5 text-green-500" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                        All changes saved
                    </span>
                </div>

                {{-- ── Credential Confirmation Modal ───────────────────── --}}
                <div x-show="modal.open"
                     x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     @keydown.escape.window="cancelModal()">

                    {{-- Backdrop --}}
                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                         @click="cancelModal()"></div>

                    {{-- Panel --}}
                    <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-2xl w-full max-w-md"
                         x-show="modal.open"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 translate-y-2 scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                         x-transition:leave-end="opacity-0 translate-y-2 scale-95">

                        {{-- Header --}}
                        <div class="px-6 py-4 border-b border-gray-100 dark:border-gray-700 flex items-center gap-3">
                            <div class="w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center shrink-0">
                                <svg class="w-5 h-5 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-sm font-semibold text-gray-900 dark:text-gray-100"
                                    x-text="modal.title"></h2>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Required to authorize this change</p>
                            </div>
                        </div>

                        {{-- Body --}}
                        <div class="px-6 py-5 space-y-4">

                            {{-- Explanation of what triggered the modal --}}
                            <p x-show="modal.reasons.length > 0"
                               class="text-sm text-gray-600 dark:text-gray-300"
                               x-text="'Because ' + modal.reasons.join(' and ') + ', your credentials must be re-entered to authorize this change.'">
                            </p>

                            {{-- API Password (basic auth) --}}
                            <div x-show="modal.needsApiSecret">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    API Password
                                </label>
                                <input type="password"
                                       x-ref="modalApiSecret"
                                       x-model="modal.apiSecretInput"
                                       class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm"
                                       placeholder="Enter your API password"
                                       @keydown.enter.prevent="confirmModal()">
                                <p class="text-xs text-gray-400 mt-1">This will be used as the API password for the updated connection.</p>
                            </div>

                            {{-- Bearer Token (token auth) --}}
                            <div x-show="modal.needsToken">
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Bearer Token
                                </label>
                                <input type="password"
                                       x-ref="modalApiToken"
                                       x-model="modal.apiTokenInput"
                                       class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm font-mono"
                                       placeholder="ey…"
                                       @keydown.enter.prevent="confirmModal()">
                                <p class="text-xs text-gray-400 mt-1">The connection changed — a new token is required.</p>
                            </div>

                            {{-- SSH warning banner --}}
                            <div x-show="modal.sshWarning"
                                 class="flex gap-3 rounded-lg bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-800/60 p-3.5">
                                <svg class="w-5 h-5 text-amber-500 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                          d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                                </svg>
                                <div>
                                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">SSH backup password will be cleared</p>
                                    <p class="text-xs text-amber-700/80 dark:text-amber-400/80 mt-0.5">
                                        Because the SSH connection details changed, the stored password will not be carried over for security. You can re-enter it in the SSH card before saving, or update it after.
                                    </p>
                                </div>
                            </div>

                        </div>

                        {{-- Footer --}}
                        <div class="px-6 py-4 border-t border-gray-100 dark:border-gray-700 flex items-center gap-3">
                            <x-primary-button type="button" @click="confirmModal()">{{ __('Confirm & Save') }}</x-primary-button>
                            <x-secondary-button type="button" @click="cancelModal()">{{ __('Go Back') }}</x-secondary-button>
                        </div>

                    </div>
                </div>
                {{-- /modal --}}

            </form>
        </div>
    </div>

    <script>
        function firewallEdit() {
            // Original server-rendered values — used to detect what changed
            const orig = {
                url:            {{ Illuminate\Support\Js::from($firewall->url) }},
                tlsPin:         {{ Illuminate\Support\Js::from($firewall->tls_public_key_pin) }},
                authMethod:     {{ Illuminate\Support\Js::from($firewall->auth_method ?? 'basic') }},
                sshPort:        {{ (int)($firewall->ssh_port ?? 22) }},
                sshUsername:    {{ Illuminate\Support\Js::from($firewall->ssh_username) }},
                sshFingerprint: {{ Illuminate\Support\Js::from($firewall->ssh_host_key_fingerprint) }},
                hasSshPassword: {{ $firewall->ssh_password ? 'true' : 'false' }},
            };

            return {
                // Shared state consumed by inner x-data scopes (osType)
                osType: {{ Illuminate\Support\Js::from(old('os_type', $firewall->os_type ?? 'pfsense')) }},

                // Dirty tracking — true once any field value changes
                dirty: false,

                init() {
                    // Listen for any input or change on the form and mark dirty
                    this.$el.addEventListener('input',  () => { this.dirty = true; });
                    this.$el.addEventListener('change', () => { this.dirty = true; });
                },

                // Modal state
                modal: {
                    open:           false,
                    title:          '',
                    reasons:        [],
                    needsApiSecret: false,
                    needsToken:     false,
                    sshWarning:     false,
                    apiSecretInput: '',
                    apiTokenInput:  '',
                },

                _form: null,

                handleSubmit(form) {
                    this._form = form;

                    const url          = form.querySelector('[name=url]')?.value ?? '';
                    const authMethod   = form.querySelector('[name=auth_method]')?.value ?? 'basic';
                    const tlsPin       = form.querySelector('[name=tls_public_key_pin]')?.value ?? '';
                    const certInput    = form.querySelector('[name=tls_certificate]');
                    const certUploaded = !!(certInput?.files?.length);

                    const urlChanged    = url.replace(/\/$/, '') !== (orig.url ?? '').replace(/\/$/, '');
                    const tlsChanged    = tlsPin !== (orig.tlsPin ?? '') || certUploaded;
                    const connChanged   = urlChanged || tlsChanged;

                    // Only show modal if connection changed AND the password field is empty
                    // (if the user already typed a new password, submit normally)
                    const apiSecretVal  = form.querySelector('[name=api_secret]')?.value ?? '';
                    const apiTokenVal   = form.querySelector('[name=api_token]')?.value ?? '';
                    const needsSecret   = connChanged && authMethod === 'basic'  && !apiSecretVal;
                    const needsToken    = connChanged && authMethod === 'token'   && !apiTokenVal;

                    // SSH destination change — warn that stored password will be cleared
                    const sshPort        = parseInt(form.querySelector('[name=ssh_port]')?.value ?? 22);
                    const sshUsername    = form.querySelector('[name=ssh_username]')?.value ?? '';
                    const sshFingerprint = form.querySelector('[name=ssh_host_key_fingerprint]')?.value ?? '';
                    const sshChanged     = urlChanged
                        || sshPort        !== orig.sshPort
                        || sshUsername    !== (orig.sshUsername    ?? '')
                        || sshFingerprint !== (orig.sshFingerprint ?? '');
                    const sshWarning     = sshChanged && orig.hasSshPassword;

                    // Nothing to intercept — submit directly
                    if (!needsSecret && !needsToken && !sshWarning) {
                        form.submit();
                        return;
                    }

                    // Build reasons list for modal explanation
                    const reasons = [];
                    if (urlChanged)  reasons.push('the Firewall URL was changed');
                    if (tlsChanged)  reasons.push('the trusted TLS certificate was updated');

                    this.modal.reasons        = reasons;
                    this.modal.needsApiSecret = needsSecret;
                    this.modal.needsToken     = needsToken;
                    this.modal.sshWarning     = sshWarning;
                    this.modal.apiSecretInput = '';
                    this.modal.apiTokenInput  = '';
                    this.modal.title = (needsSecret || needsToken)
                        ? 'API Credentials Required'
                        : 'Confirm Changes';
                    this.modal.open = true;

                    // Auto-focus the relevant password field after transition
                    this.$nextTick(() => setTimeout(() => {
                        if (needsSecret) this.$refs.modalApiSecret?.focus();
                        else if (needsToken) this.$refs.modalApiToken?.focus();
                    }, 50));
                },

                confirmModal() {
                    // Copy modal values into the actual form fields before native submit
                    if (this.modal.needsApiSecret && this._form) {
                        const el = this._form.querySelector('[name=api_secret]');
                        if (el) el.value = this.modal.apiSecretInput;
                    }
                    if (this.modal.needsToken && this._form) {
                        const el = this._form.querySelector('[name=api_token]');
                        if (el) el.value = this.modal.apiTokenInput;
                    }
                    this.modal.open = false;
                    this._form?.submit(); // native submit — bypasses @submit.prevent
                },

                cancelModal() {
                    this.modal.open = false;
                },
            };
        }

        function addressAutocomplete() {
            return {
                searchQuery: {{ Illuminate\Support\Js::from(old('address', $firewall->address)) }},
                address:     {{ Illuminate\Support\Js::from(old('address', $firewall->address)) }},
                suggestions: [],
                lat:         {{ Illuminate\Support\Js::from(old('latitude',  $firewall->latitude)) }},
                lon:         {{ Illuminate\Support\Js::from(old('longitude', $firewall->longitude)) }},
                loading:     false,

                _map:        null,
                _marker:     null,

                init() {
                    // Load Leaflet once, then always render the placeholder map
                    const loadLeaflet = (cb) => {
                        if (window.L) { cb(); return; }
                        if (!document.getElementById('leaflet-css')) {
                            const link = document.createElement('link');
                            link.id = 'leaflet-css'; link.rel = 'stylesheet';
                            link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                            document.head.appendChild(link);
                        }
                        const s = document.createElement('script');
                        s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                        s.onload = cb;
                        document.head.appendChild(s);
                    };
                    // Always initialise the map — show real location or greyed-out world view
                    loadLeaflet(() => {
                        if (this.lat && this.lon) {
                            this.updateMap(this.lat, this.lon);
                        } else {
                            this.initPlaceholderMap();
                        }
                    });
                    // React to address selection / clear
                    this.$watch('lat', (v) => {
                        if (v && this.lon) loadLeaflet(() => this.updateMap(v, this.lon));
                        else loadLeaflet(() => this.resetToPlaceholder());
                    });
                },

                initPlaceholderMap() {
                    const el = document.getElementById('edit-fw-map');
                    if (!el || this._map) return;
                    // Neutral world view — greyscale applied via CSS class
                    this._map = L.map(el, { scrollWheelZoom: false, zoomControl: false, attributionControl: false });
                    L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { maxZoom: 20 }).addTo(this._map);
                    this._map.setView([20, 0], 2);
                    this.$nextTick(() => this._map && this._map.invalidateSize());
                },

                resetToPlaceholder() {
                    if (this._marker) { this._map && this._map.removeLayer(this._marker); this._marker = null; }
                    if (this._map) {
                        this._map.setView([20, 0], 2);
                    } else {
                        this.initPlaceholderMap();
                    }
                },

                updateMap(lat, lon) {
                    lat = parseFloat(lat); lon = parseFloat(lon);
                    if (isNaN(lat) || isNaN(lon)) return;
                    const el = document.getElementById('edit-fw-map');
                    if (!el) return;
                    if (!this._map) {
                        this._map = L.map(el, { scrollWheelZoom: false, zoomControl: true, attributionControl: false });
                        L.tileLayer('https://mt1.google.com/vt/lyrs=m&x={x}&y={y}&z={z}', { maxZoom: 20 }).addTo(this._map);
                    }
                    this._map.setView([lat, lon], 14);
                    if (this._marker) this._map.removeLayer(this._marker);
                    this._marker = L.circleMarker([lat, lon], {
                        radius: 8,
                        fillColor: '#6366f1',   // indigo-500 — matches /firewalls map
                        color: '#ffffff',
                        weight: 2,
                        opacity: 1,
                        fillOpacity: 0.95
                    }).addTo(this._map);
                    this.$nextTick(() => this._map && this._map.invalidateSize());
                },

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

    {{-- ── Toast notifications ─────────────────────────────────────────── --}}
    <div class="fixed bottom-6 right-6 z-50 flex flex-col gap-3 items-end pointer-events-none">

        @if(session('success'))
            <div x-data="{
                    show: true,
                    progress: 100,
                    init() {
                        const interval = setInterval(() => {
                            this.progress -= 2.5;
                            if (this.progress <= 0) { clearInterval(interval); this.show = false; }
                        }, 100);
                    }
                 }"
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-x-8 scale-95"
                 class="pointer-events-auto w-80 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="flex items-start gap-3 px-4 py-3.5">
                <div class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900/50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Saved</p>
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ session('success') }}</p>
                </div>
                <button type="button" @click="show = false"
                        class="text-gray-300 hover:text-gray-500 dark:hover:text-gray-200 transition-colors shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            {{-- Auto-dismiss progress bar --}}
            <div class="h-0.5 bg-gray-100 dark:bg-gray-700">
                <div class="h-full bg-green-500 dark:bg-green-400 transition-all duration-100 ease-linear"
                     :style="`width: ${progress}%`"></div>
            </div>
        </div>
        @endif

        @if($errors->any())
            <div x-data="{ show: true }"
                 x-show="show"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 translate-x-8 scale-95"
                 x-transition:enter-end="opacity-100 translate-x-0 scale-100"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 translate-x-0 scale-100"
                 x-transition:leave-end="opacity-0 translate-x-8 scale-95"
                 class="pointer-events-auto w-80 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-100 dark:border-gray-700 overflow-hidden">
            <div class="flex items-start gap-3 px-4 py-3.5">
                <div class="w-8 h-8 rounded-full bg-red-100 dark:bg-red-900/50 flex items-center justify-center shrink-0">
                    <svg class="w-4 h-4 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </div>
                <div class="flex-1 min-w-0 pt-0.5">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">Couldn't save</p>
                    <ul class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 space-y-0.5">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" @click="show = false"
                        class="text-gray-300 hover:text-gray-500 dark:hover:text-gray-200 transition-colors shrink-0 mt-0.5">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
            <div class="h-0.5 bg-red-200 dark:bg-red-900/60"></div>
        </div>
        @endif

    </div>
    {{-- /toasts --}}

</x-app-layout>
