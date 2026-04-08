<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Command Prompt') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Execute Shell Command</h3>

                    @if(!auth()->user()->isReadOnly())
                        <form method="POST" action="{{ route('diagnostics.command-prompt', $firewall) }}" class="mb-6">
                            @csrf
                            <div class="flex gap-4">
                                <input type="text" name="command"
                                    class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 shadow-sm"
                                    placeholder="Enter command (e.g., date, uptime)" required>
                                <button type="submit"
                                    class="px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-500 focus:bg-indigo-500 active:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                    Execute
                                </button>
                            </div>
                        </form>
                    @endif

                    @if(isset($output))
                        <div class="mt-6">
                            <h4 class="text-md font-semibold mb-2">Output:</h4>
                            <div class="bg-gray-100 dark:bg-gray-900 p-4 rounded-md overflow-x-auto">
                                @if(isset($output['error']))
                                    <div class="text-red-500">{{ $output['error'] }}</div>
                                @elseif(is_array($output))
                                    <pre class="text-sm font-mono text-gray-800 dark:text-gray-200">{{ implode("\n", $output) }}</pre>
                                @else
                                    <pre class="text-sm font-mono text-gray-800 dark:text-gray-200">{{ $output }}</pre>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
