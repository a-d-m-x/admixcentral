<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ isset($vlan['id']) ? __('VLAN') : __('Add VLAN') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(auth()->user()->isReadOnly())
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">VLAN Details</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Parent Interface</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $vlan['if'] ?? '—' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">VLAN Tag</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $vlan['tag'] ?? '—' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Priority (PCP)</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $vlan['pcp'] ?? '0 (Default)' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Description</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $vlan['descr'] ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('firewall.vlans.index', $firewall) }}"
                            class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back to VLANs
                        </a>
                    </div>
                </div>
            </div>

            @else
            {{-- ═══════════════════════════════════════════
                 EDITABLE FORM — normal users and admins
            ═══════════════════════════════════════════ --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST"
                        action="{{ isset($vlan['id']) ? route('firewall.vlans.update', [$firewall, $vlan['id']]) : route('firewall.vlans.store', $firewall) }}">
                        @csrf
                        @if(isset($vlan['id']))
                            @method('PUT')
                        @endif

                        @if ($errors->any())
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4"
                                role="alert">
                                <ul>
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Parent Interface -->
                        <div class="mb-4">
                            <label for="if" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Parent
                                Interface</label>
                            <select name="if" id="if"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Select Parent Interface</option>
                                @foreach ($interfaces as $interface)
                                    @php
                                        $ifName = $interface['if'] ?? $interface['id'] ?? 'unknown';
                                        $descr = $interface['descr'] ?? $ifName;
                                    @endphp
                                    <option value="{{ $ifName }}" {{ (old('if', $vlan['if'] ?? '') == $ifName) ? 'selected' : '' }}>
                                        {{ $descr }} ({{ $ifName }})
                                    </option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-sm text-gray-500">The interface that will carry the VLAN tags.</p>
                        </div>

                        <!-- VLAN Tag -->
                        <div class="mb-4">
                            <label for="tag" class="block text-sm font-medium text-gray-700 dark:text-gray-300">VLAN Tag</label>
                            <input type="number" name="tag" id="tag" min="1" max="4094"
                                value="{{ old('tag', $vlan['tag'] ?? '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"
                                required>
                            <p class="mt-2 text-sm text-gray-500">802.1Q VLAN Tag (between 1 and 4094).</p>
                        </div>

                        <!-- PCP -->
                        <div class="mb-4">
                            <label for="pcp" class="block text-sm font-medium text-gray-700 dark:text-gray-300">VLAN Priority (PCP)</label>
                            <select name="pcp" id="pcp"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="" {{ (old('pcp', $vlan['pcp'] ?? '') === '') ? 'selected' : '' }}>Default (0)</option>
                                @foreach(range(0, 7) as $p)
                                    <option value="{{ $p }}" {{ (old('pcp', $vlan['pcp'] ?? '') == $p && (old('pcp', $vlan['pcp'] ?? '') !== '')) ? 'selected' : '' }}>{{ $p }}</option>
                                @endforeach
                            </select>
                            <p class="mt-2 text-sm text-gray-500">802.1Q VLAN Priority Code Point.</p>
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <label for="descr" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description</label>
                            <input type="text" name="descr" id="descr" value="{{ old('descr', $vlan['descr'] ?? '') }}"
                                class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            <p class="mt-2 text-sm text-gray-500">You may enter a description here for your reference.</p>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <a href="{{ route('firewall.vlans.index', $firewall) }}"
                                class="text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-gray-200 mr-4">Cancel</a>
                            <button type="submit"
                                class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded">
                                {{ isset($vlan['id']) ? 'Update VLAN' : 'Add VLAN' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
