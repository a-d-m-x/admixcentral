<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Certificate Manager') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <x-card>
                <!-- Tabs -->
                <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                    <nav class="-mb-px flex space-x-8">
                        <a href="{{ route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'cas']) }}"
                            class="{{ $tab === 'cas' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            CAs
                        </a>
                        <a href="{{ route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'certificates']) }}"
                            class="{{ $tab === 'certificates' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Certificates
                        </a>
                        </a>
                        <a href="{{ route('system.certificate_manager.index', ['firewall' => $firewall, 'tab' => 'crls']) }}"
                            class="{{ $tab === 'crls' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                            Revocation
                        </a>
                    </nav>
                </div>

                @if(isset($error))
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"
                        role="alert">
                        <strong class="font-bold">Error!</strong>
                        <span class="block sm:inline">{{ $error }}</span>
                    </div>
                @endif

                @if($tab === 'cas')
                    <x-card-header title="Certificate Authorities">
                        @if(!auth()->user()->isReadOnly())
                        <x-link-button-add href="{{ route('system.certificate_manager.cas.create', $firewall) }}">
                            Add CA
                        </x-link-button-add>
                        @endif
                    </x-card-header>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Name</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Internal</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Issuer</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Certificates</th>
                                        @if(!auth()->user()->isReadOnly())
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($data['cas'] ?? [] as $ca)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $ca['descr'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ isset($ca['prv']) ? 'Yes' : 'No' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $ca['caref'] ?? 'Self-signed' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $ca['refcount'] ?? 0 }}</td>
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <form
                                                    action="{{ route('system.certificate_manager.cas.destroy', ['firewall' => $firewall, 'id' => $ca['refid']]) }}"
                                                    method="POST" class="inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this CA?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                                </form>
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 text-center text-gray-500">No Certificate
                                                Authorities found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif($tab === 'certificates')
                        <x-card-header title="Certificates">
                            @if(!auth()->user()->isReadOnly())
                            <x-link-button-add href="{{ route('system.certificate_manager.certificates.create', $firewall) }}">
                                Add/Sign Certificate
                            </x-link-button-add>
                            @endif
                        </x-card-header>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Name</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Issuer</th>
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Type</th>
                                        @if(!auth()->user()->isReadOnly())
                                        <th
                                            class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse($data['certificates'] ?? [] as $cert)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $cert['descr'] ?? 'N/A' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">{{ $cert['caref'] ?? 'External' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                {{ isset($cert['prv']) ? 'Server/User' : 'CA/Server' }}</td>
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <form
                                                    action="{{ route('system.certificate_manager.certificates.destroy', ['firewall' => $firewall, 'id' => $cert['refid']]) }}"
                                                    method="POST" class="inline"
                                                    onsubmit="return confirm('Are you sure you want to delete this Certificate?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit"
                                                        class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                                </form>
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 text-center text-gray-500">No Certificates found.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @elseif($tab === 'crls')
                        @include('system.certificate_manager.crls.index')
                    @endif

            </x-card>
        </div>
    </div>
</x-app-layout>
