<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('VLANs') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <x-card>
                <x-card-header title="VLAN Interfaces">
                    @if(!auth()->user()->isReadOnly())
                    <x-link-button-add href="{{ route('interfaces.vlans.create', $firewall) }}">
                        Add VLAN
                    </x-link-button-add>
                    @endif
                </x-card-header>
                
                <div class="p-6">
                    @if(isset($error))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">{{ $error }}</span>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Interface</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Tag</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        PCP</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Description</th>
                                    <th
                                        class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                        Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($vlans as $vlan)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $vlan['if'] ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $vlan['tag'] ?? 'N/A' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $vlan['pcp'] ?? '0' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap">{{ $vlan['descr'] ?? '' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="{{ route('interfaces.vlans.edit', ['firewall' => $firewall, 'id' => $loop->index]) }}"
                                                class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 mr-2">Edit</a>
                                            <form
                                                action="{{ route('interfaces.vlans.destroy', ['firewall' => $firewall, 'id' => $loop->index]) }}"
                                                method="POST" class="inline"
                                                onsubmit="return confirm('Are you sure you want to delete this VLAN?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="px-6 py-4 text-center text-gray-500">No VLANs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
