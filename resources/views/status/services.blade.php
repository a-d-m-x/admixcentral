{{--
    View: Services Status
    Purpose: Lists all services on the firewall and indicates whether they are running or stopped.
    Note: 'Actions' column is currently a placeholder for future functionality (Start/Stop/Restart).
--}}
<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Services Status') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead
                                class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Service</th>
                                    <th scope="col" class="py-3 px-6">Description</th>
                                    <th scope="col" class="py-3 px-6">Status</th>
                                    <th scope="col" class="py-3 px-6">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($services as $service)
                                    <tr
                                        class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6 font-medium text-gray-900 dark:text-white">
                                            {{ $service['name'] ?? '' }}
                                        </td>
                                        <td class="py-4 px-6">{{ $service['description'] ?? '' }}</td>
                                        <td class="py-4 px-6">
                                            @php
                                                $rawStatus = $service['status'] ?? ($service['running'] ?? '');
                                                $isRunning = ($rawStatus == '1' || $rawStatus === 'running' || $rawStatus === true);
                                            @endphp
                                            <span
                                                class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $isRunning ? 'bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300' }}">
                                                {{ $isRunning ? 'Running' : 'Stopped' }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6">
                                            @if($firewall->isOpnSense() && !auth()->user()->isReadOnly())
                                                <div class="flex items-center space-x-3">
                                                    @if(!$isRunning)
                                                    <form method="POST" action="{{ route('status.services.action', ['firewall' => $firewall, 'service' => $service['name'] ?? $service['id'], 'action' => 'start']) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-green-600 dark:text-green-400 hover:text-green-900 font-medium text-xs">Start</button>
                                                    </form>
                                                    @else
                                                    <form method="POST" action="{{ route('status.services.action', ['firewall' => $firewall, 'service' => $service['name'] ?? $service['id'], 'action' => 'restart']) }}" class="inline">
                                                        @csrf
                                                        <button type="submit" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900 font-medium text-xs">Restart</button>
                                                    </form>
                                                    @if(empty($service['locked']))
                                                    <form method="POST" action="{{ route('status.services.action', ['firewall' => $firewall, 'service' => $service['name'] ?? $service['id'], 'action' => 'stop']) }}" class="inline" onsubmit="return confirm('Are you sure you want to stop this service?');">
                                                        @csrf
                                                        <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-900 font-medium text-xs">Stop</button>
                                                    </form>
                                                    @endif
                                                    @endif
                                                </div>
                                            @else
                                                <span class="text-gray-400">Managed via pfSense</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="4" class="py-4 px-6 text-center">No services found.</td>
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
