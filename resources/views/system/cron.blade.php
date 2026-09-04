<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Cron - Scheduled Tasks') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{
        showModal: false,
        isEdit: false,
        editUuid: '',
        form: {
            enabled: true,
            command: '',
            description: '',
            minutes: '*',
            hours: '*',
            days: '*',
            months: '*',
            weekdays: '*',
            who: 'root',
            parameters: ''
        },
        openAddModal() {
            this.isEdit = false;
            this.editUuid = '';
            this.form = {
                enabled: true,
                command: Object.keys(this.commands)[0] || '',
                description: '',
                minutes: '0',
                hours: '0',
                days: '*',
                months: '*',
                weekdays: '*',
                who: 'root',
                parameters: ''
            };
            this.showModal = true;
        },
        openEditModal(job) {
            this.isEdit = true;
            this.editUuid = job.uuid || job.id || '';
            this.form = {
                enabled: job.enabled == '1' || job.enabled === true,
                command: job.command || '',
                description: job.description || '',
                minutes: job.minutes || '*',
                hours: job.hours || '*',
                days: job.days || '*',
                months: job.months || '*',
                weekdays: job.weekdays || '*',
                who: job.who || 'root',
                parameters: job.parameters || ''
            };
            this.showModal = true;
        },
        commands: {{ Js::from($commands) }}
    }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium">Scheduled Tasks</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Manage automated system tasks, maintenance jobs, and periodic service restarts.</p>
                        </div>
                        @if(!auth()->user()->isReadOnly())
                        <button @click="openAddModal()"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Cron Job
                        </button>
                        @endif
                    </div>

                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 px-6 text-center">Status</th>
                                    <th scope="col" class="py-3 px-6">Description</th>
                                    <th scope="col" class="py-3 px-6">Command</th>
                                    <th scope="col" class="py-3 px-6 font-mono text-center">Schedule</th>
                                    <th scope="col" class="py-3 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($jobs as $job)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6 text-center">
                                            @if(!auth()->user()->isReadOnly())
                                            <form method="POST" action="{{ route('firewall.system.cron.toggle', ['firewall' => $firewall, 'uuid' => $job['uuid'] ?? $job['id']]) }}" class="inline">
                                                @csrf
                                                <button type="submit" title="Click to toggle">
                                                    <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full cursor-pointer {{ (!empty($job['enabled']) && $job['enabled'] == '1') ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                        {{ (!empty($job['enabled']) && $job['enabled'] == '1') ? 'Enabled' : 'Disabled' }}
                                                    </span>
                                                </button>
                                            </form>
                                            @else
                                                <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full {{ (!empty($job['enabled']) && $job['enabled'] == '1') ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300' }}">
                                                    {{ (!empty($job['enabled']) && $job['enabled'] == '1') ? 'Enabled' : 'Disabled' }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="py-4 px-6 font-medium text-gray-900 dark:text-white">
                                            {{ $job['description'] ?: '—' }}
                                        </td>
                                        <td class="py-4 px-6">
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ $commands[$job['command'] ?? ''] ?? ($job['command'] ?? 'Custom Command') }}
                                            </div>
                                            @if(!empty($job['parameters']))
                                                <div class="text-xs text-gray-400 font-mono">{{ $job['parameters'] }}</div>
                                            @endif
                                        </td>
                                        <td class="py-4 px-6 text-center font-mono text-xs">
                                            <span class="bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">
                                                {{ $job['minutes'] ?? '*' }} {{ $job['hours'] ?? '*' }} {{ $job['days'] ?? '*' }} {{ $job['months'] ?? '*' }} {{ $job['weekdays'] ?? '*' }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-right space-x-2">
                                            @if(!auth()->user()->isReadOnly())
                                            <button @click="openEditModal({{ Js::from($job) }})"
                                                class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">Edit</button>
                                            <form method="POST" action="{{ route('firewall.system.cron.destroy', ['firewall' => $firewall, 'uuid' => $job['uuid'] ?? $job['id']]) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this scheduled task?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-900">Delete</button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="5" class="py-6 px-6 text-center text-gray-500">
                                            No scheduled tasks configured. Click "Add Cron Job" to create one.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add / Edit Modal --}}
        <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showModal" @click="showModal = false" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showModal" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                    <form :action="isEdit ? '{{ url('/firewall/' . $firewall->id . '/system/cron') }}/' + editUuid : '{{ route('firewall.system.cron.store', $firewall) }}'" method="POST">
                        @csrf
                        <template x-if="isEdit">
                            <input type="hidden" name="_method" value="PATCH">
                        </template>

                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title" x-text="isEdit ? 'Edit Scheduled Task' : 'Add Scheduled Task'"></h3>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="enabled" value="1" x-model="form.enabled" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Enabled</span>
                                    </label>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Command</label>
                                    <select name="command" x-model="form.command" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                        <template x-for="(label, key) in commands" :key="key">
                                            <option :value="key" x-text="label"></option>
                                        </template>
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                                    <input type="text" name="description" x-model="form.description" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="e.g. Daily Firmware Update Check">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Parameters (Optional)</label>
                                    <input type="text" name="parameters" x-model="form.parameters" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Command-specific parameters if needed">
                                </div>

                                <div class="grid grid-cols-5 gap-2 font-mono">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 font-sans">Min</label>
                                        <input type="text" name="minutes" x-model="form.minutes" required class="mt-1 block w-full text-center rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm sm:text-xs" placeholder="*">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 font-sans">Hour</label>
                                        <input type="text" name="hours" x-model="form.hours" required class="mt-1 block w-full text-center rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm sm:text-xs" placeholder="*">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 font-sans">Day</label>
                                        <input type="text" name="days" x-model="form.days" required class="mt-1 block w-full text-center rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm sm:text-xs" placeholder="*">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 font-sans">Month</label>
                                        <input type="text" name="months" x-model="form.months" required class="mt-1 block w-full text-center rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm sm:text-xs" placeholder="*">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-700 dark:text-gray-300 font-sans">Weekday</label>
                                        <input type="text" name="weekdays" x-model="form.weekdays" required class="mt-1 block w-full text-center rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm sm:text-xs" placeholder="*">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Save Task
                            </button>
                            <button type="button" @click="showModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
