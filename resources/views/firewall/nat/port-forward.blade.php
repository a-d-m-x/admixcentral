<x-app-layout :firewall="$firewall">
    <x-slot name="header">
        <x-firewall-header title="{{ __('Firewall NAT: Port Forward') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{
        showModal: false,
        showDeleteModal: false,
        deleteId: null,
        isEdit: false,
        selected: [],
        allSelected: false,
        form: {
            id: '',
            interface: '{{ $interfaces[0]['if'] ?? 'wan' }}',
            ipprotocol: 'inet',
            protocol: 'tcp',
            src_type: 'any',
            src: '',
            src_not: false,
            srcport: '',
            dst_type: 'wan:ip',
            dst: '',
            dst_not: false,
            dstport: '',
            target: '',
            local_port: '',
            descr: '',
            disabled: false,
            natreflection: 'system-default',
            associated_rule_id: 'new'
        },
        resetForm() {
            this.form = {
                id: '',
                interface: '{{ $interfaces[0]['if'] ?? 'wan' }}',
                ipprotocol: 'inet',
                protocol: 'tcp',
                src_type: 'any',
                src: '',
                src_not: false,
                srcport: '',
                dst_type: 'wan:ip',
                dst: '',
                dst_not: false,
                dstport: '',
                target: '',
                local_port: '',
                descr: '',
                disabled: false,
                natreflection: 'system-default',
                associated_rule_id: 'new'
            };
            this.isEdit = false;
        },
        editRule(rule, index) {
            this.isEdit = true;
            this.form.id = index;
            this.form.interface = rule.interface || 'wan';
            this.form.ipprotocol = rule.ipprotocol || 'inet';
            this.form.protocol = rule.protocol || 'tcp';

            // Parse source into type + address
            const parseEndpoint = (val) => {
                if (!val || val === 'any') return { type: 'any', address: '', invert: false };
                let invert = false;
                let addr = String(val);
                if (addr.startsWith('!')) { invert = true; addr = addr.slice(1); }
                const knownTypes = ['wan:ip','lan:ip','opt1:ip','opt2:ip','wan','lan','opt1','opt2'];
                if (knownTypes.includes(addr)) return { type: addr, address: '', invert };
                return { type: addr.includes('/') ? 'network' : 'address', address: addr, invert };
            };

            const srcRaw = rule.source || 'any';
            const srcStr = typeof srcRaw === 'string' ? srcRaw : (srcRaw.address || srcRaw.network || 'any');
            const srcParsed = parseEndpoint(srcStr);
            this.form.src_type = srcParsed.type;
            this.form.src = srcParsed.address;
            this.form.src_not = srcParsed.invert;
            this.form.srcport = rule.source_port || '';

            const dstRaw = rule.destination || 'any';
            const dstStr = typeof dstRaw === 'string' ? dstRaw : (dstRaw.address || dstRaw.network || 'any');
            const dstParsed = parseEndpoint(dstStr);
            this.form.dst_type = dstParsed.type;
            this.form.dst = dstParsed.address;
            this.form.dst_not = dstParsed.invert;
            this.form.dstport = rule.destination_port || '';

            this.form.target = rule.target || '';
            this.form.local_port = rule.local_port || rule['local-port'] || '';
            this.form.descr = rule.descr || '';
            this.form.disabled = !!rule.disabled;
            this.form.natreflection = rule.natreflection || 'system-default';
            this.form.associated_rule_id = rule['associated-rule-id'] || rule['associated_rule_id'] || 'none';

            this.showModal = true;
        },
        confirmDelete(index) {
            this.deleteId = index;
            this.showDeleteModal = true;
        },
        toggleAll() {
            if (this.allSelected) {
                this.selected = [];
                this.allSelected = false;
            } else {
                this.selected = @json(array_keys($rules));
                this.allSelected = true;
            }
        },
        submitBulk(action) {
            if (this.selected.length === 0) return;
            const form = document.getElementById('nat-bulk-form');
            form.querySelectorAll('input[name=\'action\'], input[name=\'ids[]\']').forEach(el => el.remove());
            const aInp = document.createElement('input');
            aInp.type = 'hidden'; aInp.name = 'action'; aInp.value = action;
            form.appendChild(aInp);
            this.selected.forEach(id => {
                const iInp = document.createElement('input');
                iInp.type = 'hidden'; iInp.name = 'ids[]'; iInp.value = id;
                form.appendChild(iInp);
            });
            form.submit();
        }
    }" @open-create-modal.window="resetForm(); showModal = true">

        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @include('firewall.nat.tabs', ['active' => 'port-forward'])

                    <x-apply-changes-banner :firewall="$firewall" />

                    @if(session('success'))
                        <div class="mt-4 mb-4 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="mt-4 mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if($errors->any())
                        <div class="mt-4 mb-4 px-4 py-3 bg-red-100 border border-red-400 text-redred-700 rounded">
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    {{-- Bulk Actions Form (hidden inputs injected via JS) --}}
                    <form id="nat-bulk-form" method="POST"
                          action="{{ route('firewall.nat.port-forward.bulk-action', $firewall) }}">
                        @csrf
                    </form>

                    {{-- Toolbar: bulk buttons + Add Rule --}}
                    <div class="flex justify-between items-center mb-4 mt-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Port Forward Rules</h3>
                        @if(!auth()->user()->isReadOnly())
                        <x-button-add @click="$dispatch('open-create-modal')">
                            Add Rule
                        </x-button-add>
                        @endif
                    </div>

                    {{-- Rules Table --}}
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
                                    @if(!auth()->user()->isReadOnly())
                                    <th scope="col"
                                        class="px-3 py-2 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($rules as $index => $rule)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ !empty($rule['disabled']) ? 'opacity-50' : '' }}">
                                        {{-- Checkbox --}}
                                        <td class="px-2 py-2 whitespace-nowrap">
                                            <input type="checkbox" value="{{ $index }}" x-model="selected"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        </td>
                                        {{-- Toggle Status --}}
                                        <td class="px-3 py-2 whitespace-nowrap text-center">
                                            @if(!auth()->user()->isReadOnly())
                                            <form
                                                action="{{ route('firewall.nat.port-forward.toggle', ['firewall' => $firewall, 'id' => $index]) }}"
                                                method="POST" class="inline-block">
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
                                            @else
                                                {{-- Show static status icon for readonly --}}
                                                @if(!empty($rule['disabled']))
                                                    <svg class="w-5 h-5 text-red-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @else
                                                    <svg class="w-5 h-5 text-green-500 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @endif
                                            @endif
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ strtoupper($rule['interface'] ?? '') }}</td>
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">{{ strtoupper($rule['protocol'] ?? '') }}</td>

                                        {{-- Source Address --}}
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = is_array($rule['source']) ? ($rule['source']['any'] ? '*' : ($rule['source']['address'] ?? ($rule['source']['network'] ?? ''))) : $rule['source'];
                                                $val = ($val === 'any' || $val === '') ? '*' : $val;
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                   class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                    {{ $val }}
                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide {{ match ($aliasMap[$val]['type']) { 'host' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', 'network' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', 'port' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200', default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' } }}">{{ $aliasMap[$val]['type'] }}</span>
                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>

                                        {{-- Source Ports --}}
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @php $val = $rule['source_port'] ?? (is_array($rule['source']) && isset($rule['source']['port']) ? $rule['source']['port'] : '*'); $val = $val ?: '*'; @endphp
                                            {{ $val }}
                                        </td>

                                        {{-- Dest Address --}}
                                        <td class="px-3 py-2 text-sm text-gray-900 dark:text-gray-100">
                                            @php
                                                $val = is_array($rule['destination']) ? ($rule['destination']['any'] ? '*' : ($rule['destination']['address'] ?? ($rule['destination']['network'] ?? ''))) : $rule['destination'];
                                                $val = ($val === 'any' || $val === '') ? '*' : $val;
                                                $isAlias = isset($aliasMap[$val]);
                                            @endphp
                                            @if($isAlias)
                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                   class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                    {{ $val }}
                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide {{ match ($aliasMap[$val]['type']) { 'host' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', 'network' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', 'port' => 'bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200', default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' } }}">{{ $aliasMap[$val]['type'] }}</span>
                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>

                                        {{-- Dest Ports --}}
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @php $val = $rule['destination_port'] ?? (is_array($rule['destination']) && isset($rule['destination']['port']) ? $rule['destination']['port'] : '*'); $val = $val ?: '*'; @endphp
                                            {{ $val }}
                                        </td>

                                        {{-- NAT IP (target) --}}
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            @php $val = $rule['target'] ?? ''; $isAlias = isset($aliasMap[$val]); @endphp
                                            @if($isAlias)
                                                <a href="{{ route('firewall.aliases.edit', [$firewall, $aliasMap[$val]['id']]) }}"
                                                   class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 font-medium inline-flex items-center">
                                                    {{ $val }}
                                                    <span class="ml-1 px-1.5 py-0.5 rounded text-[10px] font-bold uppercase tracking-wide {{ match ($aliasMap[$val]['type']) { 'host' => 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200', 'network' => 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200', default => 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-200' } }}">{{ $aliasMap[$val]['type'] }}</span>
                                                </a>
                                            @else
                                                {{ $val }}
                                            @endif
                                        </td>

                                        {{-- NAT Ports (local-port) --}}
                                        <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ $rule['local_port'] ?? ($rule['local-port'] ?? '*') }}
                                        </td>
                                        @if(!auth()->user()->isReadOnly())
                                        <td class="px-3 py-2 whitespace-nowrap text-center text-sm font-medium">
                                            <div class="flex justify-center items-center space-x-2">
                                                <button @click="editRule({{ json_encode($rule) }}, {{ $index }})"
                                                    class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300" title="Edit">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                    </svg>
                                                </button>
                                                <button @click="confirmDelete({{ $index }})"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300" title="Delete">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="12" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No port forward rules found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════ ADD / EDIT MODAL ═══════════════════════════════════ --}}
        <div x-show="showModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            {{-- Backdrop --}}
            <div x-show="showModal" class="absolute inset-0 bg-gray-900/80 backdrop-blur-sm"
                 x-transition:enter="ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                 @click="showModal = false"></div>

            {{-- Dialog --}}
            <div x-show="showModal"
                 x-transition:enter="ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="relative z-10 w-full max-w-3xl bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden">

                    <form method="POST"
                          :action="isEdit
                              ? '{{ route('firewall.nat.port-forward.update', ['firewall' => $firewall, 'id' => 'REPLACE_ME']) }}'.replace('REPLACE_ME', form.id)
                              : '{{ route('firewall.nat.port-forward.store', $firewall) }}'">
                        @csrf
                        <template x-if="isEdit">
                            <input type="hidden" name="_method" value="PUT">
                        </template>

                        {{-- Modal Header --}}
                        <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gradient-to-r from-indigo-600 to-blue-600">
                            <h3 class="text-lg font-semibold text-white" x-text="isEdit ? 'Edit Port Forward Rule' : 'Add Port Forward Rule'"></h3>
                            <button type="button" @click="showModal = false" class="text-white/70 hover:text-white transition">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>

                        <div class="px-6 py-5 space-y-6 max-h-[75vh] overflow-y-auto">

                            {{-- Disabled checkbox --}}
                            <div class="flex items-center space-x-3">
                                <input id="nat-disabled" name="disabled" type="checkbox" x-model="form.disabled"
                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                <label for="nat-disabled" class="text-sm font-medium text-gray-700 dark:text-gray-300">Disable this rule</label>
                            </div>

                            {{-- ── Basic Settings: Interface / Address Family / Protocol ── --}}
                            <div>
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider mb-3">Basic Settings</p>
                                <div class="grid grid-cols-3 gap-4">
                                    {{-- Interface --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Interface</label>
                                        <select name="interface" x-model="form.interface"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                            @foreach($interfaces as $ifName => $iface)
                                                @php $ifVal = $iface['name'] ?? (is_string($ifName) ? $ifName : ($iface['id'] ?? $iface['if'])); @endphp
                                                <option value="{{ $ifVal }}">{{ $iface['descr'] ?? strtoupper($ifVal) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    {{-- Address Family --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Address Family</label>
                                        <select name="ipprotocol" x-model="form.ipprotocol"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                            <option value="inet">IPv4</option>
                                            <option value="inet6">IPv6</option>
                                        </select>
                                    </div>
                                    {{-- Protocol --}}
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Protocol</label>
                                        <select name="protocol" x-model="form.protocol"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                            <option value="tcp">TCP</option>
                                            <option value="udp">UDP</option>
                                            <option value="tcp/udp">TCP/UDP</option>
                                            <option value="icmp">ICMP</option>
                                            <option value="esp">ESP</option>
                                            <option value="ah">AH</option>
                                            <option value="gre">GRE</option>
                                            <option value="ipv6">IPv6</option>
                                            <option value="igmp">IGMP</option>
                                            <option value="ospf">OSPF</option>
                                            <option value="sctp">SCTP</option>
                                            <option value="any">Any</option>
                                        </select>
                                    </div>
                                </div>
                            </div>

                            {{-- ── Source ── --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 space-y-3">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Source</h4>

                                {{-- Invert + Type + Address on one row --}}
                                <div class="flex items-end gap-3">
                                    <div class="flex items-center pb-1 shrink-0">
                                        <input type="checkbox" name="src_not" id="nat-src-not" x-model="form.src_not"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <label for="nat-src-not" class="ml-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">Invert</label>
                                    </div>
                                    <div class="w-44 shrink-0">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Type</label>
                                        <select name="src_type" x-model="form.src_type"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                            <option value="any">Any</option>
                                            <option value="address">Host / Alias</option>
                                            <option value="network">Network</option>
                                            <option value="wan:ip">WAN address</option>
                                            <option value="lan:ip">LAN address</option>
                                            <option value="wan">WAN net</option>
                                            <option value="lan">LAN net</option>
                                        </select>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Source Address / Alias</label>
                                        <input type="text" name="src" x-model="form.src"
                                            :readonly="!['address','network'].includes(form.src_type)"
                                            :class="!['address','network'].includes(form.src_type) ? 'opacity-40 bg-gray-50 dark:bg-gray-600 cursor-not-allowed' : ''"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                            placeholder="IP, CIDR, or alias">
                                    </div>
                                </div>

                                {{-- Source Port Range (TCP/UDP/SCTP only) --}}
                                <div x-show="['tcp','udp','tcp/udp','sctp'].includes(form.protocol)" style="display:none;">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Source Port Range</label>
                                    <div class="flex gap-2 items-center">
                                        <input type="text" name="srcport" placeholder="From (any or port)" x-model="form.srcport"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            {{-- ── Destination ── --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 space-y-3">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Destination</h4>

                                {{-- Invert + Type + Address on one row --}}
                                <div class="flex items-end gap-3">
                                    <div class="flex items-center pb-1 shrink-0">
                                        <input type="checkbox" name="dst_not" id="nat-dst-not" x-model="form.dst_not"
                                            class="rounded border-gray-300 text-blue-600 shadow-sm focus:ring-blue-500">
                                        <label for="nat-dst-not" class="ml-2 text-sm text-gray-700 dark:text-gray-300 whitespace-nowrap">Invert</label>
                                    </div>
                                    <div class="w-44 shrink-0">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Type</label>
                                        <select name="dst_type" x-model="form.dst_type"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                            <option value="any">Any</option>
                                            <option value="address">Host / Alias</option>
                                            <option value="network">Network</option>
                                            <option value="wan:ip">WAN address</option>
                                            <option value="lan:ip">LAN address</option>
                                            <option value="wan">WAN net</option>
                                            <option value="lan">LAN net</option>
                                        </select>
                                    </div>
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Destination Address / Alias</label>
                                        <input type="text" name="dst" x-model="form.dst"
                                            :readonly="!['address','network'].includes(form.dst_type)"
                                            :class="!['address','network'].includes(form.dst_type) ? 'opacity-40 bg-gray-50 dark:bg-gray-600 cursor-not-allowed' : ''"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                            placeholder="IP, CIDR, or alias">
                                    </div>
                                </div>

                                {{-- Destination Port Range (TCP/UDP/SCTP only) --}}
                                <div x-show="['tcp','udp','tcp/udp','sctp'].includes(form.protocol)" style="display:none;">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Destination Port Range</label>
                                    <div class="flex gap-2 items-center">
                                        <input type="text" name="dstport" placeholder="From (e.g. 80 or 80:90)" x-model="form.dstport"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                    </div>
                                </div>
                            </div>

                            {{-- ── Redirect Target ── --}}
                            <div class="rounded-lg border border-gray-200 dark:border-gray-600 p-4 space-y-3">
                                <h4 class="text-sm font-semibold text-gray-700 dark:text-gray-300 uppercase tracking-wide">Redirect Target</h4>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Redirect target IP</label>
                                        <input type="text" name="target" x-model="form.target"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                            placeholder="e.g. 192.168.1.12">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Redirect target port</label>
                                        <input type="text" name="local_port" x-model="form.local_port"
                                            class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                            placeholder="e.g. 80">
                                    </div>
                                </div>
                            </div>

                            {{-- ── Extra Options ── --}}
                            <div class="grid grid-cols-2 gap-4">
                                {{-- Description --}}
                                <div class="col-span-2">
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Description</label>
                                    <input type="text" name="descr" x-model="form.descr"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm"
                                        placeholder="Optional description">
                                </div>

                                {{-- NAT reflection (matching pfSense wording exactly) --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">NAT reflection</label>
                                    <select name="natreflection" x-model="form.natreflection"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                        <option value="system-default">Use system default</option>
                                        <option value="enable">Enable (NAT + Proxy)</option>
                                        <option value="purenat">Enable (Pure NAT)</option>
                                        <option value="disable">Disable</option>
                                    </select>
                                </div>

                                {{-- Filter Rule Association --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Filter rule association</label>
                                    <select name="associated_rule_id" x-model="form.associated_rule_id"
                                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300 sm:text-sm">
                                        <template x-if="form.associated_rule_id && !['new','pass','none',''].includes(form.associated_rule_id)">
                                            <option :value="form.associated_rule_id" x-text="'Linked Rule (' + form.associated_rule_id + ')'"></option>
                                        </template>
                                        <option value="new">Add associated filter rule</option>
                                        <option value="pass">Pass</option>
                                        <option value="">None</option>
                                    </select>
                                </div>
                            </div>

                        </div>

                        {{-- Modal Footer --}}
                        <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 flex justify-end space-x-3 border-t border-gray-200 dark:border-gray-700">
                            <button type="button" @click="showModal = false"
                                class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                Cancel
                            </button>
                            <button type="submit"
                                class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 border border-transparent rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition">
                                Save Rule
                            </button>
                        </div>
                    </form>

                </div>
            </div>


        {{-- ═══════════════════════════════════ DELETE CONFIRM MODAL ═══════════════════════════════════ --}}
        <div x-show="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
            {{-- Backdrop --}}
            <div x-show="showDeleteModal" class="absolute inset-0 bg-gray-900/80 backdrop-blur-sm"
                 @click="showDeleteModal = false"></div>

            {{-- Dialog --}}
            <div x-show="showDeleteModal" class="relative z-10 w-full max-w-lg bg-white dark:bg-gray-800 rounded-xl shadow-2xl overflow-hidden">
                <div class="px-6 py-5">
                    <div class="flex items-start gap-4">
                        <div class="flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-red-100">
                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Delete Rule</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Are you sure you want to delete this rule? This action cannot be undone.</p>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-6 py-4 flex justify-end gap-3 border-t border-gray-200 dark:border-gray-600">
                    <button type="button" @click="showDeleteModal = false"
                        class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                        Cancel
                    </button>
                    <form method="POST"
                          :action="'{{ route('firewall.nat.port-forward.destroy', ['firewall' => $firewall, 'id' => 'REPLACE_ME']) }}'.replace('REPLACE_ME', deleteId)">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                            class="px-4 py-2 text-sm font-medium text-white bg-red-600 border border-transparent rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>