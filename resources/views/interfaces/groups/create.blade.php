<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Create Interface Group') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    @if(session('error'))
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"
                            role="alert">
                            <strong class="font-bold">Error:</strong>
                            <span class="block sm:inline">{{ session('error') }}</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('interfaces.groups.store', $firewall) }}">
                        @csrf

                        <div class="grid grid-cols-1 gap-6">
                            <!-- Name -->
                            <div>
                                <x-input-label for="ifname" :value="__('Group Name')" />
                                <x-text-input id="ifname" class="block mt-1 w-full" type="text" name="ifname"
                                    :value="old('ifname')" required autofocus />
                                <p class="text-sm text-gray-500 mt-1">Name must be less than 16 characters, no numbers,
                                    no spaces.</p>
                            </div>

                            <!-- Member Interfaces -->
                            <div>
                                <x-input-label for="members" :value="__('Member Interfaces')" />
                                <select id="members" name="members[]" multiple
                                    class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm h-48">
                                    @foreach($interfaces as $ifName => $details)
                                        <option value="{{ $ifName }}">
                                            {{ strtoupper($ifName) }} ({{ $details['if'] ?? '' }} -
                                            {{ $details['desk'] ?? $details['descr'] ?? '' }})
                                        </option>
                                    @endforeach
                                </select>
                                <p class="text-sm text-gray-500 mt-1">Hold Ctrl (Windows) or Cmd (Mac) to select
                                    multiple interfaces.</p>
                            </div>

                            <!-- Description -->
                            <div>
                                <x-input-label for="descr" :value="__('Description')" />
                                <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr"
                                    :value="old('descr')" />
                            </div>
                        </div>

                        <div class="flex items-center gap-3 mt-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                            <a href="{{ route('interfaces.groups.index', $firewall) }}">
                                <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
