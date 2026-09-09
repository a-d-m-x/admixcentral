<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System: Routing') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <!-- Error/Success Messages -->
            @if ($errors->any())
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <strong class="font-bold">Whoops!</strong>
                    <span class="block sm:inline">There were some problems with your input.</span>
                    <ul class="mt-3 list-disc list-inside text-sm text-red-600">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <x-card x-data="{
                    showModal: false,
                    editing: false,
                    activeTab: '{{ $tab }}',
                    gatewayForm: { id: '', name: '', interface: '', ipprotocol: 'inet', gateway: '', descr: '' },
                    staticRouteForm: { id: '', network: '', gateway: '', descr: '' },
                    gatewayGroupForm: { id: '', name: '', item: [], tiers: {}, trigger: 'down', descr: '' },
                    deleteAction: '',
                    confirmDelete(action) {
                        if (confirm('Are you sure you want to delete this item?')) {
                            let form = document.getElementById('delete-form');
                            form.action = action;
                            form.submit();
                        }
                    },
                    openGatewayModal(gateway = null) {
                        this.editing = !!gateway;
                        this.gatewayForm = gateway ? { ...gateway } : { id: '', name: '', interface: '', ipprotocol: 'inet', gateway: '', descr: '' };
                        this.showModal = true;
                    },
                    openStaticRouteModal(route = null) {
                        this.editing = !!route;
                        this.staticRouteForm = route ? { ...route } : { id: '', network: '', gateway: '', descr: '' };
                        this.showModal = true;
                    },
                    openGatewayGroupModal(group = null) {
                        this.editing = !!group;
                        let tiers = {};
                        if (group && Array.isArray(group.item)) {
                            group.item.forEach(itemStr => {
                                const parts = String(itemStr).split('|');
                                if (parts.length >= 2) {
                                    tiers[parts[0]] = parts[1];
                                }
                            });
                        }
                        this.gatewayGroupForm = group ? { ...group, tiers: tiers } : { id: '', name: '', item: [], tiers: {}, trigger: 'down', descr: '' };
                        this.showModal = true;
                    }
                }"
                @open-gateway-modal.window="openGatewayModal()"
                @open-static-route-modal.window="openStaticRouteModal()"
                @open-gateway-group-modal.window="openGatewayGroupModal()">

                    <!-- Tabs -->
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            <a href="{{ route('firewall.system.routing', ['firewall' => $firewall, 'tab' => 'gateways']) }}"
                                class="{{ $tab === 'gateways' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Gateways
                            </a>
                            <a href="{{ route('firewall.system.routing', ['firewall' => $firewall, 'tab' => 'static_routes']) }}"
                                class="{{ $tab === 'static_routes' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Static Routes
                            </a>
                            <a href="{{ route('firewall.system.routing', ['firewall' => $firewall, 'tab' => 'gateway_groups']) }}"
                                class="{{ $tab === 'gateway_groups' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Gateway Groups
                            </a>
                        </nav>
                    </div>

                    <!-- Gateways Tab -->
                    @if($tab === 'gateways')
                        <x-card-header title="Gateways">
                            @if(!auth()->user()->isReadOnly() && $firewall->os_type !== 'opnsense')
                            <x-button-add @click="openGatewayModal()">
                                Add Gateway
                            </x-button-add>
                            @endif
                        </x-card-header>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Name</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Interface</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Gateway</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description</th>
                                        @if($firewall->os_type !== 'opnsense')
                                        <th class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($data['gateways'] as $gateway)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $gateway['name'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $gateway['interface'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $gateway['gateway'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $gateway['descr'] ?? '' }}</td>
                                            @if(!auth()->user()->isReadOnly() && $firewall->os_type !== 'opnsense')
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button @click="openGatewayModal({{ json_encode($gateway) }})"
                                                    class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                                                <button
                                                    @click="confirmDelete({{ Js::from(route('firewall.system.routing.gateways.destroy', ['firewall' => $firewall, 'id' => $gateway['id']])) }})"
                                                    class="text-red-600 hover:text-red-900">Delete</button>
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No gateways found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <!-- Static Routes Tab -->
                    @if($tab === 'static_routes')
                        <x-card-header title="Static Routes">
                            @if(!auth()->user()->isReadOnly())
                            <x-button-add @click="openStaticRouteModal()">
                                Add Static Route
                            </x-button-add>
                            @endif
                        </x-card-header>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Network</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Gateway</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description</th>
                                        <th class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($data['static_routes'] as $route)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $route['network'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $route['gateway'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $route['descr'] ?? '' }}</td>
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button @click="openStaticRouteModal({{ json_encode($route) }})"
                                                    class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                                                <button
                                                    @click="confirmDelete({{ Js::from(route('firewall.system.routing.static-routes.destroy', ['firewall' => $firewall, 'id' => $route['id']])) }})"
                                                    class="text-red-600 hover:text-red-900">Delete</button>
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No static routes found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <!-- Gateway Groups Tab -->
                    @if($tab === 'gateway_groups')
                        <x-card-header title="Gateway Groups">
                            @if(!auth()->user()->isReadOnly() && $firewall->os_type !== 'opnsense')
                            <x-button-add @click="openGatewayGroupModal()">
                                Add Gateway Group
                            </x-button-add>
                            @endif
                        </x-card-header>

                        @if($firewall->os_type === 'opnsense')
                            <div class="mb-4 bg-blue-50 dark:bg-blue-900/30 border-l-4 border-blue-400 p-4 rounded-r-md">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-blue-400" viewBox="0 0 20 20" fill="currentColor">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-blue-700 dark:text-blue-300">
                                            Gateway Groups on OPNsense are managed directly in the OPNsense Web GUI.
                                            <a href="{{ rtrim($firewall->url, '/') }}/system_gateway_groups.php" target="_blank" rel="noopener noreferrer" class="font-semibold underline ml-1 hover:text-blue-600 dark:hover:text-blue-200 inline-flex items-center">
                                                Open OPNsense Gateway Groups
                                                <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                                </svg>
                                            </a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                        @endif

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Group Name</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Gateways</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Description</th>
                                        @if($firewall->os_type !== 'opnsense')
                                        <th class="relative px-6 py-3"><span class="sr-only">Actions</span></th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($data['gateway_groups'] ?? [] as $group)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                                {{ $group['name'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if(!isset($group['item']))
                                                    <span class="text-gray-400 italic text-xs">No gateways</span>
                                                @elseif(is_array($group['item']))
                                                    {{ implode(', ', array_map(function ($item) {
                                                        return explode('|', $item)[0]; }, $group['item'])) }}
                                                @else
                                                    {{ $group['item'] }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                {{ $group['descr'] ?? '' }}</td>
                                            @if(!auth()->user()->isReadOnly() && $firewall->os_type !== 'opnsense')
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <button @click="openGatewayGroupModal({{ json_encode($group) }})"
                                                    class="text-indigo-600 hover:text-indigo-900 mr-4">Edit</button>
                                                <button
                                                    @click="confirmDelete({{ Js::from(route('firewall.system.routing.gateway-groups.destroy', ['firewall' => $firewall, 'id' => $group['id'] ?? ''])) }})"
                                                    class="text-red-600 hover:text-red-900">Delete</button>
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ $firewall->os_type === 'opnsense' ? '3' : '4' }}" class="px-6 py-4 text-center text-gray-500">No gateway groups found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    @if(!auth()->user()->isReadOnly())
                    <!-- Modal -->
                    <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
                        <div
                            class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div x-show="showModal" class="fixed inset-0 transition-opacity" aria-hidden="true">
                                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                            </div>
                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                                aria-hidden="true">&#8203;</span>
                            <div x-show="showModal"
                                class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">

                                <!-- Gateway Form -->
                                <form x-show="activeTab === 'gateways'"
                                    :action="editing ? '{{ route('firewall.system.routing.gateways.update', ['firewall' => $firewall, 'id' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', gatewayForm.id) : '{{ route('firewall.system.routing.gateways.store', ['firewall' => $firewall]) }}'"
                                    method="POST">
                                    @csrf
                                    <template x-if="editing"><input type="hidden" name="_method"
                                            value="PATCH"></template>
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <h3 class="text-lg font-medium text-gray-900"
                                            x-text="editing ? 'Edit Gateway' : 'Add Gateway'"></h3>
                                        <div class="mt-4 space-y-4">
                                            <div>
                                                <x-input-label for="gw_interface" :value="__('Interface')" />
                                                <select id="gw_interface" name="interface"
                                                    x-model="gatewayForm.interface"
                                                    class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                                    <option value="wan">WAN</option>
                                                    <option value="lan">LAN</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label for="gw_ipprotocol" :value="__('IP Protocol')" />
                                                <select id="gw_ipprotocol" name="ipprotocol" x-model="gatewayForm.ipprotocol" class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                                    <option value="inet">IPv4</option>
                                                    <option value="inet6">IPv6</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label for="gw_name" :value="__('Name')" />
                                                <x-text-input id="gw_name" class="block mt-1 w-full" type="text"
                                                    name="name" x-model="gatewayForm.name" required />
                                            </div>
                                            <div>
                                                <x-input-label for="gw_gateway" :value="__('Gateway IP')" />
                                                <x-text-input id="gw_gateway" class="block mt-1 w-full" type="text"
                                                    name="gateway" x-model="gatewayForm.gateway" required />
                                            </div>
                                            <div>
                                                <x-input-label for="gw_descr" :value="__('Description')" />
                                                <x-text-input id="gw_descr" class="block mt-1 w-full" type="text"
                                                    name="descr" x-model="gatewayForm.descr" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="submit"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Save</button>
                                        <button type="button" @click="showModal = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                                    </div>
                                </form>

                                <!-- Static Route Form -->
                                <form x-show="activeTab === 'static_routes'"
                                    :action="editing ? '{{ route('firewall.system.routing.static-routes.update', ['firewall' => $firewall, 'id' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', staticRouteForm.id) : '{{ route('firewall.system.routing.static-routes.store', ['firewall' => $firewall]) }}'"
                                    method="POST">
                                    @csrf
                                    <template x-if="editing"><input type="hidden" name="_method"
                                            value="PATCH"></template>
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <h3 class="text-lg font-medium text-gray-900"
                                            x-text="editing ? 'Edit Static Route' : 'Add Static Route'"></h3>
                                        <div class="mt-4 space-y-4">
                                            <div>
                                                <x-input-label for="sr_network" :value="__('Destination Network')" />
                                                <x-text-input id="sr_network" class="block mt-1 w-full" type="text"
                                                    name="network" x-model="staticRouteForm.network" required />
                                            </div>
                                            <div>
                                                <x-input-label for="sr_gateway" :value="__('Gateway')" />
                                                <select id="sr_gateway" name="gateway" x-model="staticRouteForm.gateway"
                                                        class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                                    @isset($data['gateways'])
                                                        @foreach($data['gateways'] as $gw)
                                                            <option value="{{ $gw['name'] }}">{{ $gw['name'] }} ({{ $gw['gateway'] }})</option>
                                                        @endforeach
                                                    @endisset
                                                    <option value="Null4">Null4 - 127.0.0.1</option>
                                                    <option value="Null6">Null6 - ::1</option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label for="sr_descr" :value="__('Description')" />
                                                <x-text-input id="sr_descr" class="block mt-1 w-full" type="text"
                                                    name="descr" x-model="staticRouteForm.descr" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="submit"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Save</button>
                                        <button type="button" @click="showModal = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                                    </div>
                                </form>

                                <!-- Gateway Group Form -->
                                <form x-show="activeTab === 'gateway_groups'"
                                    :action="editing ? '{{ route('firewall.system.routing.gateway-groups.update', ['firewall' => $firewall, 'id' => 'PLACEHOLDER']) }}'.replace('PLACEHOLDER', gatewayGroupForm.id) : '{{ route('firewall.system.routing.gateway-groups.store', ['firewall' => $firewall]) }}'"
                                    method="POST">
                                    @csrf
                                    <template x-if="editing"><input type="hidden" name="_method"
                                            value="PATCH"></template>
                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <h3 class="text-lg font-medium text-gray-900"
                                            x-text="editing ? 'Edit Gateway Group' : 'Add Gateway Group'"></h3>
                                        <div class="mt-4 space-y-4">
                                            <div>
                                                <x-input-label for="gg_name" :value="__('Group Name')" />
                                                <x-text-input id="gg_name" class="block mt-1 w-full" type="text"
                                                    name="name" x-model="gatewayGroupForm.name" required />
                                            </div>
                                            <div>
                                                <x-input-label :value="__('Gateway Priority / Tiers')" class="mb-1" />
                                                <p class="text-xs text-gray-500 mb-2">Assign each gateway to a tier (Tier 1 = highest priority). Gateways in the same tier will load balance.</p>
                                                <div class="border border-gray-200 dark:border-gray-700 rounded-md divide-y divide-gray-200 dark:divide-gray-700 max-h-48 overflow-y-auto">
                                                    @forelse($data['gateways'] ?? [] as $gw)
                                                        <div class="p-2.5 flex items-center justify-between text-sm">
                                                            <div>
                                                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $gw['name'] }}</span>
                                                                <span class="text-xs text-gray-500 ml-1.5">({{ $gw['gateway'] ?? $gw['interface'] ?? 'Gateway' }})</span>
                                                            </div>
                                                            <div class="w-32">
                                                                <select name="tiers[{{ $gw['name'] }}]"
                                                                    x-model="gatewayGroupForm.tiers['{{ $gw['name'] }}']"
                                                                    class="block w-full text-xs border-gray-300 dark:border-gray-700 dark:bg-gray-800 rounded-md shadow-sm">
                                                                    <option value="never">Never</option>
                                                                    <option value="1">Tier 1</option>
                                                                    <option value="2">Tier 2</option>
                                                                    <option value="3">Tier 3</option>
                                                                    <option value="4">Tier 4</option>
                                                                    <option value="5">Tier 5</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    @empty
                                                        <div class="p-3 text-sm text-gray-500 italic">No gateways configured.</div>
                                                    @endforelse
                                                </div>
                                            </div>
                                            <div>
                                                <x-input-label for="gg_trigger" :value="__('Trigger Level')" />
                                                <select id="gg_trigger" name="trigger"
                                                    x-model="gatewayGroupForm.trigger"
                                                    class="block mt-1 w-full border-gray-300 rounded-md shadow-sm">
                                                    <option value="down">Member Down</option>
                                                    <option value="down,packetloss">Packet Loss</option>
                                                    <option value="down,packetloss,highlatency">High Latency</option>
                                                    <option value="down,packetloss,highlatency,memberdown">Member Down
                                                    </option>
                                                </select>
                                            </div>
                                            <div>
                                                <x-input-label for="gg_descr" :value="__('Description')" />
                                                <x-text-input id="gg_descr" class="block mt-1 w-full" type="text"
                                                    name="descr" x-model="gatewayGroupForm.descr" />
                                            </div>
                                        </div>
                                    </div>
                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="submit"
                                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 sm:ml-3 sm:w-auto sm:text-sm">Save</button>
                                        <button type="button" @click="showModal = false"
                                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">Cancel</button>
                                    </div>
                                </form>

                            </div>
                        </div>
                    </div>

                    <!-- Hidden Delete Form -->
                    <form id="delete-form" method="POST" style="display: none;">
                        @csrf
                        @method('DELETE')
                    </form>
                    @endif

            </x-card>
        </div>
    </div>
</x-app-layout>
