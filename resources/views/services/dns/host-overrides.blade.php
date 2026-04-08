<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Edit Host Override') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="pf-alert pf-alert-success mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="pf-alert pf-alert-error mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    {{-- Tabs --}}
                    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                        <nav class="-mb-px flex space-x-8 overflow-x-auto">
                            <a href="{{ route('services.dns.resolver', $firewall) }}"
                                class="border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                General Settings
                            </a>
                            <a href="{{ route('services.dns.host-overrides', $firewall) }}"
                                class="border-indigo-500 text-indigo-600 dark:text-indigo-400 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Host Overrides
                            </a>
                        </nav>
                    </div>

                    {{-- Add New Override — hidden for readonly --}}
                    @if(!auth()->user()->isReadOnly())
                    <div class="mb-8 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Add Host Override</h3>
                        <form action="{{ route('services.dns.host-overrides.store', $firewall) }}" method="POST">
                            @csrf
                            <div class="pf-form-grid">
                                <div>
                                    <label class="pf-label">Host</label>
                                    <input type="text" name="host" class="pf-input" required placeholder="hostname">
                                </div>
                                <div>
                                    <label class="pf-label">Domain</label>
                                    <input type="text" name="domain" class="pf-input" required
                                        placeholder="example.com">
                                </div>
                                <div>
                                    <label class="pf-label">IP Address</label>
                                    <input type="text" name="ip" class="pf-input" required placeholder="192.168.1.100">
                                </div>
                                <div>
                                    <label class="pf-label">Description</label>
                                    <input type="text" name="descr" class="pf-input">
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="submit" class="btn-primary">Add</button>
                            </div>
                        </form>
                    </div>
                    @endif

                    {{-- List --}}
                    <div class="pf-table-container">
                        <table class="pf-table">
                            <thead>
                                <tr>
                                    <th>Host</th>
                                    <th>Domain</th>
                                    <th>IP</th>
                                    <th>Description</th>
                                    @if(!auth()->user()->isReadOnly())
                                    <th>Actions</th>
                                    @endif
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($hosts as $host)
                                    <tr>
                                        <td data-label="Host">{{ $host['host'] ?? '' }}</td>
                                        <td data-label="Domain">{{ $host['domain'] ?? '' }}</td>
                                        <td data-label="IP">{{ $host['ip'] ?? '' }}</td>
                                        <td data-label="Description">{{ $host['descr'] ?? '' }}</td>
                                        @if(!auth()->user()->isReadOnly())
                                        <td data-label="Actions">
                                            <button class="text-red-600 hover:text-red-900 dark:text-red-400"
                                                disabled>Delete</button>
                                        </td>
                                        @endif
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ auth()->user()->isReadOnly() ? '4' : '5' }}" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                                            No host overrides defined.
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
