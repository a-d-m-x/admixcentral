<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ isset($rule['tracker']) ? __('Edit Firewall Rule') : __('Add Firewall Rule') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(auth()->user()->isReadOnly())
            {{-- READ-ONLY: show back link, no form --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">Rule details are not displayed in edit form for read-only users.</p>
                    <a href="{{ route('firewall.rules.index', $firewall) }}"
                        class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                        </svg>
                        Back to Firewall Rules
                    </a>
                </div>
            </div>
            @else
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST"
                        action="{{ isset($rule['tracker']) ? route('firewall.rules.update', ['firewall' => $firewall, 'tracker' => $rule['tracker']]) : route('firewall.rules.store', $firewall) }}"
                        x-data="{ 
                              protocol: '{{ $rule['protocol'] ?? 'tcp' }}', 
                              sourceType: '{{ is_array($rule['source'] ?? null) ? (isset($rule['source']['address']) ? 'address' : (isset($rule['source']['network']) ? 'network' : 'any')) : 'any' }}',
                              destType: '{{ is_array($rule['destination'] ?? null) ? (isset($rule['destination']['address']) ? 'address' : (isset($rule['destination']['network']) ? 'network' : 'any')) : 'any' }}',
                              showAdvanced: false 
                          }">
                        @csrf
                        @if(isset($rule['tracker']))
                            @method('PUT')
                        @endif

                        {{-- Action --}}
                        <div class="mb-4">
                            <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Action</label>
                            <div class="mt-2 space-y-2">
                                <div class="flex items-center">
                                    <input type="radio" name="type" value="pass"
                                        class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" {{ ($rule['type'] ?? 'pass') === 'pass' ? 'checked' : '' }}>
                                    <label
                                        class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">Pass</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" name="type" value="block"
                                        class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" {{ ($rule['type'] ?? '') === 'block' ? 'checked' : '' }}>
                                    <label
                                        class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">Block</label>
                                </div>
                                <div class="flex items-center">
                                    <input type="radio" name="type" value="reject"
                                        class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300" {{ ($rule['type'] ?? '') === 'reject' ? 'checked' : '' }}>
                                    <label
                                        class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">Reject</label>
                                </div>
                            </div>
                        </div>

                        {{-- Disabled --}}
                        <div class="mb-4">
                            <div class="flex items-center">
                                <input type="checkbox" name="disabled"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    {{ !empty($rule['disabled']) ? 'checked' : '' }}>
                                <label class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Disable this
                                    rule</label>
                            </div>
                        </div>

                        {{-- Interface --}}
                        <div class="mb-4">
                            <label for="interface"
                                class="block font-medium text-sm text-gray-700 dark:text-gray-300">Interface</label>
                            <select name="interface" id="interface"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                @foreach($interfaces as $iface)
                                    <option value="{{ $iface['id'] }}" {{ ($rule['interface'] ?? '') === $iface['id'] ? 'selected' : '' }}>
                                        {{ strtoupper($iface['descr'] ?? $iface['id']) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Address Family --}}
                        <div class="mb-4">
                            <label for="ipprotocol"
                                class="block font-medium text-sm text-gray-700 dark:text-gray-300">Address
                                Family</label>
                            <select name="ipprotocol" id="ipprotocol"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                <option value="inet" {{ ($rule['ipprotocol'] ?? 'inet') === 'inet' ? 'selected' : '' }}>
                                    IPv4</option>
                                <option value="inet6" {{ ($rule['ipprotocol'] ?? '') === 'inet6' ? 'selected' : '' }}>IPv6
                                </option>
                                <option value="inet46" {{ ($rule['ipprotocol'] ?? '') === 'inet46' ? 'selected' : '' }}>
                                    IPv4+IPv6</option>
                            </select>
                        </div>

                        {{-- Protocol --}}
                        <div class="mb-4">
                            <label for="protocol"
                                class="block font-medium text-sm text-gray-700 dark:text-gray-300">Protocol</label>
                            <select name="protocol" id="protocol" x-model="protocol"
                                class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
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
                                <option value="any">Any</option>
                            </select>
                        </div>

                        {{-- Source --}}
                        <div class="mb-4 p-4 border border-gray-200 dark:border-gray-700 rounded-md">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Source</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Invert
                                        Match</label>
                                    <div class="flex items-center mt-1">
                                        <input type="checkbox" name="source_invert"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            {{ isset($rule['source']['not']) ? 'checked' : '' }}>
                                        <label
                                            class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Invert</label>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block font-medium text-sm text-gray-700 dark:text-gray-300">Type</label>
                                    <select name="source_type" x-model="sourceType"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                        <option value="any">Any</option>
                                        <option value="address">Address or Alias</option>
                                        <option value="network">Network</option>
                                        <option value="wanip">WAN net</option>
                                        <option value="lanip">LAN net</option>
                                    </select>
                                </div>
                                <div x-show="sourceType === 'address' || sourceType === 'network'">
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Address /
                                        Network</label>
                                    <input type="text" name="source_address"
                                        value="{{ $rule['source']['address'] ?? '' }}"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div x-show="['tcp', 'udp', 'tcp/udp'].includes(protocol)">
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Port
                                        Range</label>
                                    <div class="flex space-x-2">
                                        <input type="text" name="source_port_from" placeholder="From"
                                            value="{{ $rule['source']['port'] ?? '' }}"
                                            class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <input type="text" name="source_port_to" placeholder="To"
                                            class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Destination --}}
                        <div class="mb-4 p-4 border border-gray-200 dark:border-gray-700 rounded-md">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Destination</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Invert
                                        Match</label>
                                    <div class="flex items-center mt-1">
                                        <input type="checkbox" name="destination_invert"
                                            class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                            {{ isset($rule['destination']['not']) ? 'checked' : '' }}>
                                        <label
                                            class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Invert</label>
                                    </div>
                                </div>
                                <div>
                                    <label
                                        class="block font-medium text-sm text-gray-700 dark:text-gray-300">Type</label>
                                    <select name="destination_type" x-model="destType"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                        <option value="any">Any</option>
                                        <option value="address">Address or Alias</option>
                                        <option value="network">Network</option>
                                        <option value="wanip">WAN net</option>
                                        <option value="lanip">LAN net</option>
                                    </select>
                                </div>
                                <div x-show="destType === 'address' || destType === 'network'">
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Address /
                                        Network</label>
                                    <input type="text" name="destination_address"
                                        value="{{ $rule['destination']['address'] ?? '' }}"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                </div>
                                <div x-show="['tcp', 'udp', 'tcp/udp'].includes(protocol)">
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Port
                                        Range</label>
                                    <div class="flex space-x-2">
                                        <input type="text" name="destination_port_from" placeholder="From"
                                            value="{{ $rule['destination']['port'] ?? '' }}"
                                            class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <input type="text" name="destination_port_to" placeholder="To"
                                            class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Log --}}
                        <div class="mb-4">
                            <div class="flex items-center">
                                <input type="checkbox" name="log"
                                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                    {{ !empty($rule['log']) ? 'checked' : '' }}>
                                <label class="ml-2 block text-sm text-gray-900 dark:text-gray-100">Log packets that are
                                    handled by this rule</label>
                            </div>
                        </div>

                        {{-- Description --}}
                        <div class="mb-4">
                            <label for="descr"
                                class="block font-medium text-sm text-gray-700 dark:text-gray-300">Description</label>
                            <input type="text" name="descr" id="descr" value="{{ $rule['descr'] ?? '' }}"
                                class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        </div>

                        {{-- Advanced Options Toggle --}}
                        <div class="mb-4">
                            <button type="button" @click="showAdvanced = !showAdvanced"
                                class="flex items-center text-sm text-blue-600 hover:text-blue-900 focus:outline-none">
                                <span x-text="showAdvanced ? 'Hide Advanced Options' : 'Show Advanced Options'"></span>
                                <svg class="ml-1 h-4 w-4" :class="{'rotate-180': showAdvanced}"
                                    xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                        </div>

                        {{-- Advanced Options Section --}}
                        <div x-show="showAdvanced"
                            class="space-y-4 p-4 bg-gray-50 dark:bg-gray-700 rounded-md border border-gray-200 dark:border-gray-600">

                            {{-- Source OS --}}
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Source
                                    OS</label>
                                <select name="os"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                    <option value="">Any</option>
                                    <option value="windows">Windows</option>
                                    <option value="linux">Linux</option>
                                    <option value="freebsd">FreeBSD</option>
                                    {{-- Add more OS options as needed --}}
                                </select>
                            </div>

                            {{-- TCP Flags --}}
                            <div x-show="protocol === 'tcp' || protocol === 'tcp/udp'">
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">TCP
                                    Flags</label>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <span class="text-xs text-gray-500">Set</span>
                                        <div class="space-y-1">
                                            @foreach(['URG', 'ACK', 'PSH', 'RST', 'SYN', 'FIN'] as $flag)
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="tcp_flags_set[]" value="{{ $flag }}"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                                    <label
                                                        class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $flag }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    <div>
                                        <span class="text-xs text-gray-500">Out Of</span>
                                        <div class="space-y-1">
                                            @foreach(['URG', 'ACK', 'PSH', 'RST', 'SYN', 'FIN'] as $flag)
                                                <div class="flex items-center">
                                                    <input type="checkbox" name="tcp_flags_out_of[]" value="{{ $flag }}"
                                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                                        {{ $flag === 'SYN' || $flag === 'ACK' ? 'checked' : '' }}>
                                                    <label
                                                        class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $flag }}</label>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- State Type --}}
                            <div>
                                <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">State
                                    Type</label>
                                <select name="statetype"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                    <option value="keep state">Keep state</option>
                                    <option value="sloppy state">Sloppy state</option>
                                    <option value="synproxy state">Synproxy state</option>
                                    <option value="none">None</option>
                                </select>
                            </div>

                            {{-- No XMLRPC Sync --}}
                            <div>
                                <div class="flex items-center">
                                    <input type="checkbox" name="nosync"
                                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50">
                                    <label class="ml-2 block text-sm text-gray-900 dark:text-gray-100">No XMLRPC
                                        Sync</label>
                                </div>
                            </div>

                            {{-- Schedule --}}
                            <div>
                                <label
                                    class="block font-medium text-sm text-gray-700 dark:text-gray-300">Schedule</label>
                                <select name="sched"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                    <option value="">None</option>
                                    {{-- Schedules would be populated here --}}
                                </select>
                            </div>

                            {{-- Gateway --}}
                            <div>
                                <label
                                    class="block font-medium text-sm text-gray-700 dark:text-gray-300">Gateway</label>
                                <select name="gateway"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                    <option value="">Default</option>
                                    {{-- Gateways would be populated here --}}
                                </select>
                            </div>

                            {{-- In/Out Pipe --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">In
                                        Pipe</label>
                                    <select name="dnpipe"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                        <option value="">None</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block font-medium text-sm text-gray-700 dark:text-gray-300">Out
                                        Pipe</label>
                                    <select name="pdnpipe"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>

                            {{-- Ackqueue/Queue --}}
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label
                                        class="block font-medium text-sm text-gray-700 dark:text-gray-300">Ackqueue</label>
                                    <select name="ackqueue"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                        <option value="">None</option>
                                    </select>
                                </div>
                                <div>
                                    <label
                                        class="block font-medium text-sm text-gray-700 dark:text-gray-300">Queue</label>
                                    <select name="defaultqueue"
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300">
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <a href="{{ route('firewall.rules.index', $firewall) }}"
                                class="inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:bg-gray-300 active:bg-gray-400 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150 mr-2">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 focus:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif
        </div>
    </div>
</x-app-layout>
