<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('IPsec Phase 2') }} (Phase 1 ID: {{ $phase1Id }})" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{
        showModal: false,
        form: {
            mode: 'tunnel',
            localid_type: 'network',
            localid_address: '',
            localid_netbits: '',
            remoteid_type: 'network',
            remoteid_address: '',
            remoteid_netbits: '',
            protocol: 'esp',
            encryption_algorithm_name: 'aes',
            encryption_algorithm_keylen: 'auto',
            hash_algorithm: ['hmac_sha256'],
            pfsgroup: '14',
            lifetime: 3600,
            descr: ''
        },
        hasKeylen() {
            return ['aes', 'aes128gcm', 'aes192gcm', 'aes256gcm'].includes(this.form.encryption_algorithm_name);
        },
        getKeylenOptions() {
            if (this.form.encryption_algorithm_name === 'aes') {
                return [
                    { value: 'auto', label: 'Auto' },
                    { value: '128', label: '128 bits' },
                    { value: '192', label: '192 bits' },
                    { value: '256', label: '256 bits' }
                ];
            }
            if (['aes128gcm', 'aes192gcm', 'aes256gcm'].includes(this.form.encryption_algorithm_name)) {
                return [
                    { value: 'auto', label: 'Auto' },
                    { value: '64', label: '64 bits' },
                    { value: '96', label: '96 bits' },
                    { value: '128', label: '128 bits' }
                ];
            }
            return [];
        }
    }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium">Phase 2 Entries</h3>
                        <div class="flex space-x-2">
                            <a href="{{ route('vpn.ipsec', $firewall) }}"
                                class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                                Back to Phase 1
                            </a>
                            <button @click="showModal = true"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                Add Phase 2
                            </button>
                        </div>
                    </div>

                    @if($phase2List->isEmpty())
                        <p class="text-gray-500">No Phase 2 tunnels found for this Phase 1.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Unique ID</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Mode</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Local Subnet</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Remote Subnet</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Description</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($phase2List as $p2)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $p2['uniqid'] ?? ($p2['id'] ?? 'N/A') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $p2['mode'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $p2['localid_type'] ?? '' }}
                                                @if(isset($p2['localid_address'])) : {{ $p2['localid_address'] }} @endif
                                                @if(isset($p2['localid_netbits'])) / {{ $p2['localid_netbits'] }} @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $p2['remoteid_type'] ?? '' }}
                                                @if(isset($p2['remoteid_address'])) : {{ $p2['remoteid_address'] }} @endif
                                                @if(isset($p2['remoteid_netbits'])) / {{ $p2['remoteid_netbits'] }} @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                {{ $p2['descr'] ?? '' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <form
                                                    action="{{ route('vpn.ipsec.phase2.destroy', [$firewall, $phase1Id, $p2['uniqid'] ?? $p2['id']]) }}"
                                                    method="POST" class="inline-block"
                                                    onsubmit="return confirm('Are you sure you want to delete this Phase 2 entry?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Modal -->
        <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showModal = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-xl sm:w-full">
                    <form action="{{ route('vpn.ipsec.phase2.store', [$firewall, $phase1Id]) }}" method="POST">
                        @csrf
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4 max-h-[80vh] overflow-y-auto">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Add IPsec Phase 2
                            </h3>

                            <!-- Mode -->
                            <div class="mb-4">
                                <x-input-label for="mode" :value="__('Mode')" />
                                <select id="mode" name="mode" x-model="form.mode"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="tunnel">Tunnel IPv4</option>
                                    <option value="tunnel6">Tunnel IPv6</option>
                                    <option value="transport">Transport</option>
                                    <option value="vti">Routed (VTI)</option>
                                </select>
                            </div>

                            <!-- Local Network -->
                            <div class="mb-4">
                                <x-input-label for="localid_type" :value="__('Local Network Type')" />
                                <select id="localid_type" name="localid_type" x-model="form.localid_type"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="lan">LAN Subnet</option>
                                    <option value="wan">WAN Address</option>
                                    <option value="address">Address</option>
                                    <option value="network">Network</option>
                                </select>
                            </div>
                            <div class="mb-4"
                                x-show="form.localid_type === 'address' || form.localid_type === 'network'">
                                <x-input-label for="localid_address" :value="__('Local Address')" />
                                <x-text-input id="localid_address" class="block mt-1 w-full" type="text"
                                    name="localid_address" x-model="form.localid_address" placeholder="e.g. 192.168.1.0" />
                            </div>
                            <div class="mb-4" x-show="form.localid_type === 'network'">
                                <x-input-label for="localid_netbits" :value="__('Local Netbits')" />
                                <x-text-input id="localid_netbits" class="block mt-1 w-full" type="number" min="0" max="128"
                                    name="localid_netbits" x-model="form.localid_netbits" placeholder="24" />
                            </div>

                            <!-- Remote Network -->
                            <div class="mb-4">
                                <x-input-label for="remoteid_type" :value="__('Remote Network Type')" />
                                <select id="remoteid_type" name="remoteid_type" x-model="form.remoteid_type"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="network">Network</option>
                                    <option value="address">Address</option>
                                </select>
                            </div>
                            <div class="mb-4"
                                x-show="form.remoteid_type === 'address' || form.remoteid_type === 'network'">
                                <x-input-label for="remoteid_address" :value="__('Remote Address')" />
                                <x-text-input id="remoteid_address" class="block mt-1 w-full" type="text"
                                    name="remoteid_address" x-model="form.remoteid_address" placeholder="e.g. 10.0.0.0" />
                            </div>
                            <div class="mb-4" x-show="form.remoteid_type === 'network'">
                                <x-input-label for="remoteid_netbits" :value="__('Remote Netbits')" />
                                <x-text-input id="remoteid_netbits" class="block mt-1 w-full" type="number" min="0" max="128"
                                    name="remoteid_netbits" x-model="form.remoteid_netbits" placeholder="24" />
                            </div>

                            <!-- Protocol -->
                            <div class="mb-4">
                                <x-input-label for="protocol" :value="__('Protocol')" />
                                <select id="protocol" name="protocol" x-model="form.protocol"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="esp">ESP</option>
                                    <option value="ah">AH</option>
                                </select>
                            </div>

                            <!-- Encryption Algorithm -->
                            <div class="mb-4">
                                <x-input-label for="encryption_algorithm_name" :value="__('Encryption Algorithm')" />
                                <select id="encryption_algorithm_name" name="encryption_algorithm_name"
                                    x-model="form.encryption_algorithm_name"
                                    @change="if (!hasKeylen()) { form.encryption_algorithm_keylen = ''; } else { form.encryption_algorithm_keylen = 'auto'; }"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="aes">AES</option>
                                    <option value="aes128gcm">AES128-GCM</option>
                                    <option value="aes192gcm">AES192-GCM</option>
                                    <option value="aes256gcm">AES256-GCM</option>
                                    <option value="chacha20poly1305">CHACHA20-POLY1305</option>
                                </select>
                            </div>

                            <!-- Key Length -->
                            <div class="mb-4" x-show="hasKeylen()">
                                <x-input-label for="encryption_algorithm_keylen" :value="__('Key Length')" />
                                <select id="encryption_algorithm_keylen" name="encryption_algorithm_keylen"
                                    x-model="form.encryption_algorithm_keylen"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <template x-for="opt in getKeylenOptions()" :key="opt.value">
                                        <option :value="opt.value" x-text="opt.label"></option>
                                    </template>
                                </select>
                            </div>

                            <!-- Hash / Integrity Algorithms -->
                            <div class="mb-4">
                                <x-input-label :value="__('Hash / Integrity Algorithms')" />
                                <div class="mt-2 grid grid-cols-2 gap-2 text-sm text-gray-700 dark:text-gray-300">
                                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="hash_algorithm[]" value="hmac_sha256" x-model="form.hash_algorithm" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span>SHA256</span>
                                    </label>
                                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="hash_algorithm[]" value="hmac_sha384" x-model="form.hash_algorithm" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span>SHA384</span>
                                    </label>
                                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="hash_algorithm[]" value="hmac_sha512" x-model="form.hash_algorithm" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span>SHA512</span>
                                    </label>
                                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="hash_algorithm[]" value="hmac_sha1" x-model="form.hash_algorithm" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span>SHA1</span>
                                    </label>
                                    <label class="inline-flex items-center space-x-2 cursor-pointer">
                                        <input type="checkbox" name="hash_algorithm[]" value="aesxcbc" x-model="form.hash_algorithm" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span>AES-XCBC</span>
                                    </label>
                                </div>
                            </div>

                            <!-- PFS Key Group -->
                            <div class="mb-4">
                                <x-input-label for="pfsgroup" :value="__('PFS Key Group')" />
                                <select id="pfsgroup" name="pfsgroup" x-model="form.pfsgroup"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="0">Off</option>
                                    <option value="1">1 (768 bit)</option>
                                    <option value="2">2 (1024 bit)</option>
                                    <option value="5">5 (1536 bit)</option>
                                    <option value="14">14 (2048 bit)</option>
                                    <option value="15">15 (3072 bit)</option>
                                    <option value="16">16 (4096 bit)</option>
                                    <option value="17">17 (6144 bit)</option>
                                    <option value="18">18 (8192 bit)</option>
                                    <option value="19">19 (nist ecp256)</option>
                                    <option value="20">20 (nist ecp384)</option>
                                    <option value="21">21 (nist ecp521)</option>
                                    <option value="22">22 (1024 sub 160 bit)</option>
                                    <option value="23">23 (2048 sub 224 bit)</option>
                                    <option value="24">24 (2048 sub 256 bit)</option>
                                    <option value="25">25 (nist ecp192)</option>
                                    <option value="26">26 (nist ecp224)</option>
                                    <option value="27">27 (brainpool ecp224)</option>
                                    <option value="28">28 (brainpool ecp256)</option>
                                    <option value="29">29 (brainpool ecp384)</option>
                                    <option value="30">30 (brainpool ecp512)</option>
                                    <option value="31">31 (Curve25519)</option>
                                    <option value="32">32 (Curve448)</option>
                                </select>
                            </div>

                            <!-- Lifetime -->
                            <div class="mb-4">
                                <x-input-label for="lifetime" :value="__('Lifetime (seconds)')" />
                                <x-text-input id="lifetime" class="block mt-1 w-full" type="number" name="lifetime"
                                    x-model="form.lifetime" />
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <x-input-label for="descr" :value="__('Description')" />
                                <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr"
                                    x-model="form.descr" />
                            </div>

                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Save
                            </button>
                            <button type="button" @click="showModal = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
