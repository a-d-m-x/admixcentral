<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System Status') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 shadow">
                            <h3 class="text-lg font-semibold mb-4">System Information</h3>
                            <div class="space-y-3">
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Hostname:</span>
                                    <span class="font-medium">{{ $system['hostname'] ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Version:</span>
                                    <span class="font-medium">{{ $system['version'] ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Platform:</span>
                                    <span class="font-medium">{{ $system['platform'] ?? 'N/A' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-500 dark:text-gray-400">Uptime:</span>
                                    <span class="font-medium">{{ $system['uptime'] ?? 'N/A' }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 shadow">
                            <h3 class="text-lg font-semibold mb-4">Resource Usage</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">CPU
                                            Usage</span>
                                        <span
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ round($system['cpu_usage'] ?? 0, 2) }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-600">
                                        <div class="bg-blue-600 h-2.5 rounded-full"
                                            style="width: {{ $system['cpu_usage'] ?? 0 }}%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Memory
                                            Usage</span>
                                        <span
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ round($system['mem_usage'] ?? 0, 2) }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-600">
                                        <div class="bg-blue-600 h-2.5 rounded-full"
                                            style="width: {{ $system['mem_usage'] ?? 0 }}%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Disk
                                            Usage</span>
                                        <span
                                            class="text-sm font-medium text-gray-700 dark:text-gray-300">{{ round($system['disk_usage'] ?? 0, 2) }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-600">
                                        <div class="bg-blue-600 h-2.5 rounded-full"
                                            style="width: {{ $system['disk_usage'] ?? 0 }}%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
