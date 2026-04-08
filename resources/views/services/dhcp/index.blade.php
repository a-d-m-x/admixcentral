<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('DHCP Server') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
                <div class="pf-alert pf-alert-success mb-4">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="pf-alert pf-alert-error mb-4">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    {{-- Interface Tabs --}}
                    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                        <nav class="-mb-px flex space-x-8 overflow-x-auto">
                            @foreach($interfaces as $iface)
                                                    <a href="{{ route('services.dhcp.index', ['firewall' => $firewall, 'interface' => $iface['id']]) }}"
                                                        class="{{ $interface === $iface['id']
                                ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                                : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} 
                                                                                        whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                                        {{ strtoupper($iface['descr'] ?? $iface['id']) }}
                                                    </a>
                            @endforeach
                        </nav>
                    </div>

                    <form
                        action="{{ route('services.dhcp.update', ['firewall' => $firewall, 'interface' => $interface]) }}"
                        method="POST">
                        @csrf
                        @method('PATCH')
                        <fieldset @if(auth()->user()->isReadOnly()) disabled @endif class="[&:disabled]:opacity-60 [&:disabled]:pointer-events-none">

                        <div class="pf-form-container">
                            {{-- Enable --}}
                            <div class="mb-6">
                                <div class="flex items-center">
                                    <input type="checkbox" name="enable" id="enable"
                                        class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800"
                                        {{ !empty($selectedConfig['enable']) ? 'checked' : '' }}>
                                    <label for="enable"
                                        class="ml-2 block text-sm font-medium text-gray-900 dark:text-gray-100">
                                        Enable DHCP server on {{ strtoupper($interface) }} interface
                                    </label>
                                </div>
                            </div>

                            <div class="pf-form-grid">
                                {{-- Range --}}
                                @if(isset($selectedInterface['ipaddr']) && isset($selectedInterface['subnet']) && !auth()->user()->isReadOnly())
                                    <div x-data="{
                                                    ip: '{{ $selectedInterface['ipaddr'] }}',
                                                    subnet: {{ $selectedInterface['subnet'] }},
                                                    calculateRange() {
                                                        // Simple calculation for /24 for now, can be expanded
                                                        if (this.subnet == 24) {
                                                            let parts = this.ip.split('.');
                                                            parts.pop();
                                                            let prefix = parts.join('.');
                                                            return {
                                                                start: prefix + '.10',
                                                                end: prefix + '.245',
                                                                subnet: prefix + '.0/24',
                                                                mask: '255.255.255.0'
                                                            };
                                                        }
                                                        return null;
                                                    },
                                                    applyRange() {
                                                        let range = this.calculateRange();
                                                        if (range) {
                                                            document.getElementById('range_from').value = range.start;
                                                            document.getElementById('range_to').value = range.end;
                                                        }
                                                    }
                                                }"
                                        class="mt-4 mb-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg shadow-sm overflow-hidden md:col-span-2">
                                        <div
                                            class="px-4 py-3 bg-gray-50 dark:bg-gray-700/50 border-b border-gray-200 dark:border-gray-700 flex items-center justify-between">
                                            <h4
                                                class="text-sm font-medium text-gray-900 dark:text-gray-100 flex items-center">
                                                <svg class="w-4 h-4 mr-2 text-indigo-500" fill="none" stroke="currentColor"
                                                    viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                        d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z">
                                                    </path>
                                                </svg>
                                                Network Configuration
                                            </h4>
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 text-indigo-800 dark:bg-indigo-900 dark:text-indigo-200">
                                                Detected
                                            </span>
                                        </div>
                                        <div class="p-4">
                                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                                <div class="bg-gray-50 dark:bg-gray-700/30 p-3 rounded-md">
                                                    <span
                                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subnet</span>
                                                    <span
                                                        class="block text-sm font-semibold text-gray-900 dark:text-gray-100 mt-1"
                                                        x-text="calculateRange()?.subnet || 'N/A'"></span>
                                                </div>
                                                <div class="bg-gray-50 dark:bg-gray-700/30 p-3 rounded-md">
                                                    <span
                                                        class="block text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Subnet
                                                        Mask</span>
                                                    <span
                                                        class="block text-sm font-semibold text-gray-900 dark:text-gray-100 mt-1"
                                                        x-text="calculateRange()?.mask || 'N/A'"></span>
                                                </div>
                                                <div
                                                    class="bg-indigo-50 dark:bg-indigo-900/20 p-3 rounded-md border border-indigo-100 dark:border-indigo-800">
                                                    <span
                                                        class="block text-xs font-medium text-indigo-600 dark:text-indigo-400 uppercase tracking-wider">Suggested
                                                        Range</span>
                                                    <span
                                                        class="block text-sm font-semibold text-indigo-900 dark:text-indigo-100 mt-1"
                                                        x-text="calculateRange()?.start + ' - ' + calculateRange()?.end || 'N/A'"></span>
                                                </div>
                                            </div>

                                            <div class="flex justify-end">
                                                <button type="button" @click="applyRange()"
                                                    class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors duration-150">
                                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M5 13l4 4L19 7"></path>
                                                    </svg>
                                                    Apply Suggested Range
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                @endif
                            </div>

                            <div>
                                <label for="range_from" class="pf-label">Range From</label>
                                <x-text-input type="text" name="range_from" id="range_from"
                                    value="{{ $selectedConfig['range']['from'] ?? '' }}" class="w-full" />
                            </div>

                            <div>
                                <label for="range_to" class="pf-label">Range To</label>
                                <x-text-input type="text" name="range_to" id="range_to"
                                    value="{{ $selectedConfig['range']['to'] ?? '' }}" class="w-full" />
                            </div>

                            {{-- Servers --}}
                            <div class="pf-form-field-full">
                                <h3 class="pf-section-header">Servers</h3>
                            </div>

                            <div>
                                <label class="pf-label">DNS Servers</label>
                                <div class="space-y-2">
                                    <x-text-input type="text" name="dns1"
                                        value="{{ $selectedConfig['dnsserver'][0] ?? '' }}" class="w-full"
                                        placeholder="DNS 1" />
                                    <x-text-input type="text" name="dns2"
                                        value="{{ $selectedConfig['dnsserver'][1] ?? '' }}" class="w-full"
                                        placeholder="DNS 2" />
                                    <x-text-input type="text" name="dns3"
                                        value="{{ $selectedConfig['dnsserver'][2] ?? '' }}" class="w-full"
                                        placeholder="DNS 3" />
                                    <x-text-input type="text" name="dns4"
                                        value="{{ $selectedConfig['dnsserver'][3] ?? '' }}" class="w-full"
                                        placeholder="DNS 4" />
                                </div>
                            </div>

                            <div>
                                <label class="pf-label">Gateway</label>
                                <x-text-input type="text" name="gateway" value="{{ $selectedConfig['gateway'] ?? '' }}"
                                    class="w-full" placeholder="Gateway IP" />
                                <p class="pf-help-text">Leave blank to use the interface IP.</p>

                                <label class="pf-label mt-4">Domain Name</label>
                                <x-text-input type="text" name="domain" value="{{ $selectedConfig['domain'] ?? '' }}"
                                    class="w-full" />
                            </div>

                            {{-- Lease Time --}}
                            <div class="pf-form-field-full">
                                <h3 class="pf-section-header">Lease Time</h3>
                            </div>

                            <div>
                                <label class="pf-label">Default Lease Time</label>
                                <x-text-input type="number" name="defaultleasetime"
                                    value="{{ $selectedConfig['defaultleasetime'] ?? 7200 }}" class="w-full" />
                                <p class="pf-help-text">Seconds (Default: 7200)</p>
                            </div>

                            <div>
                                <label class="pf-label">Maximum Lease Time</label>
                                <x-text-input type="number" name="maxleasetime"
                                    value="{{ $selectedConfig['maxleasetime'] ?? 86400 }}" class="w-full" />
                                <p class="pf-help-text">Seconds (Default: 86400)</p>
                            </div>

                            {{-- Network Booting --}}
                            <div class="pf-form-field-full">
                                <h3 class="pf-section-header">Network Booting</h3>
                            </div>

                            <div>
                                <label class="pf-label">Next Server</label>
                                <x-text-input type="text" name="next_server"
                                    value="{{ $selectedConfig['nextserver'] ?? '' }}" class="w-full"
                                    placeholder="IP Address" />
                            </div>

                            <div>
                                <label class="pf-label">TFTP Server</label>
                                <x-text-input type="text" name="tftp" value="{{ $selectedConfig['tftp'] ?? '' }}"
                                    class="w-full" placeholder="Hostname or IP" />
                            </div>
                        </div>

                        </fieldset>
                        <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            @if(!auth()->user()->isReadOnly())
                            <button type="submit" class="btn-primary">
                                Save
                            </button>
                            @endif
                        </div>
                    </form>
            </div>
        </div>
    </div>
    </div>
</x-app-layout>
