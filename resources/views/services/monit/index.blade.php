<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Monit (System Health)') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative dark:bg-green-900/30 dark:border-green-800 dark:text-green-300">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error') || isset($error))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative dark:bg-red-900/30 dark:border-red-800 dark:text-red-300">
                    {{ session('error') ?? $error }}
                </div>
            @endif

            {{-- Monit Service Status Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Monit Daemon Status</h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Automated system process monitoring and recovery</p>
                    </div>
                    <div class="flex items-center gap-3">
                        @php
                            $isRunning = ($status['status'] ?? '') === 'running';
                            $isDisabled = ($status['status'] ?? '') === 'disabled';
                        @endphp

                        @if($isRunning)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
                                <span class="w-2 h-2 mr-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                Running
                            </span>
                        @elseif($isDisabled)
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                Disabled
                            </span>
                        @else
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
                                Stopped
                            </span>
                        @endif

                        @if(!auth()->user()->isReadOnly())
                            <form action="{{ route('services.monit.action', [$firewall, 'restart']) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded text-xs font-medium transition">
                                    Reload / Restart
                                </button>
                            </form>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 pt-6 border-t border-gray-100 dark:border-gray-700">
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Service State</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ ($settings['enabled'] ?? '0') === '1' ? 'Enabled' : 'Disabled' }}
                        </p>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Poll Interval</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            {{ $settings['interval'] ?? '30' }} seconds
                        </p>
                    </div>
                    <div class="p-4 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                        <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Web Service</span>
                        <p class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                            Port {{ $settings['port'] ?? '2812' }}
                        </p>
                    </div>
                </div>
            </div>

            {{-- Monit Info Card --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-2">About Monit on OPNsense</h4>
                <p class="text-sm text-gray-600 dark:text-gray-300 leading-relaxed">
                    Monit conducts automatic maintenance and repair, and can execute meaningful causal actions in error situations (e.g. restart crashing daemons, alert administrators when memory or disk usage crosses thresholds).
                </p>
            </div>

        </div>
    </div>
</x-app-layout>
