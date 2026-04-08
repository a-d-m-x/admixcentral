<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System: General Setup') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('success'))
                        <div class="alert alert-success mb-4" role="alert">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="alert alert-danger mb-4" role="alert">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('system.general-setup.update', $firewall) }}">
                        @csrf
                        <fieldset @if(auth()->user()->isReadOnly()) disabled @endif class="[&:disabled]:opacity-60 [&:disabled]:pointer-events-none">

                        <div class="mb-3">
                            <label for="hostname"
                                class="form-label block font-medium text-sm text-gray-700 dark:text-gray-300">Hostname</label>
                            <input type="text"
                                class="form-control mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                id="hostname" name="hostname"
                                value="{{ old('hostname', $hostname['hostname'] ?? '') }}">
                        </div>

                        <div class="mb-3">
                            <label for="domain"
                                class="form-label block font-medium text-sm text-gray-700 dark:text-gray-300">Domain</label>
                            <input type="text"
                                class="form-control mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                id="domain" name="domain" value="{{ old('domain', $hostname['domain'] ?? '') }}">
                        </div>

                        <div class="mb-3">
                            <label for="timezone"
                                class="form-label block font-medium text-sm text-gray-700 dark:text-gray-300">Timezone</label>
                            <select
                                class="form-control mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                id="timezone" name="timezone">
                                @foreach ($timezones as $tz)
                                    <option value="{{ $tz }}" {{ old('timezone', $timezone['timezone'] ?? '') == $tz ? 'selected' : '' }}>
                                        {{ $tz }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <hr class="my-4 border-gray-300 dark:border-gray-700">
                        <h5 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">DNS Server Settings</h5>

                        @php
                            $dnsServers = $dns['dnsserver'] ?? [];
                            if (!is_array($dnsServers)) {
                                $dnsServers = [$dnsServers];
                            }
                        @endphp

                        @for ($i = 0; $i < 4; $i++)
                            <div class="mb-3">
                                <label for="dnsserver_{{ $i }}"
                                    class="form-label block font-medium text-sm text-gray-700 dark:text-gray-300">DNS Server
                                    {{ $i + 1 }}</label>
                                <input type="text"
                                    class="form-control mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                    id="dnsserver_{{ $i }}" name="dnsserver[]"
                                    value="{{ old('dnsserver.' . $i, $dnsServers[$i] ?? '') }}">
                            </div>
                        @endfor

                        <div class="mb-3 form-check flex items-center">
                            <input type="checkbox"
                                class="form-check-input rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:bg-gray-900"
                                id="dnsallowoverride" name="dnsallowoverride" {{ old('dnsallowoverride', $dns['dnsallowoverride'] ?? false) ? 'checked' : '' }}>
                            <label class="form-check-label ml-2 block text-sm text-gray-900 dark:text-gray-100"
                                for="dnsallowoverride">Allow DNS Server list to be overridden by DHCP/PPP on WAN</label>
                        </div>

                        <div class="mb-3 form-check flex items-center">
                            <input type="checkbox"
                                class="form-check-input rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:bg-gray-900"
                                id="dnslocalhost" name="dnslocalhost" {{ old('dnslocalhost', ($dns['dnslocalhost'] ?? '') === 'remote') ? 'checked' : '' }}>
                            <label class="form-check-label ml-2 block text-sm text-gray-900 dark:text-gray-100"
                                for="dnslocalhost">Do not use the DNS Forwarder/DNS Resolver as a DNS server for the
                                firewall</label>
                        </div>

                        </fieldset>
                        @if(!auth()->user()->isReadOnly())
                        <button type="submit"
                            class="btn btn-primary inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">Save</button>
                        @endif
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
