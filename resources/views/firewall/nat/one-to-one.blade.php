<x-app-layout :firewall="$firewall">
    <x-slot name="header">
        <x-firewall-header title="{{ __('Firewall NAT: 1:1') }}" :firewall="$firewall">
            <x-slot name="actions">
                <x-button-add @click="$dispatch('open-create-modal')">
                    Add Mapping
                </x-button-add>
            </x-slot>
        </x-firewall-header>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @include('firewall.nat.tabs', ['active' => 'one-to-one'])

                    <div x-data="natOneToOneHandler()" @open-create-modal.window="openModal()"
                        class="bg-white dark:bg-gray-800 shadow rounded-lg p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">1:1 Mappings</h3>
                        </div>

                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col"
                                            class="px-6 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                            style="width: 40px;">
                                            Status</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Interface</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            External IP</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Internal IP</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Destination IP</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Description</th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($rules as $index => $rule)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                                @if(!empty($rule['disabled']))
                                                    <svg class="w-5 h-5 text-red-500 mx-auto" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @else
                                                    <svg class="w-5 h-5 text-green-500 mx-auto" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                            d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $rule['interface'] ?? '' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $rule['external'] ?? '' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ is_array($rule['source']) ? ($rule['source']['network'] ?? $rule['source']['address'] ?? 'any') : $rule['source'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ is_array($rule['destination']) ? ($rule['destination']['network'] ?? $rule['destination']['address'] ?? 'any') : $rule['destination'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $rule['descr'] ?? '' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <button @click="editRule({{ $index }}, {{ json_encode($rule) }})"
                                                    class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                                <form
                                                    action="{{ route('firewall.nat.one-to-one.destroy', ['firewall' => $firewall->id, 'id' => $index]) }}"
                                                    method="POST" class="inline-block"
                                                    onsubmit="return confirm('Are you sure you want to delete this rule?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="text-red-600 hover:text-red-900">Delete</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6"
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                                No 1:1
                                                NAT rules found.</td>
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
                                        :action="isEdit ? '/firewall/{{ $firewall->id }}/nat/one-to-one/' + form.id : '{{ route('firewall.nat.one-to-one.store', $firewall) }}'"
                                        method="POST">
                                        @csrf
                                        <template x-if="isEdit">
                                            <input type="hidden" name="_method" value="PUT">
                                        </template>
                                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                            <div class="grid grid-cols-6 gap-6">
                                                <div class="col-span-6 sm:col-span-3">
                                                    <label for="interface"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Interface</label>
                                                    <select name="interface" x-model="form.interface"
                                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        @foreach($interfaces as $iface)
                                                            <option value="{{ $iface['if'] }}">
                                                                {{ $iface['descr'] ?? $iface['if'] }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </div>

                                                <div class="col-span-6">
                                                    <label for="external"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">External
                                                        IP</label>
                                                    <input type="text" name="external" x-model="form.external"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                        placeholder="IP Address">
                                                </div>

                                                <div class="col-span-6">
                                                    <label for="src"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Internal
                                                        IP
                                                        (Source)</label>
                                                    <input type="text" name="src" x-model="form.src"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                        placeholder="IP Address">
                                                </div>

                                                <div class="col-span-6">
                                                    <label for="dst"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Destination
                                                        IP</label>
                                                    <input type="text" name="dst" x-model="form.dst"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                        placeholder="any or IP/CIDR">
                                                </div>

                                                <div class="col-span-6">
                                                    <label for="descr"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                                    <input type="text" name="descr" x-model="form.descr"
                                                        class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                                </div>

                                                <div class="col-span-6 sm:col-span-3">
                                                    <label for="natreflection"
                                                        class="block text-sm font-medium text-gray-700 dark:text-gray-300">NAT
                                                        Reflection</label>
                                                    <select name="natreflection" x-model="form.natreflection"
                                                        class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                        <option value="">System Default</option>
                                                        <option value="enable">Enable</option>
                                                        <option value="disable">Disable</option>
                                                    </select>
                                                </div>

                                                <div class="col-span-6">
                                                    <div class="flex items-start">
                                                        <div class="flex items-center h-5">
                                                            <input id="disabled" name="disabled" type="checkbox"
                                                                x-model="form.disabled"
                                                                class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded">
                                                        </div>
                                                        <div class="ml-3 text-sm">
                                                            <label for="disabled"
                                                                class="font-medium text-gray-700 dark:text-gray-300">Disabled</label>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div
                                            class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                            <button type="submit"
                                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
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
                    </div>

                    <script>
                        function natOneToOneHandler() {
                            return {
                                showModal: false,
                                isEdit: false,
                                form: {
                                    id: '',
                                    interface: '{{ $interfaces[0]['if'] ?? 'wan' }}',
                                    external: '',
                                    src: '',
                                    dst: 'any',
                                    descr: '',
                                    natreflection: '',
                                    disabled: false
                                },
                                resetForm() {
                                    this.form = {
                                        id: '',
                                        interface: '{{ $interfaces[0]['if'] ?? 'wan' }}',
                                        external: '',
                                        src: '',
                                        dst: 'any',
                                        descr: '',
                                        natreflection: '',
                                        disabled: false
                                    };
                                },
                                openModal() {
                                    this.resetForm();
                                    this.isEdit = false;
                                    this.showModal = true;
                                },
                                editRule(index, rule) {
                                    this.resetForm();
                                    this.isEdit = true;
                                    this.form.id = index;
                                    this.form.interface = rule.interface || 'wan';
                                    this.form.external = rule.external || '';

                                    // Handle Source
                                    if (typeof rule.source === 'object') {
                                        this.form.src = rule.source.network || rule.source.address || 'any';
                                    } else {
                                        this.form.src = rule.source || 'any';
                                    }

                                    // Handle Destination
                                    if (typeof rule.destination === 'object') {
                                        this.form.dst = rule.destination.network || rule.destination.address || 'any';
                                    } else {
                                        this.form.dst = rule.destination || 'any';
                                    }

                                    this.form.descr = rule.descr || '';
                                    this.form.natreflection = rule.natreflection || '';
                                    this.form.disabled = !!rule.disabled;

                                    this.showModal = true;
                                }
                            }
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>