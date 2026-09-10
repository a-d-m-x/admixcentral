<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Create VXLAN Interface') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <x-card-header title="Add VXLAN Interface" />

                <div class="p-6">
                    @if(session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <strong class="font-bold">Error!</strong>
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('interfaces.vxlans.store', $firewall) }}">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-4xl">
                            <!-- Device ID -->
                            <div>
                                <x-input-label for="deviceId" :value="__('Device ID')" />
                                <x-text-input id="deviceId" class="block mt-1 w-full" type="number" min="0" max="65535"
                                    name="deviceId" :value="old('deviceId', 1)" required autofocus />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Numeric device identifier. For example, 1 creates interface <code>vxlan1</code>.
                                </p>
                            </div>

                            <!-- VNI -->
                            <div>
                                <x-input-label for="vxlanid" :value="__('VXLAN Network Identifier (VNI)')" />
                                <x-text-input id="vxlanid" class="block mt-1 w-full" type="number" min="1" max="16777215"
                                    name="vxlanid" :value="old('vxlanid', 100)" required />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    The 24-bit VXLAN Network Identifier (1 - 16,777,215).
                                </p>
                            </div>

                            <!-- Source Address -->
                            <div>
                                <x-input-label for="vxlanlocal" :value="__('Source Address')" />
                                <x-text-input id="vxlanlocal" class="block mt-1 w-full" type="text"
                                    name="vxlanlocal" :value="old('vxlanlocal')" required placeholder="e.g. 192.168.240.11" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Local IP address used to originate VXLAN packets.
                                </p>
                            </div>

                            <!-- Local Port -->
                            <div>
                                <x-input-label for="vxlanlocalport" :value="__('Local Port (Optional)')" />
                                <x-text-input id="vxlanlocalport" class="block mt-1 w-full" type="number" min="1" max="65535"
                                    name="vxlanlocalport" :value="old('vxlanlocalport')" placeholder="Default: 4789" />
                            </div>

                            <!-- Remote Address (Unicast) -->
                            <div>
                                <x-input-label for="vxlanremote" :value="__('Remote Address (Unicast)')" />
                                <x-text-input id="vxlanremote" class="block mt-1 w-full" type="text"
                                    name="vxlanremote" :value="old('vxlanremote')" placeholder="e.g. 192.168.240.12" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Destination IP address for point-to-point unicast tunnel.
                                </p>
                            </div>

                            <!-- Remote Port -->
                            <div>
                                <x-input-label for="vxlanremoteport" :value="__('Remote Port (Optional)')" />
                                <x-text-input id="vxlanremoteport" class="block mt-1 w-full" type="number" min="1" max="65535"
                                    name="vxlanremoteport" :value="old('vxlanremoteport')" placeholder="Default: 4789" />
                            </div>

                            <!-- Multicast Group -->
                            <div>
                                <x-input-label for="vxlangroup" :value="__('Multicast Group (Optional)')" />
                                <x-text-input id="vxlangroup" class="block mt-1 w-full" type="text"
                                    name="vxlangroup" :value="old('vxlangroup')" placeholder="e.g. 239.0.0.1" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    IPv4/IPv6 multicast group address (leave empty if using unicast remote).
                                </p>
                            </div>

                            <!-- Multicast Parent Interface -->
                            <div>
                                <x-input-label for="vxlandev" :value="__('Multicast Physical Interface (Optional)')" />
                                <select id="vxlandev" name="vxlandev"
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="">-- None / Unicast --</option>
                                    @foreach($interfaces as $ifCode => $ifData)
                                        <option value="{{ $ifCode }}" {{ old('vxlandev') === $ifCode ? 'selected' : '' }}>
                                            {{ $ifData['descr'] ?? strtoupper($ifCode) }} ({{ $ifCode }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Only used when Multicast Group is specified. Must be None for Unicast.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-6">
                            <x-primary-button>{{ __('Save VXLAN') }}</x-primary-button>
                            <a href="{{ route('interfaces.vxlans.index', $firewall) }}">
                                <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                            </a>
                        </div>
                    </form>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
