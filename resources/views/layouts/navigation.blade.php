@php
    // Reusable nav dropdown item class helpers
    $ni  = 'block px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700';
    $nia = 'block px-4 py-2 text-sm bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700 dark:text-indigo-300 font-medium';
    // Active dropdown button state
    $btnActive = 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100';
    $btnInactive = 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700';
@endphp
<nav x-data="{ open: false, systemOpen: false, firewallOpen: false, servicesOpen: false, vpnOpen: false, statusOpen: false, diagnosticsOpen: false, systemActive: {{ request()->routeIs('system.*') || request()->routeIs('firewall.system.*') ? 'true' : 'false' }}, firewallActive: {{ request()->routeIs('firewall.aliases.*') || request()->routeIs('firewall.categories.*') || request()->routeIs('firewall.nat.*') || request()->routeIs('firewall.rules.*') || request()->routeIs('firewall.schedules.*') || request()->routeIs('firewall.limiters.*') || request()->routeIs('firewall.virtual_ips.*') ? 'true' : 'false' }}, servicesActive: {{ request()->routeIs('services.*') ? 'true' : 'false' }}, vpnActive: {{ request()->routeIs('vpn.*') ? 'true' : 'false' }}, statusActive: {{ request()->routeIs('status.*') ? 'true' : 'false' }}, diagnosticsActive: {{ request()->routeIs('diagnostics.*') ? 'true' : 'false' }} }"
    class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-full mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-12 sm:h-16">
            <div class="flex">
                <!-- Logo - Removed as per user request -->
                <!--
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>
                -->

                <!-- Navigation Centered Group -->
                <div class="hidden space-x-8 sm:flex flex-1 justify-center items-center">

                    @if(request()->route('firewall'))
                        <!-- Separator removed as per user request -->
                    @endif
                    @if(request()->route('firewall'))
                        @php
                            $navFirewall = isset($firewall) && $firewall instanceof \App\Models\Firewall 
                                ? $firewall 
                                : (request()->route('firewall') instanceof \App\Models\Firewall 
                                    ? request()->route('firewall') 
                                    : (request()->route('firewall') ? \App\Models\Firewall::find(request()->route('firewall')) : null));
                            $isOpnSense = $navFirewall ? $navFirewall->isOpnSense() : false;
                        @endphp
                        <!-- pfSense-Style Dropdowns (only when managing a firewall) -->

                        <a href="{{ route('firewall.dashboard', request()->route('firewall')) }}"
                            class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out {{ request()->routeIs('firewall.dashboard') ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700' }}">
                            Dashboard
                        </a>

                        <!-- System Dropdown -->
                        <div class="relative" @click.away="systemOpen = false">
                            <button @click="systemOpen = !systemOpen"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                :class="systemActive ? '{{ $btnActive }}' : '{{ $btnInactive }}'">
                                System
                                <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="systemOpen" style="display: none;"
                                class="absolute z-10 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <a href="{{ route('system.advanced', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.advanced') ? $nia : $ni }}">Advanced</a>
                                    <a href="{{ route('system.certificate_manager.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.certificate_manager.*') ? $nia : $ni }}">{{ $isOpnSense ? 'Trust / Certs' : 'Cert. Manager' }}</a>
                                    @if($isOpnSense)
                                    <a href="{{ route('system.cron', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.cron') ? $nia : $ni }}">Cron</a>
                                    @endif
                                    <a href="{{ route('system.general-setup', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.general-setup') ? $nia : $ni }}">General Setup</a>
                                    <a href="{{ route('system.high-avail-sync', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.high-avail-sync') ? $nia : $ni }}">High Avail. Sync</a>
                                    <a href="{{ route('system.package_manager.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.package_manager.*') ? $nia : $ni }}">{{ $isOpnSense ? 'Plugins & Packages' : 'Package Manager' }}</a>
                                    <a href="{{ route('firewall.system.routing', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.system.routing*') ? $nia : $ni }}">Routing</a>
                                    <a href="{{ route('system.update', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.update') ? $nia : $ni }}">{{ $isOpnSense ? 'Firmware' : 'Update' }}</a>
                                    @if(!$isOpnSense && !auth()->user()->isReadOnly())
                                    <a href="{{ route('system.rest-api.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.rest-api.*') ? $nia : $ni }}">Update REST API</a>
                                    @endif
                                    <a href="{{ route('system.user_manager.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('system.user_manager.*') ? $nia : $ni }}">User Manager</a>
                                </div>
                            </div>
                        </div>

                        <!-- Interfaces Dropdown -->
                        <div class="relative" x-data="{ interfacesOpen: false }" @click.away="interfacesOpen = false">
                            <button @click="interfacesOpen = !interfacesOpen"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                :class="{{ request()->routeIs('firewall.interfaces.*') || request()->routeIs('interfaces.*') ? 'true' : 'false' }} ? '{{ $btnActive }}' : '{{ $btnInactive }}'">
                                Interfaces
                                <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="interfacesOpen"
                                class="absolute z-10 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <a href="{{ route('firewall.interfaces.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('firewall.interfaces.index') ? $nia : $ni }}">Overview</a>
                                    <a href="{{ route('interfaces.assignments', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.assignments') ? $nia : $ni }}">Assignments</a>
                                    <a href="{{ route('interfaces.bridges.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.bridges.*') ? $nia : $ni }}">Bridges</a>
                                    <a href="{{ route('interfaces.gre.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.gre.*') ? $nia : $ni }}">GRE</a>
                                    <a href="{{ route('interfaces.groups.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.groups.*') ? $nia : $ni }}">Groups</a>
                                    <a href="{{ route('interfaces.laggs.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.laggs.*') ? $nia : $ni }}">LAGGs</a>
                                    @if($isOpnSense)
                                    <a href="{{ route('interfaces.loopbacks.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.loopbacks.*') ? $nia : $ni }}">Loopback</a>
                                    <a href="{{ route('interfaces.vxlans.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.vxlans.*') ? $nia : $ni }}">VXLAN</a>
                                    @endif
                                    <a href="{{ route('interfaces.vlans.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.vlans.*') ? $nia : $ni }}">VLANs</a>
                                    <a href="{{ route('interfaces.wireless.index', [request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('interfaces.wireless.*') ? $nia : $ni }}">Wireless</a>
                                </div>
                            </div>
                        </div>

                        <!-- Firewall Dropdown -->
                        <div class="relative" @click.away="firewallOpen = false">
                            <button @click="firewallOpen = !firewallOpen"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                :class="firewallActive ? '{{ $btnActive }}' : '{{ $btnInactive }}'">
                                Firewall
                                <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="firewallOpen" style="display: none;"
                                class="absolute z-10 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <a href="{{ route('firewall.aliases.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.aliases.*') ? $nia : $ni }} pl-6">Aliases</a>
                                    @if($isOpnSense)
                                    <a href="{{ route('firewall.categories.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.categories.*') ? $nia : $ni }} pl-6">Categories</a>
                                    @endif
                                    <a href="{{ route('firewall.nat.port-forward', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.nat.*') ? $nia : $ni }} pl-6">NAT</a>
                                    <a href="{{ route('firewall.rules.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.rules.*') ? $nia : $ni }} pl-6">Rules</a>
                                    <a href="{{ route('firewall.schedules.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.schedules.*') ? $nia : $ni }} pl-6">Schedules</a>
                                    <a href="{{ route('firewall.limiters.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.limiters.*') ? $nia : $ni }} pl-6">Traffic Shaper</a>
                                    <a href="{{ route('firewall.virtual_ips.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('firewall.virtual_ips.*') ? $nia : $ni }} pl-6">Virtual IPs</a>
                                </div>
                            </div>
                        </div>

                        <!-- Services Dropdown -->
                        <div class="relative" @click.away="servicesOpen = false">
                            <button @click="servicesOpen = !servicesOpen"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                :class="servicesActive ? '{{ $btnActive }}' : '{{ $btnInactive }}'">
                                Services
                                <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="servicesOpen" style="display: none;"
                                class="absolute z-10 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <a href="{{ route('services.acme.certificates', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.acme.*') ? $nia : $ni }}">ACME Certificates</a>
                                    <a href="{{ route('services.auto-config-backup', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.auto-config-backup') ? $nia : $ni }}">Auto Config Backup</a>
                                    <a href="{{ route('services.captive-portal', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.captive-portal') ? $nia : $ni }}">Captive Portal</a>
                                    <a href="{{ route('services.dhcp-relay', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.dhcp-relay') ? $nia : $ni }}">DHCP Relay</a>
                                    <a href="{{ route('services.dhcp.index', ['firewall' => request()->route('firewall')]) }}"
                                        class="{{ request()->routeIs('services.dhcp.*') ? $nia : $ni }}">DHCP Server</a>
                                    <a href="{{ route('services.dhcpv6-relay', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.dhcpv6-relay') ? $nia : $ni }}">DHCPv6 Relay</a>
                                    <a href="{{ route('services.dhcpv6-server', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.dhcpv6-server') ? $nia : $ni }}">DHCPv6 Server</a>
                                    <a href="{{ route('services.dns-forwarder', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.dns-forwarder') ? $nia : $ni }}">DNS Forwarder</a>
                                    <a href="{{ route('services.dns.resolver', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.dns.*') ? $nia : $ni }}">DNS Resolver</a>
                                    <a href="{{ route('services.dynamic-dns', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.dynamic-dns') ? $nia : $ni }}">Dynamic DNS</a>
                                    <a href="{{ route('services.igmp-proxy', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.igmp-proxy') ? $nia : $ni }}">IGMP Proxy</a>
                                    <a href="{{ route('services.ntp', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.ntp') ? $nia : $ni }}">NTP</a>
                                    <a href="{{ route('services.pppoe-server', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.pppoe-server') ? $nia : $ni }}">PPPoE Server</a>
                                    <a href="{{ route('services.router-advertisement', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.router-advertisement') ? $nia : $ni }}">Router Advertisement</a>
                                    <a href="{{ route('services.snmp', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.snmp') ? $nia : $ni }}">SNMP</a>
                                    <a href="{{ route('services.upnp', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.upnp') ? $nia : $ni }}">UPnP IGD & PCP</a>
                                    <a href="{{ route('services.wake-on-lan', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('services.wake-on-lan') ? $nia : $ni }}">Wake-on-LAN</a>
                                    <a href="{{ route('vpn.wireguard.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('vpn.wireguard.*') ? $nia : $ni }}">WireGuard</a>
                                    @if($isOpnSense)
                                        <a href="{{ route('services.ids.index', request()->route('firewall')) }}"
                                            class="{{ request()->routeIs('services.ids.*') ? $nia : $ni }}">Intrusion Detection (IDS)</a>
                                        <a href="{{ route('services.monit.index', request()->route('firewall')) }}"
                                            class="{{ request()->routeIs('services.monit.*') ? $nia : $ni }}">Monit</a>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- VPN Dropdown -->
                        <div class="relative" @click.away="vpnOpen = false">
                            <button @click="vpnOpen = !vpnOpen"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                :class="vpnActive ? '{{ $btnActive }}' : '{{ $btnInactive }}'">
                                VPN
                                <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="vpnOpen" style="display: none;"
                                class="absolute z-10 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <a href="{{ route('vpn.ipsec', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('vpn.ipsec') ? $nia : $ni }}">IPsec</a>
                                    <a href="{{ route('vpn.l2tp', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('vpn.l2tp') ? $nia : $ni }}">L2TP</a>
                                    <a href="{{ route('vpn.openvpn.servers', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('vpn.openvpn.*') ? $nia : $ni }}">OpenVPN</a>
                                    <a href="{{ route('vpn.wireguard.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('vpn.wireguard.*') ? $nia : $ni }}">WireGuard</a>
                                </div>
                            </div>
                        </div>

                        <!-- Status Dropdown -->
                        <div class="relative" @click.away="statusOpen = false">
                            <button @click="statusOpen = !statusOpen"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                :class="statusActive ? '{{ $btnActive }}' : '{{ $btnInactive }}'">
                                Status
                                <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="statusOpen" style="display: none;"
                                class="absolute z-10 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <a href="{{ route('status.captive-portal', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.captive-portal') ? $nia : $ni }}">Captive Portal</a>
                                    <a href="{{ route('status.carp', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.carp') ? $nia : $ni }}">CARP (failover)</a>
                                    <a href="{{ route('status.dashboard', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.dashboard') ? $nia : $ni }}">Dashboard</a>
                                    <a href="{{ route('status.dhcp-leases', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.dhcp-leases') ? $nia : $ni }}">DHCP Leases</a>
                                    <a href="{{ route('status.dhcpv6-leases', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.dhcpv6-leases') ? $nia : $ni }}">DHCPv6 Leases</a>
                                    <a href="{{ route('status.filter-reload', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.filter-reload') ? $nia : $ni }}">Filter Reload</a>
                                    <a href="{{ route('status.gateways', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.gateways') ? $nia : $ni }}">Gateways</a>
                                    <a href="{{ route('status.interfaces.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.interfaces.*') ? $nia : $ni }}">Interfaces</a>
                                    <a href="{{ route('status.ipsec', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.ipsec') ? $nia : $ni }}">IPsec</a>
                                    <a href="{{ route('status.monitoring', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.monitoring') ? $nia : $ni }}">Monitoring</a>
                                    <a href="{{ route('status.ntp', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.ntp') ? $nia : $ni }}">NTP</a>
                                    <a href="{{ route('status.openvpn', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.openvpn') ? $nia : $ni }}">OpenVPN</a>
                                    <a href="{{ route('status.queues', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.queues') ? $nia : $ni }}">Queues</a>
                                    <a href="{{ route('status.services', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.services') ? $nia : $ni }}">Services</a>
                                    <a href="{{ route('status.system-logs', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.system-logs') ? $nia : $ni }}">System Logs</a>
                                    <a href="{{ route('status.traffic-graph', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.traffic-graph') ? $nia : $ni }}">Traffic Graph</a>
                                    <a href="{{ route('status.upnp', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('status.upnp') ? $nia : $ni }}">UPnP IGD & PCP</a>
                                </div>
                            </div>
                        </div>

                        <!-- Diagnostics Dropdown -->
                        <div class="relative" @click.away="diagnosticsOpen = false">
                            <button @click="diagnosticsOpen = !diagnosticsOpen"
                                class="inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium leading-5 transition duration-150 ease-in-out"
                                :class="diagnosticsActive ? '{{ $btnActive }}' : '{{ $btnInactive }}'">
                                Diagnostics
                                <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"
                                    fill="currentColor">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </button>
                            <div x-show="diagnosticsOpen" style="display: none;"
                                class="absolute z-10 mt-2 w-56 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5">
                                <div class="py-1" role="menu">
                                    <a href="{{ route('diagnostics.arp-table', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.arp-table') ? $nia : $ni }}">ARP Table</a>
                                    <a href="{{ route('diagnostics.authentication', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.authentication') ? $nia : $ni }}">Authentication</a>
                                    <a href="{{ route('diagnostics.backup.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.backup.*') ? $nia : $ni }}">Backup & Restore</a>
                                    <a href="{{ route('diagnostics.command-prompt', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.command-prompt') ? $nia : $ni }}">Command Prompt</a>
                                    <a href="{{ route('diagnostics.dns-lookup', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.dns-lookup') ? $nia : $ni }}">DNS Lookup</a>
                                    <a href="{{ route('diagnostics.edit-file', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.edit-file') ? $nia : $ni }}">Edit File</a>
                                    <a href="{{ route('diagnostics.factory-defaults', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.factory-defaults') ? $nia : $ni }}">Factory Defaults</a>
                                    @if(!auth()->user()->isReadOnly())
                                    <a href="{{ route('diagnostics.halt-system', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.halt-system') ? $nia : $ni }}">Halt System</a>
                                    @endif
                                    @if(!$isOpnSense)
                                    <a href="{{ route('diagnostics.limiter-info', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.limiter-info') ? $nia : $ni }}">Limiter Info</a>
                                    @endif
                                    <a href="{{ route('diagnostics.ndp-table', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.ndp-table') ? $nia : $ni }}">NDP Table</a>
                                    <a href="{{ route('diagnostics.packet_capture.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.packet_capture.*') ? $nia : $ni }}">Packet Capture</a>
                                    <a href="{{ route('diagnostics.pf-info', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.pf-info') ? $nia : $ni }}">pfInfo</a>
                                    <a href="{{ route('diagnostics.pf-top', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.pf-top') ? $nia : $ni }}">pfTop</a>
                                    <a href="{{ route('diagnostics.ping', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.ping') ? $nia : $ni }}">Ping</a>
                                    @if(!auth()->user()->isReadOnly())
                                    <a href="{{ route('diagnostics.reboot.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.reboot.*') ? $nia : $ni }}">Reboot</a>
                                    @endif
                                    <a href="{{ route('diagnostics.routes', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.routes') ? $nia : $ni }}">Routes</a>
                                    <a href="{{ route('diagnostics.smart-status', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.smart-status') ? $nia : $ni }}">S.M.A.R.T. Status</a>
                                    <a href="{{ route('diagnostics.sockets', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.sockets') ? $nia : $ni }}">Sockets</a>
                                    <a href="{{ route('diagnostics.states', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.states') ? $nia : $ni }}">States</a>
                                    <a href="{{ route('diagnostics.states-summary', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.states-summary') ? $nia : $ni }}">States Summary</a>
                                    <a href="{{ route('diagnostics.system-activity', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.system-activity') ? $nia : $ni }}">System Activity</a>
                                    <a href="{{ route('diagnostics.tables', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.tables') ? $nia : $ni }}">Tables</a>
                                    <a href="{{ route('diagnostics.test_port.index', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.test_port.*') ? $nia : $ni }}">Test Port</a>
                                    <a href="{{ route('diagnostics.traceroute', request()->route('firewall')) }}"
                                        class="{{ request()->routeIs('diagnostics.traceroute') ? $nia : $ni }}">Traceroute</a>
                                </div>
                            </div>
                        </div>

                    @endif
                    <!-- End pfSense-Style Dropdowns -->
                </div>
            </div>



            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <!-- Firewall Dashboard -->
            <x-responsive-nav-link :href="route('firewall.dashboard', request()->route('firewall'))"
                :active="request()->routeIs('firewall.dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            <!-- System -->
            <div
                x-data="{ expanded: {{ request()->routeIs('system.*') || request()->routeIs('firewall.system.*') ? 'true' : 'false' }} }">
                <button @click="expanded = !expanded"
                    class="flex items-center justify-between w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out">
                    <span>System</span>
                    <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': expanded}"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-collapse class="space-y-1 pl-4 bg-gray-50 dark:bg-gray-900/50">
                    <x-responsive-nav-link :href="route('system.advanced', request()->route('firewall'))"
                        :active="request()->routeIs('system.advanced')">{{ __('Advanced') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('system.certificate_manager.index', request()->route('firewall'))"
                        :active="request()->routeIs('system.certificate_manager.*')">{{ $isOpnSense ? __('Trust / Certs') : __('Cert. Manager') }}</x-responsive-nav-link>
                    @if($isOpnSense)
                    <x-responsive-nav-link :href="route('system.cron', request()->route('firewall'))"
                        :active="request()->routeIs('system.cron*') || request()->routeIs('firewall.system.cron*')">{{ __('Cron') }}</x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('system.general-setup', request()->route('firewall'))"
                        :active="request()->routeIs('system.general-setup')">{{ __('General Setup') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('system.high-avail-sync', request()->route('firewall'))"
                        :active="request()->routeIs('system.high-avail-sync')">{{ __('High Avail. Sync') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('system.package_manager.index', request()->route('firewall'))"
                        :active="request()->routeIs('system.package_manager.*')">{{ $isOpnSense ? __('Plugins & Packages') : __('Package Manager') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('firewall.system.routing', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.system.routing')">{{ __('Routing') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('system.update', request()->route('firewall'))"
                        :active="request()->routeIs('system.update')">{{ $isOpnSense ? __('Firmware') : __('Update') }}</x-responsive-nav-link>
                    @if(!$isOpnSense && !auth()->user()->isReadOnly())
                    <x-responsive-nav-link :href="route('system.rest-api.index', request()->route('firewall'))"
                        :active="request()->routeIs('system.rest-api.*')">{{ __('Update REST API') }}</x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('system.user_manager.index', request()->route('firewall'))"
                        :active="request()->routeIs('system.user_manager.*')">{{ __('User Manager') }}</x-responsive-nav-link>
                </div>
            </div>

            <!-- Interfaces -->
            <div
                x-data="{ expanded: {{ request()->routeIs('firewall.interfaces.*') || request()->routeIs('firewall.vlans.*') || request()->routeIs('interfaces.*') ? 'true' : 'false' }} }">
                <button @click="expanded = !expanded"
                    class="flex items-center justify-between w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out">
                    <span>Interfaces</span>
                    <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': expanded}"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-collapse class="space-y-1 pl-4 bg-gray-50 dark:bg-gray-900/50">
                    <x-responsive-nav-link :href="route('firewall.interfaces.index', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.interfaces.index')">{{ __('Overview') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('interfaces.assignments', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.assignments')">{{ __('Assignments') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('interfaces.bridges.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.bridges.*')">{{ __('Bridges') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('interfaces.gre.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.gre.*')">{{ __('GRE') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('interfaces.groups.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.groups.*')">{{ __('Groups') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('interfaces.laggs.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.laggs.*')">{{ __('LAGGs') }}</x-responsive-nav-link>
                    @if($isOpnSense)
                    <x-responsive-nav-link :href="route('interfaces.loopbacks.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.loopbacks.*')">{{ __('Loopback') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('interfaces.vxlans.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.vxlans.*')">{{ __('VXLAN') }}</x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('interfaces.vlans.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.vlans.*')">{{ __('VLANs') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('interfaces.wireless.index', request()->route('firewall'))"
                        :active="request()->routeIs('interfaces.wireless.*')">{{ __('Wireless') }}</x-responsive-nav-link>
                </div>
            </div>

            <!-- Firewall -->
            <div
                x-data="{ expanded: {{ request()->routeIs('firewall.aliases.*') || request()->routeIs('firewall.categories.*') || request()->routeIs('firewall.nat.*') || request()->routeIs('firewall.rules.*') || request()->routeIs('firewall.schedules.*') || request()->routeIs('firewall.limiters.*') || request()->routeIs('firewall.virtual_ips.*') ? 'true' : 'false' }} }">
                <button @click="expanded = !expanded"
                    class="flex items-center justify-between w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out">
                    <span>Firewall</span>
                    <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': expanded}"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-collapse class="space-y-1 pl-4 bg-gray-50 dark:bg-gray-900/50">
                    <x-responsive-nav-link :href="route('firewall.aliases.index', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.aliases.*')">{{ __('Aliases') }}</x-responsive-nav-link>
                    @if($isOpnSense)
                    <x-responsive-nav-link :href="route('firewall.categories.index', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.categories.*')">{{ __('Categories') }}</x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('firewall.nat.port-forward', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.nat.*')">{{ __('NAT') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('firewall.rules.index', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.rules.*')">{{ __('Rules') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('firewall.schedules.index', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.schedules.*')">{{ __('Schedules') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('firewall.limiters.index', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.limiters.*')">{{ __('Traffic Shaper') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('firewall.virtual_ips.index', request()->route('firewall'))"
                        :active="request()->routeIs('firewall.virtual_ips.*')">{{ __('Virtual IPs') }}</x-responsive-nav-link>
                </div>
            </div>

            <!-- Services -->
            <div x-data="{ expanded: {{ request()->routeIs('services.*') ? 'true' : 'false' }} }">
                <button @click="expanded = !expanded"
                    class="flex items-center justify-between w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out">
                    <span>Services</span>
                    <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': expanded}"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-collapse class="space-y-1 pl-4 bg-gray-50 dark:bg-gray-900/50">
                    <x-responsive-nav-link :href="route('services.acme.certificates', request()->route('firewall'))"
                        :active="request()->routeIs('services.acme.*')">{{ __('ACME Certificates') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.auto-config-backup', request()->route('firewall'))"
                        :active="request()->routeIs('services.auto-config-backup')">{{ __('Auto Config Backup') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.captive-portal', request()->route('firewall'))"
                        :active="request()->routeIs('services.captive-portal')">{{ __('Captive Portal') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.dhcp-relay', request()->route('firewall'))"
                        :active="request()->routeIs('services.dhcp-relay')">{{ __('DHCP Relay') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.dhcp.index', ['firewall' => request()->route('firewall')])"
                        :active="request()->routeIs('services.dhcp.index')">{{ __('DHCP Server') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.dhcpv6-relay', request()->route('firewall'))"
                        :active="request()->routeIs('services.dhcpv6-relay')">{{ __('DHCPv6 Relay') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.dhcpv6-server', request()->route('firewall'))"
                        :active="request()->routeIs('services.dhcpv6-server')">{{ __('DHCPv6 Server') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.dns-forwarder', request()->route('firewall'))"
                        :active="request()->routeIs('services.dns-forwarder')">{{ __('DNS Forwarder') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.dns.resolver', request()->route('firewall'))"
                        :active="request()->routeIs('services.dns.resolver')">{{ __('DNS Resolver') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.dynamic-dns', request()->route('firewall'))"
                        :active="request()->routeIs('services.dynamic-dns')">{{ __('Dynamic DNS') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.igmp-proxy', request()->route('firewall'))"
                        :active="request()->routeIs('services.igmp-proxy')">{{ __('IGMP Proxy') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.ntp', request()->route('firewall'))"
                        :active="request()->routeIs('services.ntp')">{{ __('NTP') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.pppoe-server', request()->route('firewall'))"
                        :active="request()->routeIs('services.pppoe-server')">{{ __('PPPoE Server') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.router-advertisement', request()->route('firewall'))"
                        :active="request()->routeIs('services.router-advertisement')">{{ __('Router Advertisement') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.snmp', request()->route('firewall'))"
                        :active="request()->routeIs('services.snmp')">{{ __('SNMP') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.upnp', request()->route('firewall'))"
                        :active="request()->routeIs('services.upnp')">{{ __('UPnP IGD & PCP') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('services.wake-on-lan', request()->route('firewall'))"
                        :active="request()->routeIs('services.wake-on-lan')">{{ __('Wake-on-LAN') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('vpn.wireguard.index', request()->route('firewall'))"
                        :active="request()->routeIs('vpn.wireguard.*')">{{ __('WireGuard') }}</x-responsive-nav-link>
                    @if($isOpnSense)
                        <x-responsive-nav-link :href="route('services.ids.index', request()->route('firewall'))"
                            :active="request()->routeIs('services.ids.*')">{{ __('Intrusion Detection (IDS)') }}</x-responsive-nav-link>
                        <x-responsive-nav-link :href="route('services.monit.index', request()->route('firewall'))"
                            :active="request()->routeIs('services.monit.*')">{{ __('Monit') }}</x-responsive-nav-link>
                    @endif
                </div>
            </div>

            <!-- VPN -->
            <div x-data="{ expanded: {{ request()->routeIs('vpn.*') ? 'true' : 'false' }} }">
                <button @click="expanded = !expanded"
                    class="flex items-center justify-between w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out">
                    <span>VPN</span>
                    <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': expanded}"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-collapse class="space-y-1 pl-4 bg-gray-50 dark:bg-gray-900/50">
                    <x-responsive-nav-link :href="route('vpn.ipsec', request()->route('firewall'))"
                        :active="request()->routeIs('vpn.ipsec')">{{ __('IPsec') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('vpn.l2tp', request()->route('firewall'))"
                        :active="request()->routeIs('vpn.l2tp')">{{ __('L2TP') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('vpn.openvpn.servers', request()->route('firewall'))"
                        :active="request()->routeIs('vpn.openvpn.*')">{{ __('OpenVPN') }}</x-responsive-nav-link>
                    <!-- WireGuard already in Services, but also in VPN in desktop menu? Desktop has it in both or one? Desktop has WireGuard in Services AND VPN? Checking lines 223 and 255. Yes both. -->
                    <x-responsive-nav-link :href="route('vpn.wireguard.index', request()->route('firewall'))"
                        :active="request()->routeIs('vpn.wireguard.*')">{{ __('WireGuard') }}</x-responsive-nav-link>
                </div>
            </div>

            <!-- Status -->
            <div x-data="{ expanded: {{ request()->routeIs('status.*') ? 'true' : 'false' }} }">
                <button @click="expanded = !expanded"
                    class="flex items-center justify-between w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out">
                    <span>Status</span>
                    <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': expanded}"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-collapse class="space-y-1 pl-4 bg-gray-50 dark:bg-gray-900/50">
                    <x-responsive-nav-link :href="route('status.captive-portal', request()->route('firewall'))"
                        :active="request()->routeIs('status.captive-portal')">{{ __('Captive Portal') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.carp', request()->route('firewall'))"
                        :active="request()->routeIs('status.carp')">{{ __('CARP (failover)') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.dashboard', request()->route('firewall'))"
                        :active="request()->routeIs('status.dashboard')">{{ __('Dashboard') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.dhcp-leases', request()->route('firewall'))"
                        :active="request()->routeIs('status.dhcp-leases')">{{ __('DHCP Leases') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.dhcpv6-leases', request()->route('firewall'))"
                        :active="request()->routeIs('status.dhcpv6-leases')">{{ __('DHCPv6 Leases') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.filter-reload', request()->route('firewall'))"
                        :active="request()->routeIs('status.filter-reload')">{{ __('Filter Reload') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.gateways', request()->route('firewall'))"
                        :active="request()->routeIs('status.gateways')">{{ __('Gateways') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.interfaces.index', request()->route('firewall'))"
                        :active="request()->routeIs('status.interfaces.*')">{{ __('Interfaces') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.ipsec', request()->route('firewall'))"
                        :active="request()->routeIs('status.ipsec')">{{ __('IPsec') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.monitoring', request()->route('firewall'))"
                        :active="request()->routeIs('status.monitoring')">{{ __('Monitoring') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.ntp', request()->route('firewall'))"
                        :active="request()->routeIs('status.ntp')">{{ __('NTP') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.openvpn', request()->route('firewall'))"
                        :active="request()->routeIs('status.openvpn')">{{ __('OpenVPN') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.queues', request()->route('firewall'))"
                        :active="request()->routeIs('status.queues')">{{ __('Queues') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.services', request()->route('firewall'))"
                        :active="request()->routeIs('status.services')">{{ __('Services') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.system-logs', request()->route('firewall'))"
                        :active="request()->routeIs('status.system-logs')">{{ __('System Logs') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.traffic-graph', request()->route('firewall'))"
                        :active="request()->routeIs('status.traffic-graph')">{{ __('Traffic Graph') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('status.upnp', request()->route('firewall'))"
                        :active="request()->routeIs('status.upnp')">{{ __('UPnP IGD & PCP') }}</x-responsive-nav-link>
                </div>
            </div>

            <!-- Diagnostics -->
            <div x-data="{ expanded: {{ request()->routeIs('diagnostics.*') ? 'true' : 'false' }} }">
                <button @click="expanded = !expanded"
                    class="flex items-center justify-between w-full pl-3 pr-4 py-2 border-l-4 border-transparent text-left text-base font-medium text-gray-600 dark:text-gray-400 hover:text-gray-800 dark:hover:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 hover:border-gray-300 dark:hover:border-gray-600 focus:outline-none transition duration-150 ease-in-out">
                    <span>Diagnostics</span>
                    <svg class="w-4 h-4 transform transition-transform duration-200" :class="{'rotate-180': expanded}"
                        xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd"
                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                            clip-rule="evenodd" />
                    </svg>
                </button>
                <div x-show="expanded" x-collapse class="space-y-1 pl-4 bg-gray-50 dark:bg-gray-900/50">
                    <x-responsive-nav-link :href="route('diagnostics.arp-table', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.arp-table')">{{ __('ARP Table') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.authentication', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.authentication')">{{ __('Authentication') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.backup.index', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.backup.*')">{{ __('Backup & Restore') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.command-prompt', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.command-prompt')">{{ __('Command Prompt') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.dns-lookup', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.dns-lookup')">{{ __('DNS Lookup') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.edit-file', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.edit-file')">{{ __('Edit File') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.factory-defaults', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.factory-defaults')">{{ __('Factory Defaults') }}</x-responsive-nav-link>
                    @if(!auth()->user()->isReadOnly())
                    <x-responsive-nav-link :href="route('diagnostics.halt-system', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.halt-system')">{{ __('Halt System') }}</x-responsive-nav-link>
                    @endif
                    @if(!$isOpnSense)
                    <x-responsive-nav-link :href="route('diagnostics.limiter-info', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.limiter-info')">{{ __('Limiter Info') }}</x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('diagnostics.ndp-table', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.ndp-table')">{{ __('NDP Table') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.packet_capture.index', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.packet_capture.*')">{{ __('Packet Capture') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.pf-info', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.pf-info')">{{ __('pfInfo') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.pf-top', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.pf-top')">{{ __('pfTop') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.ping', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.ping')">{{ __('Ping') }}</x-responsive-nav-link>
                    @if(!auth()->user()->isReadOnly())
                    <x-responsive-nav-link :href="route('diagnostics.reboot.index', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.reboot.*')">{{ __('Reboot') }}</x-responsive-nav-link>
                    @endif
                    <x-responsive-nav-link :href="route('diagnostics.routes', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.routes')">{{ __('Routes') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.smart-status', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.smart-status')">{{ __('S.M.A.R.T. Status') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.sockets', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.sockets')">{{ __('Sockets') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.states', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.states')">{{ __('States') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.states-summary', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.states-summary')">{{ __('States Summary') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.system-activity', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.system-activity')">{{ __('System Activity') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.tables', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.tables')">{{ __('Tables') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.test_port.index', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.test_port.*')">{{ __('Test Port') }}</x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('diagnostics.traceroute', request()->route('firewall'))"
                        :active="request()->routeIs('diagnostics.traceroute')">{{ __('Traceroute') }}</x-responsive-nav-link>
                </div>
            </div>

        </div>


    </div>
</nav>