<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Interface Assignments') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <x-card>
                <div class="p-6">
                    @if(isset($error))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">{{ $error }}</span>
                        </div>
                    @endif

                    <div class="mb-6">
                        <x-card-header title="Current Assignments" />
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Interface</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Network Port</th>
                                        @if(!auth()->user()->isReadOnly())
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($interfaces as $key => $interface)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ strtoupper($key) }} ({{ $interface['descr'] ?? $key }})</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $interface['if'] ?? 'N/A' }}</td>
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @if(!in_array($key, ['wan', 'lan']))
                                                    <form
                                                        action="{{ route('interfaces.assignments.destroy', ['firewall' => $firewall, 'id' => $key]) }}"
                                                        method="POST" class="inline"
                                                        onsubmit="return confirm('Are you sure you want to unassign this interface?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit"
                                                            class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Unassign</button>
                                                    </form>
                                                @else
                                                    <span class="text-gray-400">Cannot Unassign</span>
                                                @endif
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ auth()->user()->isReadOnly() ? '2' : '3' }}" class="px-6 py-4 text-center text-gray-500">No interfaces assigned.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>

                    @if(!auth()->user()->isReadOnly())
                    <div class="mt-8">
                        <h3 class="text-lg font-medium mb-2">Assign New Interface</h3>
                        <form method="POST" action="{{ route('interfaces.assignments.store', $firewall) }}"
                            class="flex items-end gap-4">
                            @csrf
                            <div class="flex-grow">
                                <x-input-label for="if" :value="__('Network Port')" />
                                <select id="if" name="if"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    @foreach($availablePorts as $port => $info)
                                        <option value="{{ $port }}">{{ $port }} ({{ $info['mac'] ?? '' }})</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <x-input-label for="descr" :value="__('Description (Optional)')" />
                                <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr"
                                    :value="old('descr')" />
                            </div>
                            <div class="mb-1">
                                <x-primary-button>
                                    {{ __('Add') }}
                                </x-primary-button>
                            </div>
                        </form>
                    </div>
                    @endif

                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
