<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('CARP Status') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if (session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif
            @if (session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Global CARP Settings --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">Global CARP Settings</h3>

                    <form action="{{ route('status.carp.update', $firewall) }}" method="POST" class="space-y-4">
                        @csrf
                        <fieldset @if(auth()->user()->isReadOnly()) disabled @endif class="[&:disabled]:opacity-60 [&:disabled]:pointer-events-none">
                        <div class="flex items-center">
                            <input type="checkbox" id="enable" name="enable" class="pf-checkbox"
                                {{ ($carpStatus['enable'] ?? false) ? 'checked' : '' }}>
                            <label for="enable" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Enable CARP</label>
                        </div>
                        <div class="flex items-center">
                            <input type="checkbox" id="maintenance_mode" name="maintenance_mode" class="pf-checkbox"
                                {{ ($carpStatus['maintenance_mode'] ?? false) ? 'checked' : '' }}>
                            <label for="maintenance_mode" class="ml-2 text-sm text-gray-700 dark:text-gray-300">Maintenance Mode</label>
                        </div>
                        </fieldset>
                        @if(!auth()->user()->isReadOnly())
                        <div>
                            <button type="submit" class="pf-btn pf-btn-primary">Save Settings</button>
                        </div>
                        @endif
                    </form>
                </div>
            </div>

            {{-- Virtual IPs --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">Virtual IPs</h3>
                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Type</th>
                                    <th scope="col" class="py-3 px-6">Interface</th>
                                    <th scope="col" class="py-3 px-6">Address</th>
                                    <th scope="col" class="py-3 px-6">VHID</th>
                                    <th scope="col" class="py-3 px-6">Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($virtualIps as $vip)
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6">{{ $vip['mode'] ?? 'N/A' }}</td>
                                        <td class="py-4 px-6">{{ strtoupper($vip['interface'] ?? '') }}</td>
                                        <td class="py-4 px-6">{{ $vip['subnet'] ?? '' }}/{{ $vip['subnet_bits'] ?? '' }}</td>
                                        <td class="py-4 px-6">{{ $vip['vhid'] ?? '-' }}</td>
                                        <td class="py-4 px-6">{{ $vip['descr'] ?? '' }}</td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="5" class="py-4 px-6 text-center">No Virtual IPs found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
