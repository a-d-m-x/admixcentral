<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Firewall Rules') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100" x-data="{ 
                    selected: [], 
                    allSelected: false,
                    confirmModal: {
                        open: false,
                        title: '',
                        message: '',
                        onConfirm: null
                    },
                    toggleAll() {
                        this.allSelected = !this.allSelected;
                        if (this.allSelected) {
                            this.selected = {{ $filteredRules->pluck('tracker')->values()->toJson() }};
                        } else {
                            this.selected = [];
                        }
                    },
                    openConfirmModal(title, message, callback) {
                        this.confirmModal.title = title;
                        this.confirmModal.message = message;
                        this.confirmModal.onConfirm = callback;
                        this.confirmModal.open = true;
                    },
                    closeConfirmModal() {
                        this.confirmModal.open = false;
                        this.confirmModal.onConfirm = null;
                    },
                    confirmAction() {
                        if (this.confirmModal.onConfirm) {
                            this.confirmModal.onConfirm();
                        }
                        this.closeConfirmModal();
                    },
                    submitAction(action) {
                        if (this.selected.length === 0) return;
                        
                        if (action === 'delete') {
                            this.openConfirmModal('Delete Rules', 'Are you sure you want to delete selected rules?', () => {
                                this.submitBulkForm(action);
                            });
                        } else {
                            this.submitBulkForm(action);
                        }
                    },
                    submitBulkForm(action) {
                        const form = document.getElementById('bulk-actions-form');
                        
                        // Clear previous hidden inputs for action and trackers
                        form.querySelectorAll('input[name=\'action\'], input[name=\'trackers[]\']').forEach(input => input.remove());

                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'action';
                        input.value = action;
                        form.appendChild(input);
                        
                        // Add selected trackers as hidden inputs
                        this.selected.forEach(tracker => {
                            const tInput = document.createElement('input');
                            tInput.type = 'hidden';
                            tInput.name = 'trackers[]';
                            tInput.value = tracker;
                            form.appendChild(tInput);
                        });
                        
                        form.submit();
                    }
                }">


                    <x-apply-changes-banner :firewall="$firewall" />

                    {{-- Interface Tabs --}}
                    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                        <nav class="-mb-px flex space-x-8 overflow-x-auto" aria-label="Tabs">
                            @foreach($interfaces as $iface)
                                <a href="{{ route('firewall.rules.index', ['firewall' => $firewall, 'interface' => $iface['id'] ?? $iface['if']]) }}"
                                    class="{{ $selectedInterface === ($iface['id'] ?? $iface['if']) ? 'border-blue-500 text-blue-600 dark:text-blue-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    {{ strtoupper($iface['descr'] ?? $iface['id']) }}
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    {{-- Bulk Actions Form --}}
                    <form id="bulk-actions-form" method="POST"
                        action="{{ route('firewall.rules.bulk-action', $firewall) }}">
                        @csrf
                        <input type="hidden" name="interface" value="{{ $selectedInterface }}">

                        <div class="flex justify-between mb-4">
                            <div class="flex space-x-2">
                                {{-- Bulk Actions --}}
                                <div class="flex space-x-2">
                                    <button type="button" @click="submitAction('enable')"
                                        :disabled="selected.length === 0"
                                        :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                        class="btn-primary">
                                        Enable
                                    </button>
                                    <button type="button" @click="submitAction('disable')"
                                        :disabled="selected.length === 0"
                                        :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                        class="inline-flex items-center px-3 py-2 bg-yellow-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-yellow-500">
                                        Disable
                                    </button>
                                    <button type="button" @click="submitAction('delete')"
                                        :disabled="selected.length === 0"
                                        :class="selected.length === 0 ? 'opacity-50 cursor-not-allowed' : ''"
                                        class="inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-500">
                                        Delete
                                    </button>
                                </div>
                            </div>
                            <div>
                                <x-link-button-add
                                    href="{{ route('firewall.rules.create', $firewall) }}?interface={{ $selectedInterface ?? 'wan' }}">
                                    Add Rule
                                </x-link-button-add>
                            </div>
                        </div>

                        {{-- Rules Table --}}
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col"
                                            class="px-2 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                            style="width: 40px;">
                                            <input type="checkbox" @click="toggleAll()" x-model="allSelected"
                                                class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                        </th>
                                        <th scope="col"
                                            class="px-2 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                            style="width: 60px;">
                                        </th>
                                        <th scope="col"
                                            class="px-2 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                            style="width: 40px;">
                                            Status
                                        </th>
                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Action</th>
                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Proto</th>
                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Source</th>
                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Port</th>
                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Destination</th>
                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Port</th>
                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Gateway</th>

                                        <th scope="col"
                                            class="px-3 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Description</th>
                                        <th scope="col"
                                            class="px-3 py-3 text-center text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider"
                                            style="width: 120px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($filteredRules as $index => $rule)
                                        <tr
                                            class="hover:bg-gray-50 dark:hover:bg-gray-700 transition {{ !empty($rule['disabled']) ? 'opacity-50' : '' }}">
                                            {{-- Bulk Checkbox --}}
                                            <td class="px-2 py-2 whitespace-nowrap text-center">
                                                <input type="checkbox" value="{{ $rule['tracker'] }}" x-model="selected"
                                                    class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                            </td>

                                            {{-- Order Arrows --}}
                                            <td class="px-2 py-2 whitespace-nowrap text-center">
                                                <div class="flex flex-col space-y-1 items-center justify-center">
                                                    @if($index > 0)
                                                        <form method="POST"
                                                            action="{{ route('firewall.rules.move', ['firewall' => $firewall, 'tracker' => $rule['tracker']]) }}">
                                                            @csrf
                                                            <input type="hidden" name="direction" value="up">
                                                            <input type="hidden" name="interface"
                                                                value="{{ $selectedInterface }}">
                                                            <button type="submit" title="Move Up"
                                                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M5 15l7-7 7 7" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                    @if($index < count($filteredRules) - 1)
                                                        <form method="POST"
                                                            action="{{ route('firewall.rules.move', ['firewall' => $firewall, 'tracker' => $rule['tracker']]) }}">
                                                            @csrf
                                                            <input type="hidden" name="direction" value="down">
                                                            <input type="hidden" name="interface"
                                                                value="{{ $selectedInterface }}">
                                                            <button type="submit" title="Move Down"
                                                                class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                                                                <svg class="w-4 h-4" fill="none" stroke="currentColor"
                                                                    viewBox="0 0 24 24">
                                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                                        stroke-width="2" d="M19 9l-7 7-7-7" />
                                                                </svg>
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>

                                            <td class="px-2 py-2 whitespace-nowrap text-center">
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

                                            <td class="px-3 py-2 whitespace-nowrap text-sm">
                                                @if($rule['type'] === 'pass')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        Pass
                                                    </span>
                                                @elseif($rule['type'] === 'block')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                        <svg class="w-3 h-3 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd"
                                                                d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z"
                                                                clip-rule="evenodd" />
                                                        </svg>
                                                        Block
                                                    </span>
                                                @elseif($rule['type'] === 'reject')
                                                    <span
                                                        class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                        ⛔ Reject
                                                    </span>
                                                @else
                                                    <span class="text-gray-500 dark:text-gray-400">{{ $rule['type'] }}</span>
                                                @endif
                                                @if(!empty($rule['disabled']))
                                                    <span class="ml-1 text-xs text-gray-400">(Disabled)</span>
                                                @endif
                                            </td>
                                            <td
                                                class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ strtoupper($rule['protocol'] ?? 'Any') }}
                                            </td>
                                            <td
                                                class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ is_array($rule['source']) ? ($rule['source']['address'] ?? ($rule['source']['network'] ?? 'Any')) : $rule['source'] }}
                                            </td>
                                            <td
                                                class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $rule['source']['port'] ?? '*' }}
                                            </td>
                                            <td
                                                class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ is_array($rule['destination']) ? ($rule['destination']['address'] ?? ($rule['destination']['network'] ?? 'Any')) : $rule['destination'] }}
                                            </td>
                                            <td
                                                class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $rule['destination']['port'] ?? '*' }}
                                            </td>
                                            <td
                                                class="px-3 py-2 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $rule['gateway'] ?? '*' }}
                                            </td>

                                            <td class="px-3 py-2 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $rule['descr'] ?? '' }}
                                            </td>
                                            <td class="px-3 py-2 whitespace-nowrap text-sm font-medium text-center">
                                                <div class="flex justify-center space-x-2">
                                                    {{-- Edit Button --}}
                                                    <a href="{{ route('firewall.rules.edit', ['firewall' => $firewall, 'tracker' => $rule['tracker']]) }}"
                                                        class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300"
                                                        title="Edit">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                                        </svg>
                                                    </a>

                                                    {{-- Copy Button --}}
                                                    <button type="button"
                                                        class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-300"
                                                        title="Copy">
                                                        <svg class="w-5 h-5" fill="none" stroke="currentColor"
                                                            viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                                stroke-width="2"
                                                                d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
                                                        </svg>
                                                    </button>

                                                    {{-- Delete Button --}}
                                                    <button type="button"
                                                        @click="openConfirmModal('Delete Rule', 'Are you sure you want to delete this rule?', () => document.getElementById('delete-form-{{ $rule['tracker'] }}').submit())"
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
                                            <td colspan="12"
                                                class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                                No rules found for this interface.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </form>

                    {{-- Separate Apply Changes Form --}}
                    <form id="apply-changes-form" method="POST" action="{{ route('firewall.apply', $firewall) }}"
                        class="hidden">
                        @csrf
                    </form>

                    {{-- Hidden Delete Forms --}}
                    @foreach($filteredRules as $rule)
                        <form id="delete-form-{{ $rule['tracker'] }}" method="POST"
                            action="{{ route('firewall.rules.destroy', ['firewall' => $firewall, 'tracker' => $rule['tracker']]) }}"
                            class="hidden">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="interface" value="{{ $selectedInterface }}">
                        </form>
                    @endforeach
                    {{-- Confirmation Modal --}}
                    <div x-show="confirmModal.open" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto"
                        aria-labelledby="modal-title" role="dialog" aria-modal="true">
                        <div
                            class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div x-show="confirmModal.open" x-transition:enter="ease-out duration-300"
                                x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                                x-transition:leave="ease-in duration-200" x-transition:leave-start="opacity-100"
                                x-transition:leave-end="opacity-0"
                                class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"
                                @click="closeConfirmModal()"></div>

                            <span class="hidden sm:inline-block sm:align-middle sm:h-screen"
                                aria-hidden="true">&#8203;</span>

                            <div x-show="confirmModal.open" x-transition:enter="ease-out duration-300"
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
                                            <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24"
                                                stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                        </div>
                                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left">
                                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100"
                                                id="modal-title" x-text="confirmModal.title"></h3>
                                            <div class="mt-2">
                                                <p class="text-sm text-gray-500 dark:text-gray-400"
                                                    x-text="confirmModal.message"></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                    <button type="button" @click="confirmAction()"
                                        class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-red-600 text-base font-medium text-white hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:ml-3 sm:w-auto sm:text-sm">
                                        Confirm
                                    </button>
                                    <button type="button" @click="closeConfirmModal()"
                                        class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>