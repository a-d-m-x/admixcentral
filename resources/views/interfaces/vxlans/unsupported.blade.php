<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('VXLAN Interfaces') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <x-card>
                <div class="p-6 text-center">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                    <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">API Not Supported</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        VXLAN interface management is not supported or available on this firewall platform.
                    </p>
                    <div class="mt-6">
                        <a href="{{ route('firewall.interfaces.index', $firewall) }}"
                            class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Back to Interfaces
                        </a>
                    </div>
                </div>
            </x-card>
        </div>
    </div>
</x-app-layout>
