<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('IPsec Status') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8 space-y-6">
            @if(session('success'))
                <div class="p-4 mb-4 text-sm text-emerald-800 rounded-lg bg-emerald-50 dark:bg-emerald-950/50 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="p-4 mb-4 text-sm text-rose-800 rounded-lg bg-rose-50 dark:bg-rose-950/50 dark:text-rose-300 border border-rose-200 dark:border-rose-800 flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-rose-600 dark:text-rose-400"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-xl border border-gray-200 dark:border-gray-700/80">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{-- Sub-Navigation Tabs --}}
                    <div class="mb-6 border-b border-gray-200 dark:border-gray-700">
                        <nav class="-mb-px flex space-x-6 overflow-x-auto">
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'overview']) }}"
                               class="inline-flex items-center gap-2 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'overview' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                <i class="fa-solid fa-network-wired {{ $tab === 'overview' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                {{ __('Overview') }}
                            </a>
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'leases']) }}"
                               class="inline-flex items-center gap-2 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'leases' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                <i class="fa-solid fa-address-book {{ $tab === 'leases' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                {{ __('Leases') }}
                            </a>
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'sads']) }}"
                               class="inline-flex items-center gap-2 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'sads' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                <i class="fa-solid fa-shield-halved {{ $tab === 'sads' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                {{ __('SADs') }}
                            </a>
                            <a href="{{ route('status.ipsec', [$firewall, 'tab' => 'spds']) }}"
                               class="inline-flex items-center gap-2 whitespace-nowrap py-3 px-1 border-b-2 font-medium text-sm transition {{ $tab === 'spds' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400 font-semibold' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-300' }}">
                                <i class="fa-solid fa-filter {{ $tab === 'spds' ? 'text-indigo-500' : 'text-gray-400' }}"></i>
                                {{ __('SPDs') }}
                            </a>
                        </nav>
                    </div>

                    {{-- TAB 1: OVERVIEW --}}
                    @if($tab === 'overview')
                        @php
                            $totalP1 = count($overview);
                            $establishedP1 = 0;
                            $totalChildSAs = 0;
                            foreach($overview as $oSa) {
                                if (strtoupper($oSa['state'] ?? '') === 'ESTABLISHED') {
                                    $establishedP1++;
                                }
                                $totalChildSAs += count($oSa['child_sas'] ?? []);
                            }
                            $disconnectedP1 = $totalP1 - $establishedP1;
                        @endphp

                        {{-- Quick Metrics Header --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80">
                                <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Total Tunnels') }}</div>
                                <div class="mt-1 text-2xl font-bold text-slate-900 dark:slate-100">{{ $totalP1 }}</div>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80">
                                <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Connected (P1)') }}</div>
                                <div class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                                    {{ $establishedP1 }}
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80">
                                <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Disconnected') }}</div>
                                <div class="mt-1 text-2xl font-bold text-slate-600 dark:text-slate-400 flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full bg-slate-400"></span>
                                    {{ $disconnectedP1 }}
                                </div>
                            </div>
                            <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-700/80">
                                <div class="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">{{ __('Child SAs Active') }}</div>
                                <div class="mt-1 text-2xl font-bold text-indigo-600 dark:text-indigo-400">{{ $totalChildSAs }}</div>
                            </div>
                        </div>

                        <div class="overflow-x-auto relative shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50/80 dark:bg-gray-700/70 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 font-semibold">
                                    <tr>
                                        <th scope="col" class="py-3.5 px-4">{{ __('ID') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Description') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Local') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Remote') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Role') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Timers') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Algorithms') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Status') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700/80 bg-white dark:bg-gray-800">
                                    @forelse($overview as $sa)
                                        <tr x-data="{ showChildren: {{ !empty($sa['child_sas']) ? 'true' : 'false' }} }" class="hover:bg-slate-50/75 dark:hover:bg-slate-700/40 transition">
                                            {{-- ID --}}
                                            <td class="py-4 px-4 align-top font-mono">
                                                <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $sa['con_id'] ?? '-' }}</span>
                                                @if(!empty($sa['uniqueid']))
                                                    <div class="text-[11px] text-gray-500 dark:text-gray-400">#{{ $sa['uniqueid'] }}</div>
                                                @endif
                                            </td>

                                            {{-- Description --}}
                                            <td class="py-4 px-4 align-top">
                                                <div class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $sa['descr'] ?: 'IPsec SA' }}
                                                </div>
                                                @if($sa['p1_id'] !== null)
                                                    <a href="{{ route('vpn.ipsec', $firewall) }}" class="inline-flex items-center text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium mt-1 transition" title="{{ __('Edit Phase 1') }}">
                                                        <i class="fa-solid fa-pencil mr-1 text-[11px]"></i>{{ __('Edit Phase 1') }}
                                                    </a>
                                                @endif
                                            </td>

                                            {{-- Local --}}
                                            <td class="py-4 px-4 align-top text-xs leading-relaxed">
                                                <div>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">ID:</span>
                                                    <span class="font-mono text-gray-800 dark:text-gray-200">{{ $sa['local_id'] ?: 'Unknown' }}</span>
                                                </div>
                                                @if(!empty($sa['local_host']))
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">Host:</span>
                                                        <span class="font-mono text-gray-800 dark:text-gray-200">{{ $sa['local_host'] }}{{ !empty($sa['local_port']) ? ':' . $sa['local_port'] : '' }}</span>
                                                    </div>
                                                @endif
                                                @php
                                                    $localSpi = ($sa['initiator'] ?? 'yes') === 'yes' ? ($sa['initiator_spi'] ?? '') : ($sa['responder_spi'] ?? '');
                                                @endphp
                                                @if($localSpi)
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">SPI:</span>
                                                        <span class="font-mono text-gray-700 dark:text-gray-300">{{ $localSpi }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($sa['nat_local']))
                                                    <span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded border border-slate-300 dark:border-slate-600">NAT-T</span>
                                                @endif
                                            </td>

                                            {{-- Remote --}}
                                            <td class="py-4 px-4 align-top text-xs leading-relaxed">
                                                <div>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300">ID:</span>
                                                    <span class="font-mono text-gray-800 dark:text-gray-200">{{ $sa['remote_id'] ?: 'Unknown' }}</span>
                                                </div>
                                                @if(!empty($sa['remote_host']))
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">Host:</span>
                                                        <span class="font-mono text-gray-800 dark:text-gray-200">{{ $sa['remote_host'] }}{{ !empty($sa['remote_port']) ? ':' . $sa['remote_port'] : '' }}</span>
                                                    </div>
                                                @endif
                                                @php
                                                    $remoteSpi = ($sa['initiator'] ?? 'yes') === 'yes' ? ($sa['responder_spi'] ?? '') : ($sa['initiator_spi'] ?? '');
                                                @endphp
                                                @if($remoteSpi)
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300">SPI:</span>
                                                        <span class="font-mono text-gray-700 dark:text-gray-300">{{ $remoteSpi }}</span>
                                                    </div>
                                                @endif
                                                @if(!empty($sa['nat_remote']))
                                                    <span class="inline-block mt-1 px-1.5 py-0.5 text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 rounded border border-slate-300 dark:border-slate-600">NAT-T</span>
                                                @endif
                                            </td>

                                            {{-- Role --}}
                                            <td class="py-4 px-4 align-top text-xs">
                                                <div class="font-semibold text-gray-900 dark:text-gray-100">IKEv{{ $sa['version'] ?? 2 }}</div>
                                                <div class="text-gray-500 dark:text-gray-400 text-[11px]">{{ ($sa['initiator'] ?? 'yes') === 'yes' ? __('Initiator') : __('Responder') }}</div>
                                            </td>

                                            {{-- Timers --}}
                                            <td class="py-4 px-4 align-top text-xs leading-relaxed font-mono">
                                                @if(($sa['version'] ?? 2) == 2)
                                                    <div>
                                                        <span class="font-semibold text-gray-700 dark:text-gray-300 font-sans">Rekey:</span>
                                                        @if(!empty($sa['rekey_time']))
                                                            <span class="text-gray-800 dark:text-gray-200">{{ $sa['rekey_time'] }}s ({{ $sa['rekey_dhms'] }})</span>
                                                        @else
                                                            <span class="text-gray-400">Disabled</span>
                                                        @endif
                                                    </div>
                                                @endif
                                                <div>
                                                    <span class="font-semibold text-gray-700 dark:text-gray-300 font-sans">Reauth:</span>
                                                    @if(!empty($sa['reauth_time']))
                                                        <span class="text-gray-800 dark:text-gray-200">{{ $sa['reauth_time'] }}s ({{ $sa['reauth_dhms'] }})</span>
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
                                                <div class="space-y-2">
                                                    @php
                                                        $state = strtoupper($sa['state'] ?? 'DISCONNECTED');
                                                    @endphp
                                                    @if($state === 'ESTABLISHED')
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                            {{ __('Established') }}
                                                        </span>
                                                    @elseif($state === 'CONNECTING')
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-200 dark:border-amber-800/80">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500 animate-pulse"></span>
                                                            {{ __('Connecting') }}
                                                        </span>
                                                    @else
                                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-200 dark:border-slate-700">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>
                                                            {{ __('Disconnected') }}
                                                        </span>
                                                    @endif

                                                    @if($state === 'ESTABLISHED' && !empty($sa['established']))
                                                        <div class="text-[11px] text-gray-500 dark:text-gray-400">
                                                            {{ $sa['established'] }}s ({{ $sa['established_dhms'] }})
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
                                                                    <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-amber-800 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/60 rounded-lg border border-amber-200 dark:border-amber-800/60 transition shadow-xs" onclick="return confirm('Disconnect Phase 1 tunnel {{ $sa['con_id'] }}?');">
                                                                        <i class="fa-solid fa-power-off text-amber-600 dark:text-amber-400"></i>{{ __('Disconnect P1') }}
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @else
                                                            <div>
                                                                <form method="POST" action="{{ route('status.ipsec.connect', $firewall) }}" class="inline-block">
                                                                    @csrf
                                                                    <input type="hidden" name="type" value="p1">
                                                                    <input type="hidden" name="conid" value="{{ $sa['con_id'] }}">
                                                                    <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1 text-xs font-medium text-emerald-800 bg-emerald-50 hover:bg-emerald-100 dark:bg-emerald-950/40 dark:text-emerald-300 dark:hover:bg-emerald-900/60 rounded-lg border border-emerald-200 dark:border-emerald-800/60 transition shadow-xs">
                                                                        <i class="fa-solid fa-play text-emerald-600 dark:text-emerald-400"></i>{{ __('Connect P1') }}
                                                                    </button>
                                                                </form>
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>

                                        {{-- Phase 2 (Child SA) Collapsible Sub-Row --}}
                                        <tr x-data="{ showChildren: {{ !empty($sa['child_sas']) ? 'true' : 'false' }} }" class="bg-slate-50/60 dark:bg-slate-900/30 border-b border-gray-200 dark:border-gray-700">
                                            <td colspan="8" class="p-4">
                                                @if(!empty($sa['child_sas']))
                                                    <div>
                                                        <button type="button" @click="showChildren = !showChildren" class="inline-flex items-center gap-2 px-3 py-1.5 text-xs font-semibold rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-750 border border-slate-300 dark:border-slate-600 shadow-xs transition">
                                                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400 transition-transform duration-200" :class="showChildren ? 'rotate-90' : ''"></i>
                                                            <span x-text="showChildren ? '{{ __('Hide child SA entries') }}' : '{{ __('Show child SA entries') }}'"></span>
                                                            <span class="ml-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-indigo-50 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800/80">
                                                                {{ count($sa['child_sas']) }} {{ __('Connected') }}
                                                            </span>
                                                        </button>
                                                    </div>

                                                    <div x-show="showChildren" x-cloak class="mt-3 overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-700/80 bg-white dark:bg-slate-800/90 shadow-sm">
                                                        <table class="w-full text-xs text-left text-gray-700 dark:text-gray-300">
                                                            <thead class="bg-slate-100/90 dark:bg-slate-800 text-slate-700 dark:text-slate-300 uppercase text-[10px] font-semibold tracking-wider border-b border-slate-200 dark:border-slate-700">
                                                                <tr>
                                                                    <th class="py-2.5 px-3.5">{{ __('ID') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('Description') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('Local') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('SPI(s)') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('Remote') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('Times') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('Algorithms') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('Stats') }}</th>
                                                                    <th class="py-2.5 px-3.5">{{ __('Status') }}</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                                                @foreach($sa['child_sas'] as $child)
                                                                    <tr class="hover:bg-slate-50/75 dark:hover:bg-slate-700/50 transition">
                                                                        {{-- Child ID --}}
                                                                        <td class="py-2.5 px-3.5 font-mono align-top">
                                                                            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $child['name'] }}</span>
                                                                            @if(!empty($child['uniqueid']))
                                                                                <div class="text-[10px] text-gray-500 dark:text-gray-400">#{{ $child['uniqueid'] }}</div>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Description --}}
                                                                        <td class="py-2.5 px-3.5 align-top">
                                                                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $child['descr'] ?: 'Phase 2 Subnet' }}</div>
                                                                            @if(!empty($child['p2_id']))
                                                                                <a href="{{ route('vpn.ipsec.phase2', [$firewall, $sa['p1_id'] ?? $child['p2_id']]) }}" class="inline-flex items-center text-[10px] text-indigo-600 dark:text-indigo-400 hover:text-indigo-800 dark:hover:text-indigo-300 font-medium mt-0.5 transition" title="{{ __('Edit Phase 2') }}">
                                                                                    <i class="fa-solid fa-pencil mr-1"></i>{{ __('Edit Phase 2') }}
                                                                                </a>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Local --}}
                                                                        <td class="py-2.5 px-3.5 font-mono align-top text-gray-800 dark:text-gray-200">
                                                                            @forelse($child['local_ts'] as $net)
                                                                                <div>{{ $net }}</div>
                                                                            @empty
                                                                                <span class="text-gray-400">Unknown</span>
                                                                            @endforelse
                                                                        </td>

                                                                        {{-- Child SPIs --}}
                                                                        <td class="py-2.5 px-3.5 font-mono align-top text-[11px] leading-relaxed">
                                                                            @if(!empty($child['spi_in']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">In:</span> {{ $child['spi_in'] }}</div>
                                                                            @endif
                                                                            @if(!empty($child['spi_out']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Out:</span> {{ $child['spi_out'] }}</div>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Remote --}}
                                                                        <td class="py-2.5 px-3.5 font-mono align-top text-gray-800 dark:text-gray-200">
                                                                            @forelse($child['remote_ts'] as $net)
                                                                                <div>{{ $net }}</div>
                                                                            @empty
                                                                                <span class="text-gray-400">Unknown</span>
                                                                            @endforelse
                                                                        </td>

                                                                        {{-- Child Times --}}
                                                                        <td class="py-2.5 px-3.5 font-mono align-top text-[11px] leading-relaxed">
                                                                            @if(!empty($child['rekey_time']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Rekey:</span> {{ $child['rekey_time'] }}s ({{ $child['rekey_dhms'] }})</div>
                                                                            @endif
                                                                            @if(!empty($child['life_time']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Life:</span> {{ $child['life_time'] }}s ({{ $child['life_dhms'] }})</div>
                                                                            @endif
                                                                            @if(!empty($child['install_time']))
                                                                                <div><span class="font-semibold font-sans text-gray-700 dark:text-gray-300">Installed:</span> {{ $child['install_time'] }}s ({{ $child['install_dhms'] }})</div>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Algorithms --}}
                                                                        <td class="py-2.5 px-3.5 font-mono align-top text-[11px] space-y-0.5">
                                                                            @if(!empty($child['encr_alg']))
                                                                                <div class="font-medium text-gray-800 dark:text-gray-200">{{ $child['encr_alg'] }}{{ !empty($child['encr_keysize']) ? ' (' . $child['encr_keysize'] . ')' : '' }}</div>
                                                                            @endif
                                                                            @if(!empty($child['integ_alg']))
                                                                                <div class="text-gray-600 dark:text-gray-400">{{ $child['integ_alg'] }}</div>
                                                                            @endif
                                                                            @if(!empty($child['dh_group']))
                                                                                <div class="text-gray-500 dark:text-gray-400">{{ $child['dh_group'] }}</div>
                                                                            @endif
                                                                            @if(!empty($child['ipcomp']))
                                                                                <div class="text-gray-500 dark:text-gray-400">IPComp: {{ $child['ipcomp'] }}</div>
                                                                            @endif
                                                                        </td>

                                                                        {{-- Child Stats --}}
                                                                        <td class="py-2.5 px-3.5 font-mono align-top text-[11px] leading-relaxed">
                                                                            <div>
                                                                                <span class="text-gray-500 dark:text-gray-400">In:</span>
                                                                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $child['bytes_in_formatted'] }}</span>
                                                                                <span class="text-[10px] text-gray-400">({{ $child['packets_in'] }} pkts)</span>
                                                                            </div>
                                                                            <div>
                                                                                <span class="text-gray-500 dark:text-gray-400">Out:</span>
                                                                                <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $child['bytes_out_formatted'] }}</span>
                                                                                <span class="text-[10px] text-gray-400">({{ $child['packets_out'] }} pkts)</span>
                                                                            </div>
                                                                        </td>

                                                                        {{-- Child Status & Action --}}
                                                                        <td class="py-2.5 px-3.5 align-top">
                                                                            @php
                                                                                $cState = strtoupper($child['state'] ?? 'INSTALLED');
                                                                            @endphp
                                                                            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80">
                                                                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                                                {{ ucfirst(strtolower($cState)) }}
                                                                            </span>

                                                                            @if(!auth()->user()->isReadOnly() && $cState === 'INSTALLED')
                                                                                <form method="POST" action="{{ route('status.ipsec.disconnect', $firewall) }}" class="mt-2">
                                                                                    @csrf
                                                                                    <input type="hidden" name="type" value="p2">
                                                                                    <input type="hidden" name="name" value="{{ $child['name'] }}">
                                                                                    <input type="hidden" name="uniqueid" value="{{ $child['uniqueid'] }}">
                                                                                    <button type="submit" class="inline-flex items-center gap-1 px-2 py-0.5 text-[10px] font-medium text-amber-800 bg-amber-50 hover:bg-amber-100 dark:bg-amber-950/40 dark:text-amber-300 dark:hover:bg-amber-900/60 rounded border border-amber-200 dark:border-amber-800/60 transition shadow-2xs" onclick="return confirm('Disconnect Child SA {{ $child['name'] }}?');">
                                                                                        <i class="fa-solid fa-power-off text-[9px] text-amber-600 dark:text-amber-400"></i>{{ __('Disconnect P2') }}
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
                                                    <div class="text-xs text-gray-500 dark:text-gray-400 italic flex items-center gap-1.5">
                                                        <i class="fa-solid fa-info-circle text-slate-400"></i>
                                                        {{ __('No active child SA entries for this tunnel.') }}
                                                    </div>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-12 px-4 text-center text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center justify-center space-y-2">
                                                    <i class="fa-solid fa-network-wired text-3xl text-slate-300 dark:text-slate-600"></i>
                                                    <div class="font-medium text-slate-600 dark:text-slate-300">{{ __('No IPsec SAs found.') }}</div>
                                                    <div class="text-xs text-slate-400">{{ __('Tunnels will appear here once configured and initiated.') }}</div>
                                                </div>
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
                            <div class="overflow-x-auto relative shadow-sm rounded-xl border border-gray-200 dark:border-gray-700 mb-6">
                                <div class="px-4 py-3 bg-gray-50/80 dark:bg-gray-700/70 text-gray-800 dark:text-gray-200 text-sm font-semibold border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                    <i class="fa-solid fa-server text-indigo-500"></i>
                                    {{ __('IPsec Address Pools') }}
                                </div>
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
                                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
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

                        <div class="overflow-x-auto relative shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                            <div class="px-4 py-3 bg-gray-50/80 dark:bg-gray-700/70 text-gray-800 dark:text-gray-200 text-sm font-semibold border-b border-gray-200 dark:border-gray-700 flex items-center gap-2">
                                <i class="fa-solid fa-users text-indigo-500"></i>
                                {{ __('Active Mobile Client Leases') }}
                            </div>
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
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                            <td class="py-3 px-4 font-mono">{{ $lease['id'] ?? ($lease['user'] ?? '-') }}</td>
                                            <td class="py-3 px-4 font-mono">{{ $lease['host'] ?? ($lease['remote_host'] ?? '-') }}</td>
                                            <td class="py-3 px-4 font-mono font-medium text-gray-900 dark:text-gray-100">{{ $lease['address'] ?? ($lease['ip'] ?? '-') }}</td>
                                            <td class="py-3 px-4">
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/80">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                                                    {{ ucfirst($lease['status'] ?? 'Online') }}
                                                </span>
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="py-12 px-4 text-center text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center justify-center space-y-2">
                                                    <i class="fa-solid fa-address-book text-3xl text-slate-300 dark:text-slate-600"></i>
                                                    <div class="font-medium text-slate-600 dark:text-slate-300">{{ __('No IPsec mobile client leases found.') }}</div>
                                                    <div class="text-xs text-slate-400">{{ __('Active mobile clients will appear here.') }}</div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- TAB 3: SADS --}}
                    @if($tab === 'sads')
                        <div class="overflow-x-auto relative shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50/80 dark:bg-gray-700/70 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 font-semibold">
                                    <tr>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Source') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Destination') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Protocol') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('SPI') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Enc. alg.') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Auth. alg.') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Data') }}</th>
                                        <th scope="col" class="py-3.5 px-4 text-right">{{ __('Actions') }}</th>
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
                                                        <button type="submit" class="text-rose-600 hover:text-rose-900 dark:text-rose-400 dark:hover:text-rose-300 p-1 transition" title="{{ __('Delete SAD entry') }}">
                                                            <i class="fa-solid fa-trash-can"></i>
                                                        </button>
                                                    </form>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="8" class="py-12 px-4 text-center text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center justify-center space-y-2">
                                                    <i class="fa-solid fa-shield-halved text-3xl text-slate-300 dark:text-slate-600"></i>
                                                    <div class="font-medium text-slate-600 dark:text-slate-300">{{ __('No IPsec security associations (SAD entries) found.') }}</div>
                                                    <div class="text-xs text-slate-400">{{ __('Security associations will be listed when active.') }}</div>
                                                </div>
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif

                    {{-- TAB 4: SPDS --}}
                    @if($tab === 'spds')
                        <div class="overflow-x-auto relative shadow-sm rounded-xl border border-gray-200 dark:border-gray-700">
                            <table class="w-full text-sm text-left text-gray-600 dark:text-gray-300">
                                <thead class="text-xs text-gray-700 uppercase bg-gray-50/80 dark:bg-gray-700/70 dark:text-gray-300 border-b border-gray-200 dark:border-gray-700 font-semibold">
                                    <tr>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Mode') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Source') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Destination') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Direction') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Protocol') }}</th>
                                        <th scope="col" class="py-3.5 px-4">{{ __('Tunnel Endpoints') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700 bg-white dark:bg-gray-800">
                                    @forelse($spds as $sp)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/50 transition">
                                            <td class="py-3 px-4 text-xs font-semibold text-gray-900 dark:text-gray-100">
                                                @if(($sp['scope'] ?? '') === 'ifnet')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">VTI {{ $sp['ifname'] ?? '' }}</span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 dark:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-slate-600">Tunnel</span>
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
                                                    <span class="inline-flex items-center text-emerald-600 dark:text-emerald-400 font-medium">
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
                                            <td colspan="6" class="py-12 px-4 text-center text-gray-500 dark:text-gray-400">
                                                <div class="flex flex-col items-center justify-center space-y-2">
                                                    <i class="fa-solid fa-filter text-3xl text-slate-300 dark:text-slate-600"></i>
                                                    <div class="font-medium text-slate-600 dark:text-slate-300">{{ __('No IPsec security policies (SPD entries) configured.') }}</div>
                                                    <div class="text-xs text-slate-400">{{ __('Security policies will be listed when active.') }}</div>
                                                </div>
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
