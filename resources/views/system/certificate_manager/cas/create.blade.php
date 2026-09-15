<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Create Certificate Authority') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    <form method="POST" action="{{ route('system.certificate_manager.cas.store', $firewall) }}"
                        x-data="{ method: 'internal' }">
                        @csrf

                        <!-- Method Selection -->
                        <div class="mb-4">
                            <x-input-label for="method_select" :value="__('Method')" />
                            <select id="method_select" x-model="method"
                                class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="internal">Create an internal Certificate Authority</option>
                                <option value="import">Import an existing Certificate Authority</option>
                            </select>
                            <input type="hidden" name="method" x-bind:value="method">
                        </div>

                        <!-- Descriptive Name -->
                        <div class="mb-4">
                            <x-input-label for="descr" :value="__('Descriptive Name')" />
                            <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr"
                                :value="old('descr')" required />
                            <x-input-error :messages="$errors->get('descr')" class="mt-2" />
                        </div>

                        <!-- Trust Store -->
                        <div class="mb-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="trust" value="0">
                                <input type="checkbox" name="trust" value="1" id="trust"
                                    class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    {{ old('trust') ? 'checked' : '' }}>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Add to Operating System Trust Store</span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">When enabled, this CA's contents will be added to the OS trust store.</p>
                        </div>

                        <!-- Randomize Serial -->
                        <div class="mb-4">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="hidden" name="randomserial" value="0">
                                <input type="checkbox" name="randomserial" value="1" id="randomserial"
                                    class="rounded border-gray-300 dark:border-gray-700 dark:bg-gray-900 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                    {{ old('randomserial') ? 'checked' : '' }}>
                                <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Use random serial numbers when signing certificates</span>
                            </label>
                        </div>

                        <!-- Internal CA Fields -->
                        <div x-show="method === 'internal'">
                            <div class="mb-4">
                                <x-input-label for="keytype" :value="__('Key Type')" />
                                <select id="keytype" name="keytype"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="RSA">RSA</option>
                                    <option value="ECDSA">ECDSA</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <x-input-label for="keylen" :value="__('Key Length (RSA)')" />
                                <select id="keylen" name="keylen"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="2048">2048</option>
                                    <option value="4096">4096</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <x-input-label for="digest_alg" :value="__('Digest Algorithm')" />
                                <select id="digest_alg" name="digest_alg"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="sha256">SHA256</option>
                                    <option value="sha1">SHA1</option>
                                </select>
                            </div>
                            <div class="mb-4">
                                <x-input-label for="lifetime" :value="__('Lifetime (days)')" />
                                <x-text-input id="lifetime" class="block mt-1 w-full" type="number" name="lifetime"
                                    :value="old('lifetime', 3650)" />
                            </div>

                            <h3 class="text-lg font-medium mt-6 mb-2">Subject Information</h3>
                            <p class="text-sm text-amber-700 dark:text-amber-400 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-700 rounded-md px-3 py-2 mb-4">
                                <strong>pfSense:</strong> Country, State, City, Organization, Organizational Unit, and Common Name are
                                <strong>required</strong> — pfSense passes all values directly to OpenSSL, which rejects empty fields and returns an unknown error.
                            </p>

                            {{-- Common Name: full-width, required, above the optional grid --}}
                            <div class="mb-4">
                                <label for="dn_commonname" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Common Name <span class="text-red-500">*</span>
                                </label>
                                <x-text-input id="dn_commonname" class="block mt-1 w-full" type="text"
                                    name="dn_commonname" :value="old('dn_commonname')"
                                    placeholder="e.g. My Internal CA or myca.example.com"
                                    required />
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    The CN embedded in the certificate — identifies this CA to browsers and systems.
                                </p>
                            </div>

                            {{-- Required + Optional DN fields in 2-column grid --}}
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="dn_country" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Country Code <span class="text-red-500">*</span>
                                    </label>
                                    <x-text-input id="dn_country" class="block mt-1 w-full" type="text"
                                        name="dn_country" :value="old('dn_country', 'US')" maxlength="2" required />
                                </div>
                                <div>
                                    <label for="dn_state" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        State or Province <span class="text-red-500">*</span>
                                    </label>
                                    <x-text-input id="dn_state" class="block mt-1 w-full" type="text" name="dn_state"
                                        :value="old('dn_state')" required />
                                </div>
                                <div>
                                    <label for="dn_city" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        City <span class="text-red-500">*</span>
                                    </label>
                                    <x-text-input id="dn_city" class="block mt-1 w-full" type="text" name="dn_city"
                                        :value="old('dn_city')" required />
                                </div>
                                <div>
                                    <label for="dn_organization" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Organization <span class="text-red-500">*</span>
                                    </label>
                                    <x-text-input id="dn_organization" class="block mt-1 w-full" type="text"
                                        name="dn_organization" :value="old('dn_organization')" required />
                                </div>
                                <div>
                                    <label for="dn_ou" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Organizational Unit <span class="text-red-500">*</span>
                                    </label>
                                    <x-text-input id="dn_ou" class="block mt-1 w-full" type="text"
                                        name="dn_ou" :value="old('dn_ou')"
                                        placeholder="e.g. IT Department" required />
                                </div>
                                <div>
                                    <x-input-label for="dn_email" :value="__('Email Address')" />
                                    <x-text-input id="dn_email" class="block mt-1 w-full" type="email" name="dn_email"
                                        :value="old('dn_email')" placeholder="optional" />
                                </div>
                            </div>
                        </div>

                        <!-- Import CA Fields -->
                        <div x-show="method === 'import'">
                            <div class="mb-4">
                                <x-input-label for="crt" :value="__('Certificate Data')" />
                                <textarea id="crt" name="crt" rows="5"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
                                <p class="text-sm text-gray-500 mt-1">Paste the certificate data in X.509 PEM format.
                                </p>
                            </div>
                            <div class="mb-4">
                                <x-input-label for="prv" :value="__('Private Key Data')" />
                                <textarea id="prv" name="prv" rows="5"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"></textarea>
                                <p class="text-sm text-gray-500 mt-1">Paste the private key data in X.509 PEM format.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button class="ml-4">
                                {{ __('Save') }}
                            </x-primary-button>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
