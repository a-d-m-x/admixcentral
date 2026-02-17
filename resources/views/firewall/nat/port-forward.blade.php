<x-app-layout :firewall="$firewall">
    <x-slot name="header">
        <x-firewall-header title="{{ __('Firewall NAT: Port Forward') }}" :firewall="$firewall" />
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
            local_port: '',
            descr: '',
            disabled: false,
            natreflection: 'enable',
            associated_rule_id: 'pass'
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
                local_port: '',
                descr: '',
                disabled: false,
                natreflection: 'enable',
                associated_rule_id: 'pass'
            };
            this.isEdit = false;
        },
        editRule(rule, index) {
            this.isEdit = true;
            this.form.id = index; // Use index as ID for now since API uses array index
            this.form.interface = rule.interface || 'wan';
            this.form.protocol = rule.protocol || 'tcp';
            
            // Handle source
            if (typeof rule.source === 'object') {
                this.form.src = rule.source.address || (rule.source.network ? 'network' : 'any');
                this.form.srcport = rule.source_port || rule.source.port || '';
            } else {
                // If mixed flat/nested, prefer flat source_port
                this.form.src = rule.source || 'any';
                this.form.srcport = rule.source_port || '';
            }

            // Handle dest
            if (typeof rule.destination === 'object') {
                this.form.dst = rule.destination.address || (rule.destination.network ? 'network' : 'any');
                this.form.dstport = rule.destination_port || rule.destination.port || '';
            } else {
                this.form.dst = rule.destination || 'any';
                this.form.dstport = rule.destination_port || '';
            }

            this.form.target = rule.target || '';
            this.form.local_port = rule.local_port || rule['local-port'] || '';
            this.form.descr = rule.descr || '';
            this.form.disabled = !!rule.disabled;
            this.form.natreflection = rule.natreflection || 'enable';
            this.form.associated_rule_id = rule['associated-rule-id'] || rule['associated_rule_id'] || 'pass';
            
            this.showModal = true;
        },
        confirmDelete(index) {
            this.deleteId = index;
            this.showDeleteModal = true;
        }
    }" @open-create-modal.window="resetForm(); showModal = true">

        <div class="max-w-full mx-auto sm:px-6 lg:px-8">


            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    {{-- Tabs --}}
                    @include('firewall.nat.tabs', ['active' => 'port-forward'])

                    <div class="flex justify-between items-center mb-4 mt-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Port Forward Rules</h3>
                        <x-button-add @click="$dispatch('open-create-modal')">
                            Add Rule
                        </x-button-add>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col"
                                        class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                        style="width: 40px;">
                                        Status</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Interface</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Protocol</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Source Address</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Source Ports</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Dest. Address</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Dest. Ports</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        NAT IP</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        NAT Ports</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Description</th>
                                    <th scope="col"
                                        class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($rules as $index => $rule)
                                    <tr
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ !empty($rule['disabled']) ? 'opacity-50' : '' }}">
                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                            <form
                                                action="{{ route('firewall.nat.port-forward.toggle', ['firewall' => $firewall, 'id' => $index]) }}"
                                                method="POST" class="inline-block">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit" class="focus:outline-none" title="Toggle Status">
                                                    @if(!empty($rule['disabled']))
                                                        <svg class="w-5 h-5 text-red-500 mx-auto hover:text-red-700 transition"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    @else
                                                        <svg class="w-5 h-5 text-green-500 mx-auto hover:text-green-700 transition"
                                                            fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                        </svg>
                                                    @endif
                                                </button>
                                            </form>
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ strtoupper($rule['interface'] ?? '') }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ strtoupper($rule['protocol'] ?? '') }}
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = is_array($rule['source']) ? ($rule['source']['any'] ? '*' : ($rule['source']['address'] ?? ($rule['source']['network'] ?? ''))) : $rule['source'];
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                                                    {{ $val }}
                                                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide
                                                                                                                                                                                                                                                                                                                                                                                        {{ match ($aliasMap[$val]['type']) {
                                                    'host' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'network' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'port' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                    'url' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                                } }}">
                                                                                        {{ $aliasMap[$val]['type'] }}
                                                                                    </span>
                                                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = $rule['source_port'] ?? (is_array($rule['source']) && isset($rule['source']['port']) ? $rule['source']['port'] : '*');
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                    {{ $val }}
                                                    <span
                                                        class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                        {{ $aliasMap[$val]['type'] }}
                                                    </span>
                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = is_array($rule['destination']) ? ($rule['destination']['any'] ? '*' : ($rule['destination']['address'] ?? ($rule['destination']['network'] ?? ''))) : $rule['destination'];
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                                                    {{ $val }}
                                                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide
                                                                                                                                                                                                                                                                                                                                                                                        {{ match ($aliasMap[$val]['type']) {
                                                    'host' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'network' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'port' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                    'url' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                                } }}">
                                                                                        {{ $aliasMap[$val]['type'] }}
                                                                                    </span>
                                                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = $rule['destination_port'] ?? (is_array($rule['destination']) && isset($rule['destination']['port']) ? $rule['destination']['port'] : '*');
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                    {{ $val }}
                                                    <span
                                                        class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                        {{ $aliasMap[$val]['type'] }}
                                                    </span>
                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = $rule['target'] ?? '';
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                                                    {{ $val }}
                                                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide
                                                                                                                                                                                                                                                                                                                                                                                        {{ match ($aliasMap[$val]['type']) {
                                                    'host' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
                                                    'network' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
                                                    'port' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200',
                                                    'url' => 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
                                                    default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200'
                                                } }}">
                                                                                        {{ $aliasMap[$val]['type'] }}
                                                                                    </span>
                                                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = $rule['local_port'] ?? ($rule['local-port'] ?? '');
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                    class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                    {{ $val }}
                                                    <span
                                                        class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                        {{ $aliasMap[$val]['type'] }}
                                                    </span>
                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                            {{ $rule['descr'] ?? '' }}
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center items-center space-x-2">
                                                <button @click="editRule({{ json_encode($rule) }}, {{ $index }})"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                    title="Edit">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button @click="confirmDelete({{ $index }})"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300"
                                                    title="Delete">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No port forward rules found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{-- Redundant Apply Changes button removed --}}
                </div>
            </div>
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
                        :action="isEdit ? '{{ url('firewall/' . $firewall->getRouteKey() . '/nat/port-forward') }}/' + form.id : '{{ route('firewall.nat.port-forward.store', $firewall) }}'"
                        method="POST">
                        @csrf
                        <template x-if="isEdit">
                            @method('PUT')
                        </template>

                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                                x-text="isEdit ? 'Edit Port Forward Rule' : 'Add Port Forward Rule'"></h3>

                            <div class="mt-4 grid grid-cols-1 gap-y-6 gap-x-4 sm:grid-cols-6">
                                {{-- Disabled --}}
                                <div class="sm:col-span-6">
                                    <div class="flex items-start">
                                        <div class="flex items-center h-5">
                                            <input id="disabled" name="disabled" type="checkbox" x-model="form.disabled"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        </div>
                                        <div class="ml-3 text-sm">
                                            <label for="disabled"
                                                class="font-medium text-gray-700 dark:text-gray-300">Disable this
                                                rule</label>
                                        </div>
                                    </div>
                                </div>

                                {{-- Interface --}}
                                <div class="sm:col-span-3">
                                    <label for="interface"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Interface</label>
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
                                    </select>
                                </div>

                                {{-- Source Address --}}
                                <div class="sm:col-span-3">
                                    <label for="src"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source
                                        Address</label>
                                    <input type="text" name="src" id="src" x-model="form.src"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="any, ip, or alias">
                                </div>

                                {{-- Source Port --}}
                                <div class="sm:col-span-3">
                                    <label for="srcport"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Source
                                        Port</label>
                                    <input type="text" name="srcport" id="srcport" x-model="form.srcport"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="any or port">
                                </div>

                                {{-- Dest Address --}}
                                <div class="sm:col-span-3">
                                    <label for="dst"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dest.
                                        Address</label>
                                    <input type="text" name="dst" id="dst" x-model="form.dst"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="any, ip, or alias">
                                </div>

                                {{-- Destination Port --}}
                                <div class="sm:col-span-3">
                                    <label for="dstport"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Dest. Port
                                        Range</label>
                                    <input type="text" name="dstport" id="dstport" x-model="form.dstport"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="80 or 80-90">
                                </div>

                                {{-- Target IP --}}
                                <div class="sm:col-span-3">
                                    <label for="target"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Redirect
                                        Target IP</label>
                                    <input type="text" name="target" id="target" x-model="form.target"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="192.168.1.x">
                                </div>

                                {{-- Target Port --}}
                                <div class="sm:col-span-3">
                                    <label for="local_port"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Redirect
                                        Target Port</label>
                                    <input type="text" name="local_port" id="local_port" x-model="form.local_port"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="80">
                                </div>

                                {{-- Description --}}
                                <div class="sm:col-span-6">
                                    <label for="descr"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                    <input type="text" name="descr" id="descr" x-model="form.descr"
                                        class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                </div>
                            </div>

                            {{-- Filter Rule Association --}}
                            <div class="mt-6 sm:col-span-6">
                                <label for="associated_rule_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filter rule
                                    association</label>
                                <select id="associated_rule_id" name="associated_rule_id"
                                    x-model="form.associated_rule_id"
                                    class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                    <option value="pass">Add associated filter rule</option>
                                    <option value="block">Block</option>
                                    <option value="reject">Reject</option>
                                    <option value="none">None</option>
                                </select>
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
                            <div
                                class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
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
                            :action="'{{ url('firewall/' . $firewall->getRouteKey() . '/nat/port-forward') }}/' + deleteId"
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
    </div>
</x-app-layout>