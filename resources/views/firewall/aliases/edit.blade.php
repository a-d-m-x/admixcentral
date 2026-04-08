<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ isset($alias['id']) ? __('Firewall Alias') : __('Add Firewall Alias') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(auth()->user()->isReadOnly())

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-6">

                    {{-- Alias Properties --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Alias Properties</h3>
                        <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Name</dt>
                                <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $alias['name'] ?? '—' }}</dd>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Type</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">
                                    @php
                                        $typeLabels = [
                                            'host'     => 'Host(s)',
                                            'network'  => 'Network(s)',
                                            'port'     => 'Port(s)',
                                            'url'      => 'URL (IPs)',
                                            'urltable' => 'URL Table (IPs)',
                                        ];
                                    @endphp
                                    {{ $typeLabels[$alias['type'] ?? ''] ?? ($alias['type'] ?? '—') }}
                                </dd>
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                                <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Description</dt>
                                <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $alias['descr'] ?? '—' }}</dd>
                            </div>
                        </dl>
                    </div>

                    {{-- Entries Table --}}
                    <div>
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Entries</h3>
                        @php
                            $addresses = $alias['address'] ?? [];
                            $details   = $alias['detail']  ?? [];
                        @endphp
                        @if(count($addresses))
                        <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider w-8">#</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Value</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 dark:text-gray-300 uppercase tracking-wider">Description</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-100 dark:divide-gray-700">
                                    @foreach($addresses as $i => $addr)
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700/40">
                                        <td class="px-4 py-2.5 text-gray-400 dark:text-gray-500 font-mono text-xs">{{ $i + 1 }}</td>
                                        <td class="px-4 py-2.5 font-mono text-gray-900 dark:text-gray-100">{{ $addr }}</td>
                                        <td class="px-4 py-2.5 text-gray-500 dark:text-gray-400">{{ $details[$i] ?? '' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        @else
                            <p class="text-sm text-gray-500 dark:text-gray-400 italic">No entries defined.</p>
                        @endif
                    </div>

                    {{-- Back link --}}
                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('firewall.aliases.index', $firewall) }}"
                            class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back to Aliases
                        </a>
                    </div>

                </div>
            </div>

            @else
            {{-- ═══════════════════════════════════════════
                 EDITABLE FORM — normal users and admins
            ═══════════════════════════════════════════ --}}
            @if(session('error'))
                <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form
                    action="{{ isset($alias['id']) ? route('firewall.aliases.update', [$firewall, $alias['id']]) : route('firewall.aliases.store', $firewall) }}"
                    method="POST"
                    x-data="aliasForm({{ json_encode($alias['address'] ?? ['']) }}, {{ json_encode($alias['detail'] ?? ['']) }})">
                    @csrf
                    @if(isset($alias['id']))
                        @method('PUT')
                    @endif

                    <div class="p-6 space-y-6">
                        {{-- Alias Properties --}}
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Alias Properties</h3>

                            {{-- Name --}}
                            <div class="mb-4">
                                <label for="name" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" id="name" value="{{ old('name', $alias['name'] ?? '') }}"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300 {{ isset($alias['id']) ? 'bg-gray-100 text-gray-500 cursor-not-allowed' : '' }}"
                                    required pattern="[a-zA-Z0-9_]+" maxlength="255"
                                    placeholder="Alphanumeric and underscore only"
                                    {{ isset($alias['id']) ? 'readonly' : '' }}>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">The name may only consist of
                                    letters, numbers, and underscores.</p>
                            </div>

                            {{-- Description --}}
                            <div class="mb-4">
                                <label for="descr"
                                    class="block font-medium text-sm text-gray-700 dark:text-gray-300">Description</label>
                                <input type="text" name="descr" id="descr"
                                    value="{{ old('descr', $alias['descr'] ?? '') }}"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300"
                                    placeholder="Description for this alias">
                            </div>

                            {{-- Type --}}
                            <div class="mb-4">
                                <label for="type" class="block font-medium text-sm text-gray-700 dark:text-gray-300">
                                    Type <span class="text-red-500">*</span>
                                </label>
                                <select name="type" id="type"
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300"
                                    required>
                                    <option value="host" {{ old('type', $alias['type'] ?? 'host') === 'host' ? 'selected' : '' }}>Host(s)</option>
                                    <option value="network" {{ old('type', $alias['type'] ?? '') === 'network' ? 'selected' : '' }}>Network(s)</option>
                                    <option value="port" {{ old('type', $alias['type'] ?? '') === 'port' ? 'selected' : '' }}>Port(s)</option>
                                    <option value="url" {{ old('type', $alias['type'] ?? '') === 'url' ? 'selected' : '' }}>URL (IPs)</option>
                                    <option value="urltable" {{ old('type', $alias['type'] ?? '') === 'urltable' ? 'selected' : '' }}>URL Table (IPs)</option>
                                </select>
                            </div>
                        </div>

                        {{-- Entries --}}
                        <div>
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Entries</h3>

                            <template x-for="(entry, index) in entries" :key="index">
                                <div class="flex gap-2 mb-2">
                                    <div class="flex-grow">
                                        <input type="text" :name="'address[' + index + ']'"
                                            x-model="entries[index].address"
                                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300"
                                            placeholder="IP Address, CIDR, Port, or URL" required>
                                    </div>
                                    <div class="flex-grow">
                                        <input type="text" :name="'detail[' + index + ']'"
                                            x-model="entries[index].detail"
                                            class="block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-600 dark:text-gray-300"
                                            placeholder="Description (optional)">
                                    </div>
                                    <button type="button" @click="removeEntry(index)" x-show="entries.length > 1"
                                        class="px-3 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                </div>
                            </template>

                            <button type="button" @click="addEntry()"
                                class="mt-2 inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M12 4v16m8-8H4"></path>
                                </svg>
                                Add Entry
                            </button>
                        </div>

                        {{-- Actions --}}
                        <div class="flex items-center justify-between pt-4 border-t border-gray-200 dark:border-gray-700">
                            <a href="{{ route('firewall.aliases.index', $firewall) }}"
                                class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                                Cancel
                            </a>
                            <button type="submit"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M5 13l4 4L19 7"></path>
                                </svg>
                                Save
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endif

        </div>
    </div>

    <script>
        function aliasForm(addresses, details) {
            const maxLength = Math.max(addresses.length, details.length, 1);
            const paddedAddresses = [...addresses, ...Array(maxLength - addresses.length).fill('')];
            const paddedDetails = [...details, ...Array(maxLength - details.length).fill('')];

            return {
                entries: paddedAddresses.map((addr, idx) => ({
                    address: addr,
                    detail: paddedDetails[idx] || ''
                })),

                addEntry() {
                    this.entries.push({ address: '', detail: '' });
                },

                removeEntry(index) {
                    if (this.entries.length > 1) {
                        this.entries.splice(index, 1);
                    }
                }
            }
        }
    </script>
</x-app-layout>
