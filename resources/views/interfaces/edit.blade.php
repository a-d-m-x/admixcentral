<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="Edit Interface: {{ $interface['descr'] ?? $interfaceId }}" :firewall="$firewall">
            <x-slot name="actions">
                <a href="{{ route('firewall.interfaces.index', $firewall) }}"
                    class="bg-gray-500 hover:bg-gray-700 text-white font-bold py-2 px-4 rounded">
                    Cancel
                </a>
            </x-slot>
        </x-firewall-header>
    </x-slot>

    <div class="py-12"
        x-data="{ showWarning: false, ipv4Type: {{ Js::from($interface['type'] ?? 'dhcp') }}, ipv6Type: {{ Js::from($interface['type6'] ?? 'dhcp6') }} }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"
                            role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <form id="edit-interface-form"
                        action="{{ route('firewall.interfaces.update', [$firewall, $interfaceId]) }}" method="POST"
                        @submit.prevent="showWarning = true">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="if" value="{{ $interface['if'] ?? $interfaceId }}">

                        {{-- General Configuration --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4 border-b pb-2">General Configuration</h3>

                            <div class="grid grid-cols-1 gap-y-4">
                                {{-- Enable --}}
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" id="enable" name="enable"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            {{ isset($interface['enable']) && $interface['enable'] ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="enable" class="font-medium text-gray-700 dark:text-gray-300">Enable
                                            Interface</label>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <div>
                                    <label for="descr"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                    <input type="text" name="descr" id="descr" value="{{ $interface['descr'] ?? '' }}"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>

                                {{-- IPv4 Configuration Type --}}
                                <div>
                                    <label for="type"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv4
                                        Configuration Type</label>
                                    <select name="type" id="type" x-model="ipv4Type"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="dhcp" {{ ($interface['type'] ?? '') == 'dhcp' ? 'selected' : '' }}>
                                            DHCP</option>
                                        <option value="pppoe" {{ ($interface['type'] ?? '') == 'pppoe' ? 'selected' : '' }}>
                                            PPPoE</option>
                                        <option value="static" {{ ($interface['type'] ?? '') == 'static' ? 'selected' : '' }}>Static IPv4</option>
                                        <option value="none" {{ ($interface['type'] ?? '') == 'none' ? 'selected' : '' }}>
                                            None</option>
                                        {{-- Add other types as needed --}}
                                    </select>
                                </div>

                                {{-- IPv6 Configuration Type --}}
                                <div>
                                    <label for="type6"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv6
                                        Configuration Type</label>
                                    <select name="type6" id="type6" x-model="ipv6Type"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="dhcp6" {{ ($interface['type6'] ?? '') == 'dhcp6' ? 'selected' : '' }}>DHCP6</option>
                                        <option value="static6" {{ ($interface['type6'] ?? '') == 'static6' ? 'selected' : '' }}>Static IPv6</option>
                                        <option value="none" {{ ($interface['type6'] ?? '') == 'none' ? 'selected' : '' }}>None</option>
                                        {{-- Add other types as needed --}}
                                    </select>
                                </div>

                                {{-- MAC Address --}}
                                <div>
                                    <label for="mac"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">MAC
                                        Address</label>
                                    <input type="text" name="mac" id="mac" value="{{ $interface['mac'] ?? '' }}"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm"
                                        placeholder="xx:xx:xx:xx:xx:xx">
                                </div>

                                {{-- MTU --}}
                                <div>
                                    <label for="mtu"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">MTU</label>
                                    <input type="number" name="mtu" id="mtu" value="{{ $interface['mtu'] ?? '' }}"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>

                                {{-- MSS --}}
                                <div>
                                    <label for="mss"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">MSS</label>
                                    <input type="number" name="mss" id="mss" value="{{ $interface['mss'] ?? '' }}"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>

                                {{-- Speed and Duplex --}}
                                <div>
                                    <label for="media"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Speed and
                                        Duplex</label>
                                    <select name="media" id="media"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="" {{ empty($interface['media']) ? 'selected' : '' }}>Default
                                        </option>
                                        {{-- This list would ideally be populated dynamically based on interface
                                        capabilities --}}
                                        <option value="autoselect" {{ ($interface['media'] ?? '') == 'autoselect' ? 'selected' : '' }}>Autoselect</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- PPPoE Configuration --}}
                        <div class="mb-8" x-show="ipv4Type === 'pppoe'">
                            <h3 class="text-lg font-semibold mb-4 border-b pb-2">PPPoE Configuration</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="pppoe_username"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Username</label>
                                    <input type="text" name="pppoe_username" id="pppoe_username"
                                        value="{{ $interface['pppoe_username'] ?? '' }}"
                                        :disabled="ipv4Type !== 'pppoe'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="pppoe_password"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Password</label>
                                    <input type="password" name="pppoe_password" id="pppoe_password"
                                        value="{{ $interface['pppoe_password'] ?? '' }}"
                                        :disabled="ipv4Type !== 'pppoe'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="pppoe_service"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Service
                                        Name</label>
                                    <input type="text" name="pppoe_service" id="pppoe_service"
                                        value="{{ $interface['pppoe_service'] ?? '' }}"
                                        :disabled="ipv4Type !== 'pppoe'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="pppoe_dialondemand"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dial-on-demand</label>
                                    <div class="mt-2">
                                        <label class="inline-flex items-center">
                                            <input type="checkbox" name="pppoe_dialondemand" id="pppoe_dialondemand"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                                :disabled="ipv4Type !== 'pppoe'"
                                                {{ isset($interface['pppoe_dialondemand']) && $interface['pppoe_dialondemand'] ? 'checked' : '' }}>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Enable
                                                Dial-on-demand</span>
                                        </label>
                                    </div>
                                </div>
                                <div>
                                    <label for="pppoe_idletimeout"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Idle
                                        Timeout (seconds)</label>
                                    <input type="number" name="pppoe_idletimeout" id="pppoe_idletimeout"
                                        value="{{ $interface['pppoe_idletimeout'] ?? '' }}"
                                        :disabled="ipv4Type !== 'pppoe'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="pppoe_periodic_reset"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Periodic
                                        Reset</label>
                                    <select name="pppoe_periodic_reset" id="pppoe_periodic_reset"
                                        :disabled="ipv4Type !== 'pppoe'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        <option value="disabled" {{ ($interface['pppoe_periodic_reset'] ?? '') == 'disabled' ? 'selected' : '' }}>Disabled</option>
                                        <option value="daily" {{ ($interface['pppoe_periodic_reset'] ?? '') == 'daily' ? 'selected' : '' }}>Daily</option>
                                        <option value="weekly" {{ ($interface['pppoe_periodic_reset'] ?? '') == 'weekly' ? 'selected' : '' }}>Weekly</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        {{-- Static IPv4 Configuration --}}
                        <div class="mb-8" x-show="ipv4Type === 'static'">
                            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Static IPv4 Configuration</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="ipaddr"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv4
                                        Address</label>
                                    <input type="text" name="ipaddr" id="ipaddr"
                                        value="{{ $interface['ipaddr'] ?? '' }}"
                                        :disabled="ipv4Type !== 'static'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="subnet"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv4 Subnet
                                        Bit Count</label>
                                    <select name="subnet" id="subnet"
                                        :disabled="ipv4Type !== 'static'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        @for ($i = 32; $i >= 1; $i--)
                                            <option value="{{ $i }}" {{ ($interface['subnet'] ?? '') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="gateway"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv4 Upstream
                                        Gateway</label>
                                    <input type="text" name="gateway" id="gateway"
                                        value="{{ $interface['gateway'] ?? '' }}"
                                        :disabled="ipv4Type !== 'static'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                            </div>
                        </div>

                        {{-- Static IPv6 Configuration --}}
                        <div class="mb-8" x-show="ipv6Type === 'static6'">
                            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Static IPv6 Configuration</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="ipaddrv6"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv6
                                        Address</label>
                                    <input type="text" name="ipaddrv6" id="ipaddrv6"
                                        value="{{ $interface['ipaddrv6'] ?? '' }}"
                                        :disabled="ipv6Type !== 'static6'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div>
                                    <label for="subnetv6"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv6 Prefix
                                        Length</label>
                                    <select name="subnetv6" id="subnetv6"
                                        :disabled="ipv6Type !== 'static6'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                        @for ($i = 128; $i >= 1; $i--)
                                            <option value="{{ $i }}" {{ ($interface['subnetv6'] ?? '') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>
                                <div class="md:col-span-2">
                                    <label for="gatewayv6"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">IPv6 Upstream
                                        Gateway</label>
                                    <input type="text" name="gatewayv6" id="gatewayv6"
                                        value="{{ $interface['gatewayv6'] ?? '' }}"
                                        :disabled="ipv6Type !== 'static6'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                            </div>
                        </div>

                        {{-- DHCP Client Configuration --}}
                        <div class="mb-8" x-show="ipv4Type === 'dhcp'">
                            <h3 class="text-lg font-semibold mb-4 border-b pb-2">DHCP Client Configuration</h3>
                            <div class="grid grid-cols-1 gap-y-4">
                                <div>
                                    <label for="dhcphostname"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Hostname</label>
                                    <input type="text" name="dhcphostname" id="dhcphostname"
                                        value="{{ $interface['dhcphostname'] ?? '' }}"
                                        :disabled="ipv4Type !== 'dhcp'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="alias-address"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alias
                                            IPv4 Address</label>
                                        <input type="text" name="alias-address" id="alias-address"
                                            value="{{ $interface['alias-address'] ?? '' }}"
                                            :disabled="ipv4Type !== 'dhcp'"
                                            class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                    </div>
                                    <div>
                                        <label for="alias-subnet"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-300">Alias
                                            IPv4 Subnet Bit Count</label>
                                        <select name="alias-subnet" id="alias-subnet"
                                            :disabled="ipv4Type !== 'dhcp'"
                                            class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                            <option value="">None</option>
                                            @for ($i = 32; $i >= 1; $i--)
                                                <option value="{{ $i }}" {{ ($interface['alias-subnet'] ?? '') == $i ? 'selected' : '' }}>{{ $i }}</option>
                                            @endfor
                                        </select>
                                    </div>
                                </div>
                                <div>
                                    <label for="dhcprejectfrom"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Reject Leases
                                        From</label>
                                    <input type="text" name="dhcprejectfrom" id="dhcprejectfrom"
                                        value="{{ is_array($interface['dhcprejectfrom'] ?? '') ? implode(',', $interface['dhcprejectfrom']) : ($interface['dhcprejectfrom'] ?? '') }}"
                                        :disabled="ipv4Type !== 'dhcp'"
                                        class="mt-1 block w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-blue-500 focus:ring-blue-500 sm:text-sm">
                                </div>
                            </div>
                        </div>

                        {{-- Reserved Networks --}}
                        <div class="mb-8">
                            <h3 class="text-lg font-semibold mb-4 border-b pb-2">Reserved Networks</h3>
                            <div class="space-y-4">
                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" id="blockpriv" name="blockpriv"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            {{ isset($interface['blockpriv']) && $interface['blockpriv'] ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="blockpriv"
                                            class="font-medium text-gray-700 dark:text-gray-300">Block private networks
                                            and loopback addresses</label>
                                        <p class="text-gray-500 dark:text-gray-400">Blocks traffic from IP addresses
                                            that are reserved for private networks per RFC 1918 (10/8, 172.16/12,
                                            192.168/16) and loopback addresses (127/8). This should generally be turned
                                            on for WAN interfaces.</p>
                                    </div>
                                </div>

                                <div class="flex items-start">
                                    <div class="flex items-center h-5">
                                        <input type="checkbox" id="blockbogon" name="blockbogon"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500"
                                            {{ isset($interface['blockbogon']) && $interface['blockbogon'] ? 'checked' : '' }}>
                                    </div>
                                    <div class="ml-3 text-sm">
                                        <label for="blockbogon"
                                            class="font-medium text-gray-700 dark:text-gray-300">Block bogon
                                            networks</label>
                                        <p class="text-gray-500 dark:text-gray-400">Blocks traffic from IP addresses
                                            that are reserved (including unassigned) by IANA. This should generally be
                                            turned on for WAN interfaces.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex gap-3 mt-6">
                            <button type="submit"
                                class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded shadow-sm">
                                Save
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Warning Modal --}}
        <div x-show="showWarning" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog"
            aria-modal="true" style="display: none;">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showWarning" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                    x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                    x-transition:leave-end="opacity-0"
                    class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showWarning" x-transition:enter="ease-out duration-300"
                    x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave="ease-in duration-200"
                    x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                    x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                                    id="modal-title">
                                    Warning: Connectivity Risk
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Modifying interface settings can disrupt network connectivity, potentially
                                        cutting off access to the firewall or the internet.
                                    </p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-2">
                                        Are you sure you want to proceed with these changes?
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="button" @click="document.getElementById('edit-interface-form').submit()"
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Yes, Apply Changes
                        </button>
                        <button type="button" @click="showWarning = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
