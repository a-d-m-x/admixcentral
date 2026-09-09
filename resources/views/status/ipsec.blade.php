<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('IPsec Status') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-4">
            @if(session('success'))
                <div class="pf-alert pf-alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="pf-alert pf-alert-error">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{-- Sub-Navigation Tabs --}}
                    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto">
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'overview']) }}"
                               class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'overview' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                {{ __('Overview') }}
                            </a>
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'leases']) }}"
                               class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'leases' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                {{ __('Leases') }}
                            </a>
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'sads']) }}"
                               class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'sads' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                {{ __('SADs') }}
                            </a>
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'spds']) }}"
                               class="whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'spds' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                {{ __('SPDs') }}
                            </a>
                        </nav>
                    </div>

                    {{-- TAB 1: OVERVIEW --}}
                    @if($tab === 'overview')
                        <div class="overflow-x-auto relative shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <th scope="col" class="py-3 px-4">{{ __('ID') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Description') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Local') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Remote') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Role') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Timers') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Algo') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @forelse($overview as $sa)
                                        <tr x-data="{ showChildren: {{ !empty($sa['child_sas']) ? 'true' : 'false' }} }" class="hover:bg-gray-50/75 dark:hover:bg-gray-700/50 transition border-b border-gray-200 dark:border-gray-700">
                                            {{-- ID --}}
                                            <td class="py-4 px-4 align-top font-mono">
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $sa['con_id'] ?? '-' }}</span>
                                                @if(!empty($sa['uniqueid']))
                                                    <div class="text-xs text-gray-500 dark:text-gray-400">#{{ $sa['uniqueid'] }}</div>
                                                @endif
                                            </td>

                                            {{-- Description --}}
                                            <td class="py-4 px-4 align-top">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $sa['descr'] ?: 'IPsec SA' }}
                                                </div>
                                                @if($sa['p1_id'] !== null)
                                                    <a href="{{ route('vpn.ipsec', $firewall) }}" class="inline-flex items-center text-xs text-indigo-600 dark:text-indigo-400 hover:underline mt-1" title="{{ __('Edit Phase 1') }}">
                                                        <i class="fa-solid fa-pencil mr-1"></i>{{ __('Edit') }}
                                                    </a>
                                                @endif
                                            </td>

                                            {{-- Local --}}
                                            <td class="py-4 px-4 align-top text-xs leading-relaxed">
                                                <div>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">ID:</span>
                                                    <span class="font-mono">{{ $sa['local_id'] ?: 'Unknown' }}</span>
                                                </div>
                                                @if(!empty($sa['local_host']))
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">Host:</span>
                                                        <span class="font-mono">{{ $sa['local_host'] }}{{ !empty($sa['local_port']) ? ':' . $sa['local_port'] : '' }}</span>
                                                    </div>
                                                @endif
                                                @php
                                                    $localSpi = ($sa['initiator'] ?? 'yes') === 'yes' ? ($sa['initiator_spi'] ?? '') : ($sa['responder_spi'] ?? '');
                                                @endphp
                                                @if($localSpi)
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">SPI:</span>
                                                        <span class="font-mono">{{ $localSpi }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($sa['nat_local']))
                                                    <span class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-600">NAT-T</span>
                                                @endif
                                            </td>

                                            {{-- Remote --}}
                                            <td class="py-4 px-4 align-top text-xs leading-relaxed">
                                                <div>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">ID:</span>
                                                    <span class="font-mono">{{ $sa['remote_id'] ?: 'Unknown' }}</span>
                                                </div>
                                                @if(!empty($sa['remote_host']))
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">Host:</span>
                                                        <span class="font-mono">{{ $sa['remote_host'] }}{{ !empty($sa['remote_port']) ? ':' . $sa['remote_port'] : '' }}</span>
                                                    </div>
                                                @endif
                                                @php
                                                    $remoteSpi = ($sa['initiator'] ?? 'yes') === 'yes' ? ($sa['responder_spi'] ?? '') : ($sa['initiator_spi'] ?? '');
                                                @endphp
                                                @if($remoteSpi)
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">SPI:</span>
                                                        <span class="font-mono">{{ $remoteSpi }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($sa['nat_remote']))
                                                    <span class="inline-block mt-0.5 px-1.5 py-0.5 text-[10px] font-semibold bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded border border-gray-300 dark:border-gray-600">NAT-T</span>
                                                @endif
                                            </td>

                                            {{-- Role --}}
                                            <td class="py-4 px-4 align-top text-xs">
                                                <div class="font-semibold text-gray-900 dark:text-gray-100">IKEv{{ $sa['version'] ?? 2 }}</div>
                                                <div class="text-gray-500 dark:text-gray-400">{{ ($sa['initiator'] ?? 'yes') === 'yes' ? __('Initiator') : __('Responder') }}</div>
                                            </td>

                                            {{-- Timers --}}
                                            <td class="py-4 px-4 align-top text-xs leading-relaxed font-mono">
                                                @if(($sa['version'] ?? 2) == 2)
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300 font-sans">Rekey:</span>
                                                        @if(!empty($sa['rekey_time']))
                                                            {{ $sa['rekey_time'] }}s ({{ $sa['rekey_dhms'] }})
                                                        @else
                                                            <span class="text-gray-400">Disabled</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300 font-sans">Reauth:</span>
                                                    @if(!empty($sa['reauth_time']))
                                                        {{ $sa['reauth_time'] }}s ({{ $sa['reauth_dhms'] }})
                                                    @else
                                                        <span class="text-gray-400">Disabled</span>
                                                    @endif
                                                </div>
                                            </td>

                                            {{-- Algo --}}
                                            <td class="py-4 px-4 align-top text-xs font-mono space-y-0.5">
                                                @if(!empty($sa['encr_alg']))
                                                    <div class="font-medium text-gray-800 dark:text-gray-200">
                                                        {{ $sa['encr_alg'] }}{{ !empty($sa['encr_keysize']) ? ' (' . $sa['encr_keysize'] . ')' : '' }}
                                                    </div>
                                                @endif
                                                @if(!empty($sa['integ_alg']))
                                                    <div class="text-gray-600 dark:text-gray-400">{{ $sa['integ_alg'] }}</div>
                                                @endif
                                                @if(!empty($sa['prf_alg']))
                                                    <div class="text-gray-500 dark:text-gray-400">{{ $sa['prf_alg'] }}</div>
                                                @endif
                                                @if(!empty($sa['dh_group']))
                                                    <div class="text-gray-500 dark:text-gray-400">{{ $sa['dh_group'] }}</div>
                                                @endif
                                                @if(empty($sa['encr_alg']) && empty($sa['integ_alg']))
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>

                                            {{-- Status & Actions --}}
                                            <td class="py-4 px-4 align-top">
                                                <div class="space-y-1.5">
                                                    @php
                                                        $state = strtoupper($sa['state'] ?? 'DISCONNECTED');
                                                    @endphp
                                                    <span class="px-2.5 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full {{ $state === 'ESTABLISHED' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : ($state === 'CONNECTING' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/50 dark:text-yellow-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300') }}">
                                                        {{ ucfirst(strtolower($state)) }}
                                                    </span>

                                                    @if($state === 'ESTABLISHED' && !empty($sa['established']))
                                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                                            {{ $sa['established'] }}s ({{ $sa['established_dhms'] }}) ago
                                                        </div>
                                                    @endif

                                                    @if(!auth()->user()->isReadOnly())
                                                        @if(in_array($state, ['ESTABLISHED', 'CONNECTING']))
                                                            <div>
                                                                <form method="POST" action="{{ route('status.ipsec.disconnect', $firewall) }}" class="inline-block">
                                                                    @csrf
                                                                    <input type="hidden" name="type" value="p1">
                                                                    <input type="hidden" name="conid" value="{{ $sa['con_id'] }}">
                                                                    <input type="hidden" name="uniqueid" value="{{ $sa['uniqueid'] }}">
                                                                    <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-amber-800 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300 dark:hover:bg-amber-900/60 rounded border border-amber-300 dark:border-amber-700 transition" onclick="return confirm('Disconnect Phase 1 tunnel {{ $sa['con_id'] }}?');">
                                                                        <i class="fa-solid fa-power-off mr-1"></i>{{ __('Disconnect P1') }}
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @else
                                                            <div>
                                                                <form method="POST" action="{{ route('status.ipsec.connect', $firewall) }}" class="inline-block">
                                                                    @csrf
                                                                    <input type="hidden" name="type" value="p1">
                                                                    <input type="hidden" name="conid" value="{{ $sa['con_id'] }}">
                                                                    <button type="submit" class="inline-flex items-center px-2 py-1 text-xs font-medium text-green-800 bg-green-100 hover:bg-green-200 dark:bg-green-900/40 dark:text-green-300 dark:hover:bg-green-900/60 rounded border border-green-300 dark:border-green-700 transition">
                                                                        <i class="fa-solid fa-play mr-1"></i>{{ __('Connect P1') }}
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Phase 2 (Child SA) Collapsible Sub-Row --}}
                                        <tr x-data="{ showChildren: {{ !empty($sa['child_sas']) ? 'true' : 'false' }} }" class="bg-gray-50/50 dark:bg-gray-900/40 border-b-2 border-gray-200 dark:border-gray-700">
                                            <td colspan="8" class="p-3">
                                                @if(!empty($sa['child_sas']))
                                                    <div class="mb-2">
                                                        <button type="button" @click="showChildren = !showChildren" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium rounded text-white bg-sky-600 hover:bg-sky-500 dark:bg-sky-700 dark:hover:bg-sky-600 shadow-sm transition">
                                                            <i class="fa-solid" :class="showChildren ? 'fa-minus-circle' : 'fa-plus-circle'"></i>
                                                            <span x-text="showChildren ? '{{ __('Hide child SA entries') }}' : '{{ __('Show child SA entries') }}'"></span>
                                                            <span>({{ count($sa['child_sas']) }} {{ __('Connected') }})</span>
                                                        </button>
                                                    </div>

                                                    <div x-show="showChildren" x-cloak class="mt-2 overflow-x-auto rounded border border-sky-300 dark:border-sky-800 shadow-sm">
                                                        <table class="w-full text-xs text-left text-gray-700 dark:text-gray-300">
                                                            <thead class="bg-sky-700 dark:bg-sky-900 text-white uppercase text-[11px] font-semibold">
                                                                <tr>
                                                                    <th class="py-2 px-3">{{ __('ID') }}</th>
                                                                    <th class="py-2 px-3">{{ __('Description') }}</th>
                                                                    <th class="py-2 px-3">{{ __('Local') }}</th>
                                                                    <th class="py-2 px-3">{{ __('SPI(s)') }}</th>
                                                                    <th class="py-2 px-3">{{ __('Remote') }}</th>
                                                                    <th class="py-2 px-3">{{ __('Times') }}</th>
                                                                    <th class="py-2 px-3">{{ __('Algo') }}</th>
                                                                    <th class="py-2 px-3">{{ __('Stats') }}</th>
                                                                    <th class="py-2 px-3">{{ __('Status') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                                                @foreach($sa['child_sas'] as $child)
                                                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/60 transition">
                                                                        {{-- Child ID --}}
                                                                        <td class="py-2.5 px-3 font-mono align-top">
                                                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $child['name'] }}</span>
                                                                            @if(!empty($child['uniqueid']))
                                                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">#{{ $child['uniqueid'] }}</div>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Description --}}
                                                                        <td class="py-2.5 px-3 align-top">
                                                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $child['descr'] ?: 'Phase 2 Subnet' }}</div>
                                                                            @if(!empty($child['p2_id']))
                                                                                <a href="{{ route('vpn.ipsec.phase2', [$firewall, $sa['p1_id'] ?? $child['p2_id']]) }}" class="inline-flex items-center text-[10px] text-indigo-600 dark:text-indigo-400 hover:underline mt-0.5" title="{{ __('Edit Phase 2') }}">
                                                                                    <i class="fa-solid fa-pencil mr-1"></i>{{ __('Edit') }}
                                                                                </a>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Local --}}
                                                                        <td class="py-2.5 px-3 font-mono align-top text-gray-800 dark:text-gray-200">
                                                                            @forelse($child['local_ts'] as $net)
                                                                                <div>{{ $net }}</div>
                                                                            @empty
                                                                                <span class="text-gray-400">Unknown</span>
                                                                            @endforelse
                                                                        </td>

                                                                        {{-- Child SPIs --}}
                                                                        <td class="py-2.5 px-3 font-mono align-top text-[11px] leading-relaxed">
                                                                            @if(!empty($child['spi_in']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Local:</span> {{ $child['spi_in'] }}</div>
                                                                            @endif
                                                                            @if(!empty($child['spi_out']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Remote:</span> {{ $child['spi_out'] }}</div>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Remote --}}
                                                                        <td class="py-2.5 px-3 font-mono align-top text-gray-800 dark:text-gray-200">
                                                                            @forelse($child['remote_ts'] as $net)
                                                                                <div>{{ $net }}</div>
                                                                            @empty
                                                                                <span class="text-gray-400">Unknown</span>
                                                                            @endforelse
                                                                        </td>

                                                                        {{-- Child Times --}}
                                                                        <td class="py-2.5 px-3 font-mono align-top text-[11px] leading-relaxed">
                                                                            @if(!empty($child['rekey_time']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Rekey:</span> {{ $child['rekey_time'] }}s ({{ $child['rekey_dhms'] }})</div>
                                                                            @endif
                                                                            @if(!empty($child['life_time']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Life:</span> {{ $child['life_time'] }}s ({{ $child['life_dhms'] }})</div>
                                                                            @endif
                                                                            @if(!empty($child['install_time']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Install:</span> {{ $child['install_time'] }}s ({{ $child['install_dhms'] }})</div>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Algo --}}
                                                                        <td class="py-2.5 px-3 font-mono align-top text-[11px] space-y-0.5">
                                                                            @if(!empty($child['encr_alg']))
                                                                                <div>{{ $child['encr_alg'] }}{{ !empty($child['encr_keysize']) ? ' (' . $child['encr_keysize'] . ')' : '' }}</div>
                                                                            @endif
                                                                            @if(!empty($child['integ_alg']))
                                                                                <div>{{ $child['integ_alg'] }}</div>
                                                                            @endif
                                                                            @if(!empty($child['dh_group']))
                                                                                <div>{{ $child['dh_group'] }}</div>
                                                                            @endif
                                                                            <div class="text-gray-500 dark:text-gray-400">IPComp: {{ $child['ipcomp'] ?? 'None' }}</div>
                                                                        </td>

                                                                        {{-- Child Stats --}}
                                                                        <td class="py-2.5 px-3 font-mono align-top text-[11px] leading-relaxed">
                                                                            <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Bytes-In:</span> {{ $child['packets_in_formatted'] }} ({{ $child['bytes_in_formatted'] }})</div>
                                                                            <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Packets-In:</span> {{ $child['packets_in_formatted'] }}</div>
                                                                            <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Bytes-Out:</span> {{ $child['packets_out_formatted'] }} ({{ $child['bytes_out_formatted'] }})</div>
                                                                            <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Packets-Out:</span> {{ $child['packets_out_formatted'] }}</div>
                                                                        </td>

                                                                        {{-- Child Status & Actions --}}
                                                                        <td class="py-2.5 px-3 align-top">
                                                                            @php
                                                                                $cState = strtoupper($child['state'] ?? 'INSTALLED');
                                                                            @endphp
                                                                            <span class="px-2 py-0.5 inline-flex text-[11px] leading-4 font-semibold rounded-full {{ $cState === 'INSTALLED' ? 'bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300' : 'bg-red-100 text-red-800 dark:bg-red-900/50 dark:text-red-300' }}">
                                                                                {{ ucfirst(strtolower($cState)) }}
                                                                            </span>

                                                                            @if(!auth()->user()->isReadOnly() && $cState === 'INSTALLED')
                                                                                <form method="POST" action="{{ route('status.ipsec.disconnect', $firewall) }}" class="mt-1.5">
                                                                                    @csrf
                                                                                    <input type="hidden" name="type" value="p2">
                                                                                    <input type="hidden" name="name" value="{{ $child['name'] }}">
                                                                                    <input type="hidden" name="uniqueid" value="{{ $child['uniqueid'] }}">
                                                                                    <button type="submit" class="inline-flex items-center px-2 py-0.5 text-[10px] font-medium text-amber-800 bg-amber-100 hover:bg-amber-200 dark:bg-amber-900/40 dark:text-amber-300 rounded border border-amber-300 dark:border-amber-700 transition" onclick="return confirm('Disconnect Child SA {{ $child['name'] }}?');">
                                                                                        <i class="fa-solid fa-power-off mr-1"></i>{{ __('Disconnect P2') }}
                                                                                    </button>
                                                                                </form>
                                                                            @endif
                                                                        </td>
                                                                    </tr>
                                                                @endforeach
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                @else
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 italic">
                                                        {{ __('No child SA entries for this tunnel.') }}
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-8 px-4 text-center text-gray-500 dark:text-gray-400">
                                                {{ __('No IPsec SAs found.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- TAB 2: LEASES --}}
                    @if($tab === 'leases')
                        @php
                            $pools = $leases['pool'] ?? ($leases['pools'] ?? []);
                            $clientLeases = $leases['leases'] ?? [];
                        @endphp

                        @if(!empty($pools) && is_array($pools))
                            <div class="overflow-x-auto relative shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700 mb-6">
                                <h4 class="text-sm font-semibold p-3 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('IPsec Address Pools') }}</h4>
                                <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                    <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-800 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                        <tr>
                                            <th scope="col" class="py-3 px-4">{{ __('Pool') }}</th>
                                            <th scope="col" class="py-3 px-4">{{ __('Base') }}</th>
                                            <th scope="col" class="py-3 px-4">{{ __('Online') }}</th>
                                            <th scope="col" class="py-3 px-4">{{ __('Total Usage') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                        @foreach($pools as $p)
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                                <td class="py-3 px-4 font-semibold text-gray-900 dark:text-gray-100">{{ $p['name'] ?? 'Mobile Pool' }}</td>
                                                <td class="py-3 px-4 font-mono">{{ $p['base'] ?? ($p['subnet'] ?? '-') }}</td>
                                                <td class="py-3 px-4">{{ $p['online'] ?? 0 }}</td>
                                                <td class="py-3 px-4">
                                                    @if(isset($p['size']) && (int)$p['size'] > 0)
                                                        {{ ((int)($p['online'] ?? 0)) + ((int)($p['offline'] ?? 0)) }} / {{ $p['size'] }}
                                                    @else
                                                        {{ $p['usage'] ?? '-' }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <div class="overflow-x-auto relative shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                            <h4 class="text-sm font-semibold p-3 bg-gray-50 dark:bg-gray-700 text-gray-700 dark:text-gray-300 uppercase tracking-wider">{{ __('Active Mobile Client Leases') }}</h4>
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-100 dark:bg-gray-800 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700">
                                    <tr>
                                        <th scope="col" class="py-3 px-4">{{ __('ID') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Host') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Assigned IP') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @forelse($clientLeases as $lease)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50">
                                            <td class="py-3 px-4 font-mono">{{ $lease['id'] ?? ($lease['user'] ?? '-') }}</td>
                                            <td class="py-3 px-4 font-mono">{{ $lease['host'] ?? ($lease['remote_host'] ?? '-') }}</td>
                                            <td class="py-3 px-4 font-mono font-medium text-gray-900 dark:text-gray-100">{{ $lease['address'] ?? ($lease['ip'] ?? '-') }}</td>
                                            <td class="py-3 px-4">
                                                <span class="px-2 py-0.5 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-900/50 dark:text-green-300">
                                                    {{ ucfirst($lease['status'] ?? 'Online') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="py-8 px-4 text-center text-gray-500 dark:text-gray-400">
                                                {{ __('No IPsec mobile client leases found.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- TAB 3: SADS --}}
                    @if($tab === 'sads')
                        <div class="overflow-x-auto relative shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <th scope="col" class="py-3 px-4">{{ __('Source') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Destination') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Protocol') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('SPI') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Enc. alg.') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Auth. alg.') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Data') }}</th>
                                        <th scope="col" class="py-3 px-4 text-right">{{ __('Actions') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @forelse($sads as $sa)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                            <td class="py-3 px-4 font-mono text-xs">{{ $sa['src'] ?? '-' }}</td>
                                            <td class="py-3 px-4 font-mono text-xs">{{ $sa['dst'] ?? '-' }}</td>
                                            <td class="py-3 px-4 font-mono uppercase text-xs">{{ $sa['proto'] ?? 'ESP' }}</td>
                                            <td class="py-3 px-4 font-mono text-xs font-medium text-gray-900 dark:text-gray-100">{{ $sa['spi'] ?? '-' }}</td>
                                            <td class="py-3 px-4 font-mono text-xs">{{ $sa['ealgo'] ?? '-' }}</td>
                                            <td class="py-3 px-4 font-mono text-xs">{{ $sa['aalgo'] ?? '-' }}</td>
                                            <td class="py-3 px-4 font-mono text-xs text-gray-500 dark:text-gray-400">{{ $sa['data'] ?? '-' }}</td>
                                            <td class="py-3 px-4 text-right">
                                                @if(!auth()->user()->isReadOnly())
                                                    <form method="POST" action="{{ route('status.ipsec.sad.destroy', $firewall) }}" class="inline-block" onsubmit="return confirm('Delete this SAD entry?');">
                                                        @csrf
                                                        <input type="hidden" name="src" value="{{ $sa['src'] ?? '' }}">
                                                        <input type="hidden" name="dst" value="{{ $sa['dst'] ?? '' }}">
                                                        <input type="hidden" name="proto" value="{{ $sa['proto'] ?? 'esp' }}">
                                                        <input type="hidden" name="spi" value="{{ $sa['spi'] ?? '' }}">
                                                        <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300 p-1" title="{{ __('Delete SAD entry') }}">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-8 px-4 text-center text-gray-500 dark:text-gray-400">
                                                {{ __('No IPsec security associations (SAD entries) found.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- TAB 4: SPDS --}}
                    @if($tab === 'spds')
                        <div class="overflow-x-auto relative shadow-sm sm:rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-300 border-b border-gray-200 dark:border-gray-600">
                                    <tr>
                                        <th scope="col" class="py-3 px-4">{{ __('Mode') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Source') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Destination') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Direction') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Protocol') }}</th>
                                        <th scope="col" class="py-3 px-4">{{ __('Tunnel Endpoints') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @forelse($spds as $sp)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                            <td class="py-3 px-4 text-xs font-semibold text-gray-900 dark:text-gray-100">
                                                @if(($sp['scope'] ?? '') === 'ifnet')
                                                    VTI {{ $sp['ifname'] ?? '' }}
                                                @else
                                                    Tunnel
                                                @endif
                                            </td>
                                            <td class="py-3 px-4 font-mono text-xs">{{ $sp['srcid'] ?? ($sp['src'] ?? '-') }}</td>
                                            <td class="py-3 px-4 font-mono text-xs">{{ $sp['dstid'] ?? ($sp['dst'] ?? '-') }}</td>
                                            <td class="py-3 px-4 text-xs">
                                                @if(($sp['dir'] ?? 'out') === 'in')
                                                    <span class="inline-flex items-center text-blue-600 dark:text-blue-400 font-medium">
                                                        <i class="fa-solid fa-arrow-left mr-1"></i>{{ __('Inbound') }}
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center text-green-600 dark:text-green-400 font-medium">
                                                        <i class="fa-solid fa-arrow-right mr-1"></i>{{ __('Outbound') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="py-3 px-4 font-mono uppercase text-xs">{{ $sp['proto'] ?? 'ANY' }}</td>
                                            <td class="py-3 px-4 font-mono text-xs text-gray-700 dark:text-gray-300">
                                                @if(($sp['dir'] ?? 'out') === 'in')
                                                    {{ $sp['dst'] ?? '-' }} &larr; {{ $sp['src'] ?? '-' }}
                                                @else
                                                    {{ $sp['src'] ?? '-' }} &rarr; {{ $sp['dst'] ?? '-' }}
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-8 px-4 text-center text-gray-500 dark:text-gray-400">
                                                {{ __('No IPsec security policies (SPD entries) configured.') }}
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
