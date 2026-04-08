<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Traffic Shaper Limiters') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <x-card x-data="limiterHandler()" @open-create-modal.window="openModal()">
                <x-card-header title="Limiters">
                    @if(!auth()->user()->isReadOnly())
                    <x-button-add @click="$dispatch('open-create-modal')">
                        Add Limiter
                    </x-button-add>
                    @endif
                </x-card-header>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                        <thead class="bg-gray-50 dark:bg-gray-700">
                            <tr>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Name</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Description</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Bandwidth</th>
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Mask</th>
                                @if(!auth()->user()->isReadOnly())
                                <th scope="col"
                                    class="px-6 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                    Actions</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                            @forelse($limiters as $index => $limiter)
                                <tr class="hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $limiter['name'] ?? '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $limiter['descr'] ?? '' }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        @if(isset($limiter['bandwidth']) && is_array($limiter['bandwidth']))
                                            @foreach($limiter['bandwidth'] as $bw)
                                                {{ $bw['bw'] ?? '' }} {{ $bw['bwscale'] ?? '' }}<br>
                                            @endforeach
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                        {{ $limiter['mask'] ?? 'none' }}
                                        @if(!empty($limiter['maskbits']))
                                            /{{ $limiter['maskbits'] }}
                                        @endif
                                    </td>
                                    @if(!auth()->user()->isReadOnly())
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <button @click="editLimiter({{ $index }}, {{ json_encode($limiter) }})"
                                            class="text-blue-600 hover:text-blue-900 mr-3">Edit</button>
                                        <form
                                            action="{{ route('firewall.limiters.destroy', ['firewall' => $firewall->id, 'limiter' => $index]) }}"
                                            method="POST" class="inline-block"
                                            onsubmit="return confirm('Are you sure you want to delete this limiter?');">
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
                                        No limiters found.</td>
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
                                :action="isEdit ? '/firewall/{{ $firewall->id }}/limiters/' + form.id : '{{ route('firewall.limiters.store', $firewall) }}'"
                                method="POST">
                                @csrf
                                <template x-if="isEdit">
                                    <input type="hidden" name="_method" value="PUT">
                                </template>
                                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                    <div class="grid grid-cols-6 gap-6">
                                        <div class="col-span-6">
                                            <label for="name"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                            <input type="text" name="name" x-model="form.name"
                                                class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                required>
                                        </div>

                                        <div class="col-span-6">
                                            <label for="descr"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                            <input type="text" name="descr" x-model="form.descr"
                                                class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white">
                                        </div>

                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="bandwidth_value"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Bandwidth</label>
                                            <div class="mt-1 flex rounded-md shadow-sm">
                                                <input type="number" name="bandwidth_value"
                                                    x-model="form.bandwidth_value"
                                                    class="focus:ring-blue-500 focus:border-blue-500 flex-1 block w-full rounded-none rounded-l-lg sm:text-sm border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                    required>
                                                <select name="bandwidth_scale" x-model="form.bandwidth_scale"
                                                    class="focus:ring-blue-500 focus:border-blue-500 inline-flex items-center px-3 rounded-r-lg border border-l-0 border-gray-300 bg-gray-50 dark:bg-gray-600 dark:border-gray-600 dark:text-white text-gray-500 sm:text-sm">
                                                    <option value="b">bit/s</option>
                                                    <option value="Kb">Kbit/s</option>
                                                    <option value="Mb">Mbit/s</option>
                                                    <option value="Gb">Gbit/s</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="mask"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mask</label>
                                            <select name="mask" x-model="form.mask"
                                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                <option value="none">None</option>
                                                <option value="srcaddress">Source Address</option>
                                                <option value="dstaddress">Destination Address</option>
                                            </select>
                                        </div>

                                        <div class="col-span-6 sm:col-span-3" x-show="form.mask !== 'none'">
                                            <label for="maskbits"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Mask
                                                Bits</label>
                                            <input type="number" name="maskbits" x-model="form.maskbits"
                                                class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-lg dark:bg-gray-700 dark:border-gray-600 dark:text-white"
                                                placeholder="32">
                                        </div>

                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="aqm"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">AQM</label>
                                            <select name="aqm" x-model="form.aqm"
                                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                <option value="droptail">Drop Tail</option>
                                                <option value="codel">CoDel</option>
                                                <option value="pie">PIE</option>
                                            </select>
                                        </div>

                                        <div class="col-span-6 sm:col-span-3">
                                            <label for="sched"
                                                class="block text-sm font-medium text-gray-700 dark:text-gray-300">Scheduler</label>
                                            <select name="sched" x-model="form.sched"
                                                class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white dark:bg-gray-700 dark:border-gray-600 dark:text-white rounded-lg shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                                                <option value="fifo">FIFO</option>
                                                <option value="qfq">QFQ</option>
                                                <option value="wfq">WFQ</option>
                                            </select>
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
        function limiterHandler() {
            return {
                showModal: false,
                isEdit: false,
                form: {
                    id: '',
                    name: '',
                    descr: '',
                    bandwidth_value: '',
                    bandwidth_scale: 'Mb',
                    mask: 'none',
                    maskbits: '',
                    aqm: 'droptail',
                    sched: 'fifo'
                },
                resetForm() {
                    this.form = {
                        id: '',
                        name: '',
                        descr: '',
                        bandwidth_value: '',
                        bandwidth_scale: 'Mb',
                        mask: 'none',
                        maskbits: '',
                        aqm: 'droptail',
                        sched: 'fifo'
                    };
                },
                openModal() {
                    this.resetForm();
                    this.isEdit = false;
                    this.showModal = true;
                },
                editLimiter(index, limiter) {
                    this.resetForm();
                    this.isEdit = true;
                    this.form.id = index;
                    this.form.name = limiter.name || '';
                    this.form.descr = limiter.descr || '';

                    if (limiter.bandwidth && Array.isArray(limiter.bandwidth) && limiter.bandwidth.length > 0) {
                        this.form.bandwidth_value = limiter.bandwidth[0].bw || '';
                        this.form.bandwidth_scale = limiter.bandwidth[0].bwscale || 'Mb';
                    } else if (limiter.bandwidth && limiter.bandwidth.item) {
                        this.form.bandwidth_value = limiter.bandwidth.item.bw || '';
                        this.form.bandwidth_scale = limiter.bandwidth.item.bwscale || 'Mb';
                    }

                    this.form.mask = limiter.mask || 'none';
                    this.form.maskbits = limiter.maskbits || '';
                    this.form.aqm = limiter.aqm || 'droptail';
                    this.form.sched = limiter.sched || 'fifo';

                    this.showModal = true;
                }
            }
        }
    </script>
</x-app-layout>
