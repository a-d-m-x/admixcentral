<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Add Schedule') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('firewall.schedules.store', $firewall) }}">
                        @csrf

                        <!-- Name -->
                        <div class="mb-4">
                            <x-input-label for="name" :value="__('Name')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name"
                                :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                            <p class="mt-1 text-sm text-gray-500">
                                The name of the schedule. Must be alphanumeric and underscores only.
                            </p>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <x-input-label for="descr" :value="__('Description')" />
                            <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr"
                                :value="old('descr')" />
                            <x-input-error :messages="$errors->get('descr')" class="mt-2" />
                        </div>

                        <!-- Time Range (Single for now) -->
                        <div class="mb-4 border p-4 rounded-md">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Time Range 1</h3>
                            <div class="grid grid-cols-3 gap-4">
                                <div>
                                    <x-input-label for="month" :value="__('Month')" />
                                    <x-text-input id="month" class="block mt-1 w-full" type="text" name="month"
                                        :value="old('month')" placeholder="1-12 or all" />
                                </div>
                                <div>
                                    <x-input-label for="day" :value="__('Day')" />
                                    <x-text-input id="day" class="block mt-1 w-full" type="text" name="day"
                                        :value="old('day')" placeholder="1-31 or all" />
                                </div>
                                <div>
                                    <x-input-label for="hour" :value="__('Hour')" />
                                    <x-text-input id="hour" class="block mt-1 w-full" type="text" name="hour"
                                        :value="old('hour')" placeholder="0:00-23:59" />
                                </div>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Enter 'all' or specific values (e.g., 1,2,3 or 1-5).
                            </p>
                        </div>

                        <div class="flex items-center gap-3 mt-4">
                            <x-primary-button>{{ __('Save') }}</x-primary-button>
                            <a href="{{ route('firewall.schedules.index', $firewall) }}">
                                <x-secondary-button type="button">{{ __('Cancel') }}</x-secondary-button>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
