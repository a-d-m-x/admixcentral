<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System: Package Manager') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">

                    <!-- Tabs -->
                    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            @foreach(['installed' => 'Installed Packages', 'available' => 'Available Packages'] as $key => $label)
                                <a href="{{ route('system.package_manager.index', ['firewall' => $firewall, 'tab' => $key]) }}"
                                    class="{{ $tab === $key ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-600' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    @if (session('success'))
                        <div class="mb-4 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (isset($data['error']))
                        <div class="mb-4 font-medium text-sm text-red-600 dark:text-red-400">
                            Error fetching packages: {{ $data['error'] }}
                        </div>
                    @endif

                    <!-- Content -->
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Category</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Version</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    @if(!auth()->user()->isReadOnly())
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($data['packages'] as $pkg)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ $pkg['name'] ?? 'Unknown' }}
                                            @if(!empty($pkg['shortname']))
                                                <br><span class="text-xs text-gray-500 dark:text-gray-400">({{ $pkg['shortname'] }})</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $pkg['category'] ?? 'Other' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $pkg['version'] ?? '-' }}
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500 dark:text-gray-400 max-w-xs truncate"
                                            title="{{ $pkg['descr'] ?? '' }}">{{ $pkg['descr'] ?? '-' }}</td>
                                        @if(!auth()->user()->isReadOnly())
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            @if($tab === 'installed')
                                                <form action="{{ route('system.package_manager.uninstall', $firewall) }}"
                                                    method="POST" class="inline-block"
                                                    onsubmit="return confirm('Are you sure you want to uninstall {{ $pkg['name'] ?? 'this package' }}?');">
                                                    @csrf
                                                    {{-- API uses 0-based array index as ID for package operations --}}
                                                    <input type="hidden" name="id" value="{{ $loop->index }}">
                                                    <input type="hidden" name="name" value="{{ $pkg['name'] ?? '' }}">
                                                    <button type="submit"
                                                        class="text-red-600 dark:text-red-400 hover:text-red-900 dark:hover:text-red-300">Uninstall</button>
                                                </form>
                                            @else
                                                <form action="{{ route('system.package_manager.install', $firewall) }}"
                                                    method="POST" class="inline-block"
                                                    onsubmit="return confirm('Are you sure you want to install {{ $pkg['name'] ?? 'this package' }}?');">
                                                    @csrf
                                                    <input type="hidden" name="name" value="{{ $pkg['name'] ?? '' }}">
                                                    <button type="submit"
                                                        class="text-green-600 dark:text-green-400 hover:text-green-900 dark:hover:text-green-300">Install</button>
                                                </form>
                                            @endif
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ auth()->user()->isReadOnly() ? '4' : '5' }}"
                                            class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                            No packages found.
                                        </td>
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
