<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Create Loopback Interface') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <x-card-header title="Add Loopback Interface" />

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

                    <form method="POST" action="{{ route('interfaces.loopbacks.store', $firewall) }}">
                        @csrf

                        <div class="grid grid-cols-1 gap-6 max-w-xl">
                            <!-- Device ID -->
                            <div>
                                <x-input-label for="deviceId" :value="__('Device ID')" />
                                <x-text-input id="deviceId" class="block mt-1 w-full" type="number" min="0" max="65535"
                                    name="deviceId" :value="old('deviceId', 1)" required autofocus />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Numeric device identifier. For example, 1 creates interface <code>lo1</code>.
                                </p>
                            </div>

                            <!-- Description -->
                            <div>
                                <x-input-label for="description" :value="__('Description')" />
                                <x-text-input id="description" class="block mt-1 w-full" type="text"
                                    name="description" :value="old('description')" required
                                    placeholder="e.g. Internal Services Loopback" />
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Description to identify this loopback interface.
                                </p>
                            </div>

                            <div class="flex items-center gap-4 mt-4">
                                <x-primary-button>
                                    {{ __('Save Loopback') }}
                                </x-primary-button>
                                <a href="{{ route('interfaces.loopbacks.index', $firewall) }}"
                                    class="text-sm text-gray-600 dark:text-gray-400 hover:underline">
                                    {{ __('Cancel') }}
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
