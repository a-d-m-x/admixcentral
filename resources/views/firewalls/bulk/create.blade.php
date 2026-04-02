<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bulk Add: ') . ucfirst($type) }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                        Adding {{ $type }} to {{ count(explode(',', $firewall_ids)) }} firewalls.
                    </p>

                    <form action="{{ route('firewalls.bulk.store', $type) }}" method="POST">
                        @csrf
                        <input type="hidden" name="firewall_ids" value="{{ $firewall_ids }}">

                        @if($type === 'alias')
                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <label class="pf-label" for="name">Name</label>
                                    <input class="pf-input shadow-sm" id="name" type="text" name="name" required>
                                </div>
                                <div>
                                    <label class="pf-label" for="type">Type</label>
                                    <select class="pf-select shadow-sm" id="type" name="type">
                                        <option value="host">Host(s)</option>
                                        <option value="network">Network(s)</option>
                                        <option value="port">Port(s)</option>
                                        <option value="url">URL (IPs)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="pf-label" for="address">Content (IPs/Networks/Ports)</label>
                                    <textarea class="pf-input shadow-sm" id="address" name="address" rows="3"></textarea>
                                    <p class="text-xs text-gray-500 mt-1">Space or comma separated.</p>
                                </div>
                                <div>
                                    <label class="pf-label" for="descr">Description</label>
                                    <input class="pf-input shadow-sm" id="descr" type="text" name="descr">
                                </div>
                            </div>

                        @elseif($type === 'nat')
                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <label class="pf-label" for="interface">Interface</label>
                                    <select class="pf-select shadow-sm" id="interface" name="interface">
                                        <option value="wan">WAN</option>
                                        <option value="lan">LAN</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="pf-label" for="protocol">Protocol</label>
                                    <select class="pf-select shadow-sm" id="protocol" name="protocol">
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                        <option value="tcp/udp">TCP/UDP</option>
                                        <option value="icmp">ICMP</option>
                                        <option value="esp">ESP</option>
                                        <option value="ah">AH</option>
                                        <option value="gre">GRE</option>
                                        <option value="ipv6">IPv6</option>
                                        <option value="igmp">IGMP</option>
                                        <option value="pim">PIM</option>
                                        <option value="ospf">OSPF</option>
                                        <option value="sctp">SCTP</option>
                                        <option value="any">Any</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="src">Source</label>
                                        <input class="pf-input shadow-sm" id="src" type="text" name="src" value="any"
                                            placeholder="IP/Alias or 'any'">
                                    </div>
                                    <div>
                                        <label class="pf-label" for="srcport">Source Port</label>
                                        <input class="pf-input shadow-sm" id="srcport" type="text" name="srcport"
                                            placeholder="Port, Range or 'any'">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="dst">Destination Address</label>
                                        <input class="pf-input shadow-sm" id="dst" type="text" name="dst" value="wan"
                                            placeholder="IP/Alias, 'any' or 'interface name (e.g. wan)'">
                                    </div>
                                    <div>
                                        <label class="pf-label" for="dstport">Destination Port (External)</label>
                                        <input class="pf-input shadow-sm" id="dstport" type="text" name="dstport" required>
                                    </div>
                                </div>
                                <div>
                                    <label class="pf-label" for="target">Target IP (Internal)</label>
                                    <input class="pf-input shadow-sm" id="target" type="text" name="target" required>
                                </div>
                                <div>
                                    <label class="pf-label" for="local_port">Target Port (Internal)</label>
                                    <input class="pf-input shadow-sm" id="local_port" type="text" name="local_port"
                                        required>
                                </div>
                                <div>
                                    <label class="pf-label" for="natreflection">NAT Reflection</label>
                                    <select class="pf-select shadow-sm" id="natreflection" name="natreflection">
                                        <option value="system-default">Use System Default</option>
                                        <option value="enable">Enable (NAT + Proxy)</option>
                                        <option value="purenat">Enable (Pure NAT)</option>
                                        <option value="disable">Disable</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="pf-label" for="associated_rule">Filter Rule Association</label>
                                    <select class="pf-select shadow-sm" id="associated_rule" name="associated_rule">
                                        <option value="new">Add associated filter rule</option>
                                        <option value="pass">Add unassociated Pass rule</option>
                                        <option value="">None (NAT Only)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="pf-label" for="descr">Description</label>
                                    <input class="pf-input shadow-sm" id="descr" type="text" name="descr">
                                </div>
                            </div>

                        @elseif($type === 'rule')
                            <div class="grid grid-cols-1 gap-6">
                                <div>
                                    <label class="pf-label" for="type">Action</label>
                                    <select class="pf-select shadow-sm" id="type" name="type">
                                        <option value="pass">Pass</option>
                                        <option value="block">Block</option>
                                        <option value="reject">Reject</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="pf-label" for="interface">Interface</label>
                                    <select class="pf-select shadow-sm" id="interface" name="interface">
                                        <option value="wan">WAN</option>
                                        <option value="lan">LAN</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="pf-label" for="protocol">Protocol</label>
                                    <select class="pf-select shadow-sm" id="protocol" name="protocol">
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                        <option value="tcp/udp">TCP/UDP</option>
                                        <option value="icmp">ICMP</option>
                                        <option value="esp">ESP</option>
                                        <option value="ah">AH</option>
                                        <option value="gre">GRE</option>
                                        <option value="ipv6">IPv6</option>
                                        <option value="igmp">IGMP</option>
                                        <option value="pim">PIM</option>
                                        <option value="ospf">OSPF</option>
                                        <option value="sctp">SCTP</option>
                                        <option value="any">Any</option>
                                    </select>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="src">Source</label>
                                        <input class="pf-input shadow-sm" id="src" type="text" name="src" value="any"
                                            placeholder="IP/Alias or 'any'">
                                    </div>
                                    <div>
                                        <label class="pf-label" for="srcport">Source Port</label>
                                        <input class="pf-input shadow-sm" id="srcport" type="text" name="srcport"
                                            placeholder="Port or 'any'">
                                    </div>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="dst">Destination</label>
                                        <input class="pf-input shadow-sm" id="dst" type="text" name="dst" value="any"
                                            placeholder="IP/Alias or 'any'">
                                    </div>
                                    <div>
                                        <label class="pf-label" for="dstport">Destination Port</label>
                                        <input class="pf-input shadow-sm" id="dstport" type="text" name="dstport"
                                            placeholder="Port or 'any'">
                                    </div>
                                </div>
                                <div>
                                    <label class="pf-label" for="descr">Description</label>
                                    <input class="pf-input shadow-sm" id="descr" type="text" name="descr">
                                </div>
                            </div>

                        @elseif($type === 'ipsec')
                            <div class="grid grid-cols-1 gap-6" x-data="{ 
                                        auth_method: 'pre_shared_key', 
                                        encryption: 'aes',
                                        myid_type: 'myaddress',
                                        peerid_type: 'peeraddress'
                                    }">
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="iketype">IKE Version</label>
                                        <select class="pf-select shadow-sm" id="iketype" name="iketype">
                                            <option value="ikev2">IKEv2</option>
                                            <option value="ikev1">IKEv1</option>
                                            <option value="auto">Auto</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="pf-label" for="protocol">Internet Protocol</label>
                                        <select class="pf-select shadow-sm" id="protocol" name="protocol">
                                            <option value="inet">IPv4</option>
                                            <option value="inet6">IPv6</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="interface">Interface</label>
                                        <select class="pf-select shadow-sm" id="interface" name="interface">
                                            <option value="wan">WAN</option>
                                            <option value="lan">LAN</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="pf-label" for="remote_gateway">Remote Gateway</label>
                                        <input class="pf-input shadow-sm" id="remote_gateway" type="text"
                                            name="remote_gateway" required>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="authentication_method">Authentication Method</label>
                                        <select class="pf-select shadow-sm" id="authentication_method"
                                            name="authentication_method" x-model="auth_method" readonly>
                                            <option value="pre_shared_key">Mutual PSK</option>
                                        </select>
                                    </div>
                                    <div x-show="auth_method === 'pre_shared_key'">
                                        <label class="pf-label" for="pre_shared_key">Pre-Shared Key</label>
                                        <input class="pf-input shadow-sm" id="pre_shared_key" type="text"
                                            name="pre_shared_key">
                                    </div>
                                </div>

                                <div>
                                    <label class="pf-label" for="descr">Description</label>
                                    <input class="pf-input shadow-sm" id="descr" type="text" name="descr">
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="myid_type">My Identifier</label>
                                        <select class="pf-select shadow-sm" id="myid_type" name="myid_type"
                                            x-model="myid_type">
                                            <option value="myaddress">My IP Address</option>
                                            <option value="address">IP Address</option>
                                            <option value="fqdn">FQDN</option>
                                            <option value="user_fqdn">User FQDN (Email)</option>
                                            <option value="keyid">Key ID</option>
                                        </select>
                                    </div>
                                    <div x-show="myid_type !== 'myaddress'">
                                        <label class="pf-label" for="myid_data">My Identifier Value</label>
                                        <input class="pf-input shadow-sm" id="myid_data" type="text" name="myid_data">
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="pf-label" for="peerid_type">Peer Identifier</label>
                                        <select class="pf-select shadow-sm" id="peerid_type" name="peerid_type"
                                            x-model="peerid_type">
                                            <option value="peeraddress">Peer IP Address</option>
                                            <option value="address">IP Address</option>
                                            <option value="fqdn">FQDN</option>
                                            <option value="user_fqdn">User FQDN (Email)</option>
                                            <option value="keyid">Key ID</option>
                                        </select>
                                    </div>
                                    <div x-show="peerid_type !== 'peeraddress'">
                                        <label class="pf-label" for="peerid_data">Peer Identifier Value</label>
                                        <input class="pf-input shadow-sm" id="peerid_data" type="text" name="peerid_data">
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Phase 1 Proposal
                                        (Encryption)</h3>

                                    <div class="grid grid-cols-2 gap-4 mb-4">
                                        <div>
                                            <label class="pf-label" for="encryption_algorithm_name">Algorithm</label>
                                            <select class="pf-select shadow-sm" id="encryption_algorithm_name"
                                                name="encryption_algorithm_name" x-model="encryption">
                                                <option value="aes">AES</option>
                                                <option value="3des">3DES</option>
                                                <option value="blowfish">Blowfish</option>
                                                <option value="cast128">CAST128</option>
                                            </select>
                                        </div>
                                        <div x-show="encryption === 'aes'">
                                            <label class="pf-label" for="encryption_algorithm_keylen">Key Length</label>
                                            <select class="pf-select shadow-sm" id="encryption_algorithm_keylen"
                                                name="encryption_algorithm_keylen">
                                                <option value="128">128 bits</option>
                                                <option value="192">192 bits</option>
                                                <option value="256">256 bits</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="grid grid-cols-3 gap-4">
                                        <div>
                                            <label class="pf-label" for="hash_algorithm">Hash</label>
                                            <select class="pf-select shadow-sm" id="hash_algorithm" name="hash_algorithm">
                                                <option value="sha256">SHA256</option>
                                                <option value="sha1">SHA1</option>
                                                <option value="sha384">SHA384</option>
                                                <option value="sha512">SHA512</option>
                                                <option value="aes-xcbc">AES-XCBC</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="pf-label" for="dhgroup">DH Group</label>
                                            <select class="pf-select shadow-sm" id="dhgroup" name="dhgroup">
                                                <option value="14">14 (2048 bit)</option>
                                                <option value="2">2 (1024 bit)</option>
                                                <option value="1">1 (768 bit)</option>
                                                <option value="5">5 (1536 bit)</option>
                                                <option value="19">19 (NIST EC 256)</option>
                                                <option value="20">20 (NIST EC 384)</option>
                                                <option value="21">21 (NIST EC 521)</option>
                                                <option value="24">24 (Brainpool 256)</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="pf-label" for="lifetime">Lifetime (Seconds)</label>
                                            <input class="pf-input shadow-sm" id="lifetime" type="number" name="lifetime"
                                                value="28800">
                                        </div>
                                    </div>
                                </div>

                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <div class="flex items-center mb-4">
                                        <input type="checkbox" id="enable_phase2" name="enable_phase2"
                                            x-model="enable_phase2"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                        <label for="enable_phase2"
                                            class="ml-2 block text-sm text-gray-900 dark:text-gray-100 font-medium">
                                            Configure Phase 2 Entry
                                        </label>
                                    </div>

                                    <div x-show="enable_phase2" class="space-y-4">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Phase 2
                                            Configuration</h3>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="pf-label" for="p2_mode">Mode</label>
                                                <select class="pf-select shadow-sm" id="p2_mode" name="p2_mode">
                                                    <option value="tunnel">Tunnel IPv4</option>
                                                    <option value="tunnel6">Tunnel IPv6</option>
                                                    <option value="transport">Transport</option>
                                                    <option value="vti">VTI</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="pf-label" for="p2_protocol">Protocol</label>
                                                <select class="pf-select shadow-sm" id="p2_protocol" name="p2_protocol">
                                                    <option value="esp">ESP</option>
                                                    <option value="ah">AH</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <!-- Simple Network Selections for Bulk -->
                                            <div>
                                                <label class="pf-label" for="p2_local_network">Local Network</label>
                                                <select class="pf-select shadow-sm" id="p2_local_network"
                                                    name="p2_local_network">
                                                    <option value="lan">LAN Subnet</option>
                                                    <option value="wan">WAN Address</option>
                                                    <option value="network">Custom Network</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="pf-label" for="p2_remote_network">Remote Network</label>
                                                <input class="pf-input shadow-sm" id="p2_remote_network" type="text"
                                                    name="p2_remote_network" placeholder="CIDR (e.g. 10.0.2.0/24)">
                                            </div>
                                        </div>

                                        <div x-show="document.getElementById('p2_local_network')?.value === 'network'"
                                            class="bg-gray-50 p-2 rounded">
                                            <label class="pf-label" for="p2_local_network_custom">Custom Local
                                                Network</label>
                                            <input class="pf-input shadow-sm" id="p2_local_network_custom" type="text"
                                                name="p2_local_network_custom" placeholder="CIDR (e.g. 192.168.50.0/24)">
                                        </div>

                                        <div class="grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="pf-label" for="p2_encryption">Encryption</label>
                                                <select class="pf-select shadow-sm" id="p2_encryption" name="p2_encryption"
                                                    x-model="p2_encryption">
                                                    <option value="aes">AES</option>
                                                    <option value="3des">3DES</option>
                                                    <option value="blowfish">Blowfish</option>
                                                </select>
                                            </div>
                                            <div x-show="p2_encryption === 'aes'">
                                                <label class="pf-label" for="p2_keylen">Key Length</label>
                                                <select class="pf-select shadow-sm" id="p2_keylen" name="p2_keylen">
                                                    <option value="128">128 bits</option>
                                                    <option value="192">192 bits</option>
                                                    <option value="256">256 bits</option>
                                                    <option value="auto">Auto</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-3 gap-4">
                                            <div>
                                                <label class="pf-label" for="p2_hash">Hash</label>
                                                <select class="pf-select shadow-sm" id="p2_hash" name="p2_hash[]" multiple
                                                    size="3">
                                                    <option value="hmac_sha256" selected>SHA256</option>
                                                    <option value="hmac_sha1">SHA1</option>
                                                    <option value="hmac_sha384">SHA384</option>
                                                    <option value="hmac_sha512">SHA512</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="pf-label" for="p2_pfsgroup">PFS Group</label>
                                                <select class="pf-select shadow-sm" id="p2_pfsgroup" name="p2_pfsgroup">
                                                    <option value="14">14 (2048 bit)</option>
                                                    <option value="2">2 (1024 bit)</option>
                                                    <option value="0">Off</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="pf-label" for="p2_lifetime">Lifetime</label>
                                                <input class="pf-input shadow-sm" id="p2_lifetime" type="number"
                                                    name="p2_lifetime" value="3600">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        @else
                            <p class="text-red-500">Unknown Bulk Action Type: {{ $type }}</p>
                        @endif

                        <div class="mt-6 flex items-center justify-end">
                            <a href="{{ route('firewalls.index') }}"
                                class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 mr-4">Cancel</a>
                            <button type="submit" class="pf-btn pf-btn-primary shadow-sm">
                                Push to Firewalls
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
