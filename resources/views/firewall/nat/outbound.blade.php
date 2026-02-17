<x-app-layout :firewall="$firewall">
    <x-slot name="header">
        <x-firewall-header title="{{ __('Firewall NAT: Outbound') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{
        showModal: false,
        showDeleteModal: false,
        deleteId: null,
        isEdit: false,
        form: {
            id: '',
            interface: '{{ $interfaces[0]['if'] ?? 'wan' }}',
            protocol: 'tcp',
            src: '',
            srcport: '',
            dst: '',
            dstport: '',
            target: '',
            target_subnet: 32,
            descr: '',
            disabled: false,
            nonat: false,
            staticnatport: false
        },
        resetForm() {
            this.form = {
                id: '',
                interface: '{{ $interfaces[0]['if'] ?? 'wan' }}',
                protocol: 'tcp',
                src: '',
                srcport: '',
                dst: '',
                dstport: '',
                target: '',
                target_subnet: 32,
                descr: '',
                disabled: false,
                nonat: false,
                staticnatport: false
            };
            this.isEdit = false;
        },
        editRule(rule, index) {
            this.isEdit = true;
            this.form.id = index;
            this.form.interface = rule.interface || 'wan';
            this.form.protocol = rule.protocol || 'tcp';

            // Source
            if (typeof rule.source === 'object') {
                this.form.src = rule.source.address || (rule.source.network ? 'network' : 'any');
                this.form.srcport = rule.source_port || rule.source.port || '';
            } else {
                this.form.src = rule.source || 'any';
                this.form.srcport = rule.source_port || '';
            }

            // Destination
            if (typeof rule.destination === 'object') {
                this.form.dst = rule.destination.address || (rule.destination.network ? 'network' : 'any');
                this.form.dstport = rule.destination_port || rule.destination.port || '';
            } else {
                this.form.dst = rule.destination || 'any';
                this.form.dstport = rule.destination_port || '';
            }

            // Target/NAT
            // target can be object or string in API response depending on advanced mode?
            // Usually 'target' key holds the IP/Alias.
            this.form.target = rule.target || '';
            this.form.target_subnet = rule.target_subnet || 32;

            this.form.descr = rule.descr || '';
            this.form.disabled = !!rule.disabled;
            this.form.nonat = !!rule.nonat;
            this.form.staticnatport = !!rule.staticnatport;

            this.showModal = true;
        },
        confirmDelete(index) {
            this.deleteId = index;
            this.showDeleteModal = true;
        }
    }" @open-create-modal.window="resetForm(); showModal = true">

        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            <x-card>
                <div class="p-6">
                    {{-- Tabs --}}
                    @include('firewall.nat.tabs', ['active' => 'outbound'])

                    {{-- Mode Selection --}}
                    <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <form action="{{ route('firewall.nat.outbound.mode', $firewall) }}" method="POST">
                            @csrf
                            @method('PATCH')
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Outbound NAT Mode</h3>

                            <div class="space-y-4">
                                @foreach(['automatic' => 'Automatic', 'hybrid' => 'Hybrid', 'advanced' => 'Manual', 'disabled' => 'Disabled'] as $val => $label)
                                <div class="flex items-center">
                                    <input id="mode_{{ $val }}" name="mode" type="radio" value="{{ $val }}"
                                        {{ ($mode ?? 'automatic') === $val ? 'checked' : '' }}
                                        class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                                    <label for="mode_{{ $val }}"
                                        class="ml-3 block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        {{ $label }} Outbound NAT rule generation
                                    </label>
                                </div>
                                @endforeach
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn-primary">
                                    Save Mode
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Rules Table --}}
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Mappings</h3>
                        <x-button-add @click="$dispatch('open-create-modal')">
                            Add Mapping
                        </x-button-add>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider" style="width: 40px;">Status</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Interface</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Source Port</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Destination</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Dest. Port</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">NAT Address</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">NAT Port</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Static Port</th>
                                    <th class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($rules as $index => $rule)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ !empty($rule['disabled']) ? 'opacity-50' : '' }}">
                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                            <form action="{{ route('firewall.nat.outbound.toggle', ['firewall' => $firewall, 'id' => $index]) }}" method="POST" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="focus:outline-none" title="Toggle Status">
                                                    @if(!empty($rule['disabled']))
                                                        <svg class="w-5 h-5 text-red-500 mx-auto hover:text-red-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    @else
                                                        <svg class="w-5 h-5 text-green-500 mx-auto hover:text-green-700 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    @endif
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ strtoupper($rule['interface'] ?? '') }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ is_array($rule['source']) ? ($rule['source']['network'] ?? 'any') : $rule['source'] }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ is_array($rule['source']) && isset($rule['source']['port']) ? $rule['source']['port'] : '*' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ is_array($rule['destination']) ? ($rule['destination']['network'] ?? 'any') : $rule['destination'] }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ is_array($rule['destination']) && isset($rule['destination']['port']) ? $rule['destination']['port'] : '*' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $rule['target'] ?? '' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ $rule['local-port'] ?? '' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ isset($rule['static-port']) ? 'Yes' : 'No' }}</td>
                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">{{ $rule['descr'] ?? '' }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center items-center space-x-2">
                                                <button @click="editRule({{ json_encode($rule) }}, {{ $index }})"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                    title="Edit">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button @click="confirmDelete({{ $index }})"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                    title="Delete">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No outbound rules defined.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Modal --}}
        <div x-show="showModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showModal" class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>

                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showModal"
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                    <form
                        :action="isEdit ? '{{ url('firewall/' . $firewall->getRouteKey() . '/nat/outbound') }}/' + form.id : '{{ route('firewall.nat.outbound.store', $firewall) }}'"
                        method="POST">
                        @csrf
                        <template x-if="isEdit">
                            @method('PUT')
                        </template>

                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                                x-text="isEdit ? 'Edit Outbound NAT Mapping' : 'Add Outbound NAT Mapping'"></h3>

                            <div class="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                {{-- Disabled --}}
                                <div class="sm:col-span-6">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="disabled" name="disabled" type="checkbox" x-model="form.disabled"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="disabled" class="font-medium text-gray-700 dark:text-gray-300">Disable this rule</label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Do Not NAT --}}
                                <div class="sm:col-span-6">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="nonat" name="nonat" type="checkbox" x-model="form.nonat"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="nonat" class="font-medium text-gray-700 dark:text-gray-300">Do not NAT</label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Interface --}}
                                <div class="sm:col-span-3">
                                    <label for="interface" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Interface</label>
                                    <select id="interface" name="interface" x-model="form.interface"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                        @foreach($interfaces as $iface)
                                            <option value="{{ $iface['if'] ?? $iface['descr'] }}">
                                                {{ $iface['descr'] ?? strtoupper($iface['if']) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                {{-- Protocol --}}
                                <div class="sm:col-span-3">
                                    <label for="protocol" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Protocol</label>
                                    <select id="protocol" name="protocol" x-model="form.protocol"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                        <option value="tcp">TCP</option>
                                        <option value="udp">UDP</option>
                                        <option value="tcp/udp">TCP/UDP</option>
                                        <option value="icmp">ICMP</option>
                                        <option value="esp">ESP</option>
                                        <option value="ah">AH</option>
                                        <option value="gre">GRE</option>
                                        <option value="ipv6">IPv6</option>
                                        <option value="igmp">IGMP</option>
                                        <option value="any">Any</option>
                                    </select>
                                </div>

                                {{-- Source --}}
                                <div class="sm:col-span-3">
                                    <label for="src" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source</label>
                                    <input type="text" name="src" id="src" x-model="form.src"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="Network, IP or Alias">
                                </div>

                                {{-- Source Port --}}
                                <div class="sm:col-span-3">
                                    <label for="srcport" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source Port</label>
                                    <input type="text" name="srcport" id="srcport" x-model="form.srcport"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="Port, Range or Alias">
                                </div>

                                {{-- Destination --}}
                                <div class="sm:col-span-3">
                                    <label for="dst" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destination</label>
                                    <input type="text" name="dst" id="dst" x-model="form.dst"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="Network, IP or Alias">
                                </div>

                                {{-- Destination Port --}}
                                <div class="sm:col-span-3">
                                    <label for="dstport" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destination Port</label>
                                    <input type="text" name="dstport" id="dstport" x-model="form.dstport"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="Port, Range or Alias">
                                </div>

                                {{-- NAT Address --}}
                                <div class="sm:col-span-4">
                                    <label for="target" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Translation Address</label>
                                    <input type="text" name="target" id="target" x-model="form.target"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="IP Address or Alias (e.g. WAN interface address)">
                                </div>

                                {{-- Subnet --}}
                                <div class="sm:col-span-2">
                                     <label for="target_subnet" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Subnet</label>
                                     <select id="target_subnet" name="target_subnet" x-model="form.target_subnet"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                        @for($i = 32; $i >= 1; $i--)
                                            <option value="{{ $i }}">/{{ $i }}</option>
                                        @endfor
                                    </select>
                                </div>

                                {{-- Static Port --}}
                                <div class="sm:col-span-6">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="staticnatport" name="staticnatport" type="checkbox" x-model="form.staticnatport"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="staticnatport" class="font-medium text-gray-700 dark:text-gray-300">Static Port</label>
                                            <p class="text-gray-500 dark:text-gray-400">Do not randomize source port</p>
                                        </div>
                                    </div>
                                </div>

                                {{-- Description --}}
                                <div class="sm:col-span-6">
                                    <label for="descr" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                    <input type="text" name="descr" id="descr" x-model="form.descr"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Save
                            </button>
                            <button type="button" @click="showModal = false"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Delete Modal --}}
        <div x-show="showDeleteModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showDeleteModal" class="fixed inset-0 transition-opacity" aria-hidden="true">
                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                </div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
                <div x-show="showDeleteModal"
                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                                    Delete Rule
                                </h3>
                                <div class="mt-2">
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        Are you sure you want to delete this rule? This action cannot be undone.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <form
                            :action="'{{ url('firewall/' . $firewall->getRouteKey() . '/nat/outbound') }}/' + deleteId"
                            method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Delete
                            </button>
                        </form>
                        <button type="button" @click="showDeleteModal = false"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div></x-app-layout>
