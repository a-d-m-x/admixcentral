{{-- 
    View: Halt System
    Purpose: Display a warning page to the user before they halt the system.
    This page ensures the user confirms the action as it requires manual power-on.
--}}
<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Halt System') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100 text-center">
                    <h3 class="text-lg font-semibold mb-4 text-red-600 dark:text-red-400">Halt System</h3>

                    @if(!auth()->user()->isReadOnly())
                        <p class="mb-6">Are you sure you want to halt the system? The system will shut down and must be
                            manually powered on.</p>

                        {{-- Form to submit the halt command --}}
                        <form method="POST" action="{{ route('diagnostics.halt-system', $firewall) }}">
                            @csrf
                            {{-- Submit button with a javascript confirmation dialog for extra safety --}}
                            <button type="submit"
                                class="px-6 py-3 bg-red-600 border border-transparent rounded-md font-semibold text-white uppercase tracking-widest hover:bg-red-500 focus:bg-red-500 active:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                                onclick="return confirm('Are you absolutely sure you want to halt the system?');">
                                Halt System
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
