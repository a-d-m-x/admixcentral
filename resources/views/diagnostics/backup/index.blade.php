<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Backup & Restore') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Backup Section — download is read-only, available to all -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-medium mb-4">Download Configuration</h3>

                        @if(session('error'))
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                                <strong class="font-bold">Error:</strong>
                                <span class="block sm:inline">{{ session('error') }}</span>
                            </div>
                        @endif

                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Click below to download the current configuration XML file from the firewall.
                        </p>

                        <a href="{{ route('diagnostics.backup.download', $firewall) }}"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 focus:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Download Configuration
                        </a>
                    </div>
                </div>

                <!-- Restore Section — write action, hidden for readonly -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-medium mb-4">Restore Configuration</h3>

                        @if(!auth()->user()->isReadOnly())
                            @if(session('success'))
                                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                                    <strong class="font-bold">Success!</strong>
                                    <span class="block sm:inline">{{ session('success') }}</span>
                                </div>
                            @endif

                            <p class="text-gray-600 dark:text-gray-400 mb-4">
                                Upload a configuration XML file to restore. <span class="text-red-500 font-bold">Warning:
                                    The firewall will reboot!</span>
                            </p>

                            <form action="{{ route('diagnostics.restore.upload', $firewall) }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf

                                <div class="mb-4">
                                    <x-input-label for="config_file" :value="__('Configuration File (XML)')" />
                                    <input type="file" id="config_file" name="config_file" accept=".xml"
                                        class="block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400"
                                        required>
                                </div>

                                <div class="flex justify-end">
                                    <x-primary-button class="bg-red-600 hover:bg-red-700 focus:bg-red-700 active:bg-red-900"
                                        onclick="return confirm('Are you sure? This will overwrite the current configuration and reboot the firewall.')">
                                        {{ __('Restore Configuration') }}
                                    </x-primary-button>
                                </div>
                            </form>
                        @else
                            <div class="bg-yellow-50 dark:bg-yellow-900/30 border-l-4 border-yellow-500 p-4">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                            fill="currentColor" aria-hidden="true">
                                            <path fill-rule="evenodd"
                                                d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                clip-rule="evenodd" />
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Access Restricted</h3>
                                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                            <p>You do not have permission to restore a configuration. This action is restricted to administrators.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
