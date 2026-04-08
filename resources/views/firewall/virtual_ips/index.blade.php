<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Virtual IPs') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <x-card x-data="vipHandler()" @open-create-modal.window="openModal()">
                <x-card-header title="Virtual IPs">
                    @if(!auth()->user()->isReadOnly())
                    <x-button-add @click="$dispatch('open-create-modal')">
                        Add Virtual IP
                    </x-button-add>
                    @endif
                </x-card-header>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Type</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Interface</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Address</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Description</th>
                                        @if(!auth()->user()->isReadOnly())
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($vips as $index => $vip)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                @if($vip['mode'] === 'ipalias') IP Alias
                                                @elseif($vip['mode'] === 'carp') CARP
                                                @elseif($vip['mode'] === 'proxyarp') Proxy ARP
                                                @else {{ $vip['mode'] }}
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $vip['interface'] ?? '' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $vip['subnet'] ?? '' }}/{{ $vip['subnet_bits'] ?? '' }}
                                                @if($vip['mode'] === 'carp' && !empty($vip['vhid']))
                                                    (vhid: {{ $vip['vhid'] }})
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $vip['descr'] ?? '' }}
                                            </td>
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button @click="editVip({{ $index }}, {{ json_encode($vip) }})"
                                                    class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                                <form
                                                    action="{{ route('firewall.virtual_ips.destroy', ['firewall' => $firewall->id, 'virtual_ip' => $index]) }}"
                                                    method="POST" class="inline-block"
                                                    onsubmit="return confirm('Are you sure you want to delete this Virtual IP?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ auth()->user()->isReadOnly() ? '4' : '5' }}"
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                                No Virtual IPs found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Modal -->
                        <div x-show="showModal" class="fixed z-10 inset-0 overflow-y-auto" style="display: none;">
                            <div
                                class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                                <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                                    <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                                </div>
                                <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                                    aria-hidden="true">&#8203;</span>
                                <div
                                    class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                    <form
                                        :action="isEdit ? '/firewall/{{ $firewall->id }}/virtual_ips/' + form.id : '{{ route('firewall.virtual_ips.store', $firewall) }}'"
                                        method="POST">
                                        @csrf
                                        <template x-if="isEdit">
                                            <input type="hidden" name="_method" value="PUT">
                                        </template>
                                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                            <div class="grid grid-cols-6 gap-6">
                                                <div class="col-span-6">
                                                    <label for="mode"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                                    <select name="mode" x-model="form.mode"
                                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        <option value="ipalias">IP Alias</option>
                                                        <option value="carp">CARP</option>
                                                        <option value="proxyarp">Proxy ARP</option>
                                                        <option value="other">Other</option>
                                                    </select>
                                                </div>

                                                <div class="col-span-6">
                                                    <label for="interface"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Interface</label>
                                                    <select name="interface" x-model="form.interface"
                                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        <option value="wan">WAN</option>
                                                        <option value="lan">LAN</option>
                                                        <option value="opt1">OPT1</option>
                                                        <option value="opt2">OPT2</option>
                                                    </select>
                                                </div>

                                                <div class="col-span-6 sm:col-span-4">
                                                    <label for="subnet"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Address
                                                        (IP)</label>
                                                    <input type="text" name="subnet" x-model="form.subnet"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                        placeholder="1.2.3.4" required>
                                                </div>

                                                <div class="col-span-6 sm:col-span-2">
                                                    <label for="subnet_bits"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bits</label>
                                                    <input type="number" name="subnet_bits" x-model="form.subnet_bits"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                        placeholder="32" required>
                                                </div>

                                                <div class="col-span-6" x-show="form.mode === 'carp'">
                                                    <label for="password"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">VHID
                                                        Password</label>
                                                    <input type="password" name="password" x-model="form.password"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                </div>

                                                <div class="col-span-6" x-show="form.mode === 'carp'">
                                                    <label for="vhid"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">VHID
                                                        Group</label>
                                                    <select name="vhid" x-model="form.vhid"
                                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        @for($i = 1; $i <= 255; $i++)
                                                            <option value="{{ $i }}">{{ $i }}</option>
                                                        @endfor
                                                    </select>
                                                </div>

                                                <div class="col-span-6">
                                                    <label for="descr"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                                    <input type="text" name="descr" x-model="form.descr"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                            <button type="submit"
                                                class="btn-primary w-full sm:w-auto sm:ml-3 sm:text-sm">
                                                Save
                                            </button>
                                            <button type="button" @click="showModal = false"
                                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                                Cancel
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
            </x-card>
        </div>
    </div>

    <script>
        function vipHandler() {
            return {
                showModal: false,
                isEdit: false,
                form: {
                    id: '',
                    mode: 'ipalias',
                    interface: 'wan',
                    subnet: '',
                    subnet_bits: '32',
                    password: '',
                    vhid: '1',
                    descr: ''
                },
                resetForm() {
                    this.form = {
                        id: '',
                        mode: 'ipalias',
                        interface: 'wan',
                        subnet: '',
                        subnet_bits: '32',
                        password: '',
                        vhid: '1',
                        descr: ''
                    };
                },
                openModal() {
                    this.resetForm();
                    this.isEdit = false;
                    this.showModal = true;
                },
                editVip(index, vip) {
                    this.resetForm();
                    this.isEdit = true;
                    this.form.id = index;
                    this.form.mode = vip.mode || 'ipalias';
                    this.form.interface = vip.interface || 'wan';
                    this.form.subnet = vip.subnet || '';
                    this.form.subnet_bits = vip.subnet_bits || '32';
                    this.form.password = vip.password || '';
                    this.form.vhid = vip.vhid || '1';
                    this.form.descr = vip.descr || '';

                    this.showModal = true;
                }
            }
        }
    </script>
</x-app-layout>
