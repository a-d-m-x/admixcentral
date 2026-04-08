<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('IPsec') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{
        showModal: false,
        form: {
            iketype: 'ikev2',
            protocol: 'inet',
            interface: 'wan',
            remote_gateway: '',
            descr: '',
            encryption_algorithm_name: 'aes',
            encryption_algorithm_keylen: '128',
            hash_algorithm: 'sha256',
            dhgroup: '14',
            authentication_method: 'pre_shared_key',
            pre_shared_key: '',
            myid_type: 'myaddress',
            peerid_type: 'peeraddress',
            lifetime: 28800
        }
    }" @open-create-phase1.window="showModal = true">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Phase 1 -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium">Phase 1</h3>
                        @if(!auth()->user()->isReadOnly())
                        <x-button-add @click="showModal = true">
                            Add Phase 1
                        </x-button-add>
                        @endif
                    </div>
                    
                    @if(empty($phase1s))
                        <p class="text-gray-500">No Phase 1 tunnels found.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IKE ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Remote Gateway</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mode</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($phase1s as $p1)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p1['ikeid'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p1['remote-gateway'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p1['mode'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p1['descr'] ?? '' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                @if(isset($p1['disabled']))
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Disabled</span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Enabled</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                                <a href="{{ route('vpn.ipsec.phase2', [$firewall, $p1['ikeid']]) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Phase 2</a>
                                                @if(!auth()->user()->isReadOnly())
                                                <form action="{{ route('vpn.ipsec.phase1.destroy', [$firewall, $p1['ikeid']]) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete this tunnel?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Phase 2 -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium">Phase 2</h3>
                        <!-- Add button placeholder -->
                    </div>

                    @if(empty($phase2s))
                        <p class="text-gray-500">No Phase 2 tunnels found.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IKE ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Mode</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Local Subnet</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Remote Subnet</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($phase2s as $p2)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p2['ikeid'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p2['mode'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p2['localid']['type'] ?? '' }} {{ $p2['localid']['address'] ?? '' }} {{ $p2['localid']['netbits'] ?? '' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p2['remoteid']['type'] ?? '' }} {{ $p2['remoteid']['address'] ?? '' }} {{ $p2['remoteid']['netbits'] ?? '' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $p2['descr'] ?? '' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <!-- Modal — hidden for readonly -->
        @if(!auth()->user()->isReadOnly())
        <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity" aria-hidden="true" @click="showModal = false">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form action="{{ route('vpn.ipsec.phase1.store', $firewall) }}" method="POST">
                        @csrf
                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100 mb-4">Add Phase 1</h3>
                            
                            <!-- IKE Type -->
                            <div class="mb-4">
                                <x-input-label for="iketype" :value="__('IKE Version')" />
                                <select id="iketype" name="iketype" x-model="form.iketype" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="ikev2">IKEv2</option>
                                    <option value="ikev1">IKEv1</option>
                                    <option value="auto">Auto</option>
                                </select>
                            </div>

                            <!-- Protocol -->
                            <div class="mb-4">
                                <x-input-label for="protocol" :value="__('Protocol')" />
                                <select id="protocol" name="protocol" x-model="form.protocol" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="inet">IPv4</option>
                                    <option value="inet6">IPv6</option>
                                </select>
                            </div>

                            <!-- Interface -->
                            <div class="mb-4">
                                <x-input-label for="interface" :value="__('Interface')" />
                                <select id="interface" name="interface" x-model="form.interface" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    @foreach($interfaces as $iface)
                                        <option value="{{ $iface['id'] }}">{{ $iface['descr'] ?? $iface['id'] }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <!-- Remote Gateway -->
                            <div class="mb-4">
                                <x-input-label for="remote_gateway" :value="__('Remote Gateway')" />
                                <x-text-input id="remote_gateway" class="block mt-1 w-full" type="text" name="remote_gateway" x-model="form.remote_gateway" required />
                            </div>

                            <!-- Description -->
                            <div class="mb-4">
                                <x-input-label for="descr" :value="__('Description')" />
                                <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr" x-model="form.descr" />
                            </div>

                            <!-- Encryption Algorithm -->
                            <div class="mb-4">
                                <x-input-label for="encryption_algorithm_name" :value="__('Encryption Algorithm')" />
                                <select id="encryption_algorithm_name" name="encryption_algorithm_name" x-model="form.encryption_algorithm_name" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="aes">AES</option>
                                    <option value="3des">3DES</option>
                                </select>
                            </div>

                            <!-- Key Length -->
                            <div class="mb-4" x-show="form.encryption_algorithm_name === 'aes'">
                                <x-input-label for="encryption_algorithm_keylen" :value="__('Key Length')" />
                                <select id="encryption_algorithm_keylen" name="encryption_algorithm_keylen" x-model="form.encryption_algorithm_keylen" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="128">128 bits</option>
                                    <option value="256">256 bits</option>
                                </select>
                            </div>

                            <!-- Hash Algorithm -->
                            <div class="mb-4">
                                <x-input-label for="hash_algorithm" :value="__('Hash Algorithm')" />
                                <select id="hash_algorithm" name="hash_algorithm" x-model="form.hash_algorithm" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="sha256">SHA256</option>
                                    <option value="sha1">SHA1</option>
                                </select>
                            </div>

                            <!-- DH Group -->
                            <div class="mb-4">
                                <x-input-label for="dhgroup" :value="__('DH Group')" />
                                <select id="dhgroup" name="dhgroup" x-model="form.dhgroup" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="14">14 (2048 bit)</option>
                                    <option value="2">2 (1024 bit)</option>
                                </select>
                            </div>

                            <!-- Auth Method -->
                            <div class="mb-4">
                                <x-input-label for="authentication_method" :value="__('Authentication Method')" />
                                <select id="authentication_method" name="authentication_method" x-model="form.authentication_method" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="pre_shared_key">Pre-Shared Key</option>
                                    <option value="rsasig">RSA Signature</option>
                                </select>
                            </div>

                            <!-- Pre-Shared Key -->
                            <div class="mb-4" x-show="form.authentication_method === 'pre_shared_key'">
                                <x-input-label for="pre_shared_key" :value="__('Pre-Shared Key')" />
                                <x-text-input id="pre_shared_key" class="block mt-1 w-full" type="text" name="pre_shared_key" x-model="form.pre_shared_key" />
                            </div>

                            <!-- My ID Type -->
                            <div class="mb-4">
                                <x-input-label for="myid_type" :value="__('My Identifier')" />
                                <select id="myid_type" name="myid_type" x-model="form.myid_type" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="myaddress">My IP address</option>
                                    <option value="address">IP address</option>
                                    <option value="fqdn">Distinguished name</option>
                                    <option value="user_fqdn">User distinguished name</option>
                                    <option value="asn1dn">ASN.1 distinguished name</option>
                                </select>
                            </div>

                            <!-- Peer ID Type -->
                            <div class="mb-4">
                                <x-input-label for="peerid_type" :value="__('Peer Identifier')" />
                                <select id="peerid_type" name="peerid_type" x-model="form.peerid_type" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="peeraddress">Peer IP address</option>
                                    <option value="address">IP address</option>
                                    <option value="fqdn">Distinguished name</option>
                                    <option value="user_fqdn">User distinguished name</option>
                                    <option value="asn1dn">ASN.1 distinguished name</option>
                                </select>
                            </div>

                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Save
                            </button>
                            <button type="button" @click="showModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        @endif
    </div>
</x-app-layout>
