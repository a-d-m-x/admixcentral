<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('DNS Resolver') }}" :firewall="$firewall" />
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
                    
                    {{-- Tabs --}}
                    <div class="border-b border-gray-200 dark:border-gray-700 mb-6">
                        <nav class="-mb-px flex space-x-8 overflow-x-auto">
                            <a href="{{ route('services.dns.resolver', $firewall) }}"
                               class="border-indigo-500 text-indigo-600 dark:text-indigo-400 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                General Settings
                            </a>
                            <a href="{{ route('services.dns.host-overrides', $firewall) }}"
                               class="border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                Host Overrides
                            </a>
                        </nav>
                    </div>

                    <form action="{{ route('services.dns.resolver.update', $firewall) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <fieldset @if(auth()->user()->isReadOnly()) disabled @endif class="[&:disabled]:opacity-60 [&:disabled]:pointer-events-none">

                        <div class="pf-form-container">
                            {{-- Enable --}}
                            <div class="mb-6">
                                <div class="flex items-center">
                                    <input type="checkbox" name="enable" id="enable" 
                                           class="pf-checkbox"
                                           {{ !empty($config['enable']) ? 'checked' : '' }}>
                                    <label for="enable" class="ml-2 block text-sm font-medium text-gray-900 dark:text-gray-100">
                                        Enable DNS Resolver
                                    </label>
                                </div>
                            </div>

                            <div class="pf-form-grid">
                                {{-- Network --}}
                                <div class="pf-form-field-full">
                                    <h3 class="pf-section-header">Network</h3>
                                </div>

                                <div>
                                    <label for="port" class="pf-label">Listen Port</label>
                                    <input type="number" name="port" id="port" 
                                           value="{{ $config['port'] ?? 53 }}"
                                           class="pf-input">
                                </div>

                                {{-- Options --}}
                                <div class="pf-form-field-full">
                                    <h3 class="pf-section-header">System</h3>
                                </div>

                                <div class="pf-form-field-full space-y-4">
                                    <div class="flex items-center">
                                        <input type="checkbox" name="dnssec" id="dnssec" 
                                               class="pf-checkbox"
                                               {{ !empty($config['dnssec']) ? 'checked' : '' }}>
                                        <label for="dnssec" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                            Enable DNSSEC Support
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="forwarding" id="forwarding" 
                                               class="pf-checkbox"
                                               {{ !empty($config['forwarding']) ? 'checked' : '' }}>
                                        <label for="forwarding" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                            Enable Forwarding Mode (Forward to System DNS Servers)
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="regdhcp" id="regdhcp" 
                                               class="pf-checkbox"
                                               {{ !empty($config['regdhcp']) ? 'checked' : '' }}>
                                        <label for="regdhcp" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                            Register DHCP leases in the DNS Resolver
                                        </label>
                                    </div>

                                    <div class="flex items-center">
                                        <input type="checkbox" name="regdhcpstatic" id="regdhcpstatic" 
                                               class="pf-checkbox"
                                               {{ !empty($config['regdhcpstatic']) ? 'checked' : '' }}>
                                        <label for="regdhcpstatic" class="ml-2 block text-sm text-gray-900 dark:text-gray-100">
                                            Register DHCP static mappings in the DNS Resolver
                                        </label>
                                    </div>
                                </div>

                                {{-- Custom Options --}}
                                <div class="pf-form-field-full">
                                    <h3 class="pf-section-header">Custom Options</h3>
                                    <textarea name="custom_options" rows="4" class="pf-input font-mono">{{ $config['custom_options'] ?? '' }}</textarea>
                                </div>
                            </div>

                            </fieldset>
                            <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                                @if(!auth()->user()->isReadOnly())
                                <button type="submit" class="pf-btn pf-btn-primary">
                                    Save
                                </button>
                                @endif
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
