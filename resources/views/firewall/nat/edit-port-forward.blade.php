<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Port Forward Rule') }} - {{ $firewall->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(auth()->user()->isReadOnly())
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 space-y-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Rule Details</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Interface</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ strtoupper($rule['interface'] ?? '—') }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Protocol</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ strtoupper($rule['protocol'] ?? '—') }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Source Address</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $rule['src'] ?? 'any' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Destination Address</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $rule['dst'] ?? 'any' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Destination Port</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $rule['dstport'] ?? '—' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Redirect Target IP</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $rule['target'] ?? '—' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Redirect Target Port</dt>
                            <dd class="text-sm font-mono text-gray-900 dark:text-gray-100">{{ $rule['local-port'] ?? '—' }}</dd>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg px-4 py-3">
                            <dt class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-1">Description</dt>
                            <dd class="text-sm text-gray-900 dark:text-gray-100">{{ $rule['descr'] ?? '—' }}</dd>
                        </div>
                    </dl>

                    <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                        <a href="{{ route('firewall.nat.port-forward', $firewall) }}"
                            class="inline-flex items-center gap-1.5 text-sm text-indigo-600 dark:text-indigo-400 hover:underline">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                            </svg>
                            Back to Port Forward Rules
                        </a>
                    </div>
                </div>
            </div>

            @else
            {{-- ═══════════════════════════════════════════
                 EDITABLE FORM — normal users and admins
            ═══════════════════════════════════════════ --}}
            @if(session('error'))
                <div class="pf-alert pf-alert-error mb-4">
                    {{ session('error') }}
                </div>
            @endif

            @if($errors->any())
                <div class="pf-alert pf-alert-error mb-4">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <form action="{{ route('firewall.nat.port-forward.store', $firewall) }}" method="POST">
                    @csrf

                    <div class="pf-form-container">
                        <div class="pf-form-grid">
                            {{-- Interface --}}
                            <div>
                                <label for="interface" class="pf-label">Interface</label>
                                <select name="interface" id="interface" class="pf-select" required>
                                    @foreach($interfaces as $ifName => $iface)
                                        @php $ifVal = $iface['name'] ?? (is_string($ifName) ? $ifName : ($iface['id'] ?? $iface['if'])); @endphp
                                        <option value="{{ $ifVal }}" {{ ($rule['interface'] ?? '') == $ifVal ? 'selected' : '' }}>
                                            {{ $iface['descr'] ?? strtoupper($ifVal) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Protocol --}}
                            <div>
                                <label for="protocol" class="pf-label">Protocol</label>
                                <select name="protocol" id="protocol" class="pf-select" required>
                                    <option value="tcp" {{ ($rule['protocol'] ?? 'tcp') === 'tcp' ? 'selected' : '' }}>TCP</option>
                                    <option value="udp" {{ ($rule['protocol'] ?? '') === 'udp' ? 'selected' : '' }}>UDP</option>
                                    <option value="tcp/udp" {{ ($rule['protocol'] ?? '') === 'tcp/udp' ? 'selected' : '' }}>TCP/UDP</option>
                                </select>
                            </div>

                            {{-- Source --}}
                            <div class="pf-form-field-full">
                                <h3 class="pf-section-header">Source</h3>
                            </div>
                            <div class="pf-form-field-full">
                                <label for="src" class="pf-label">Source Address</label>
                                <input type="text" name="src" id="src" value="{{ $rule['src'] ?? 'any' }}"
                                    class="pf-input" placeholder="any, IP address, or alias">
                                <p class="pf-help-text">Use 'any' for internet traffic.</p>
                            </div>

                            {{-- Destination --}}
                            <div class="pf-form-field-full">
                                <h3 class="pf-section-header">Destination</h3>
                            </div>
                            <div>
                                <label for="dst" class="pf-label">Dest. Address</label>
                                <input type="text" name="dst" id="dst" value="{{ $rule['dst'] ?? 'any' }}"
                                    class="pf-input" placeholder="WAN Address, IP, or alias">
                            </div>
                            <div>
                                <label for="dstport" class="pf-label">Dest. Port Range</label>
                                <input type="text" name="dstport" id="dstport" value="{{ $rule['dstport'] ?? '' }}"
                                    class="pf-input" placeholder="80, 443, or 8000-8010" required>
                            </div>

                            {{-- Redirect Target --}}
                            <div class="pf-form-field-full">
                                <h3 class="pf-section-header">Redirect Target IP</h3>
                            </div>
                            <div>
                                <label for="target" class="pf-label">Redirect Target IP</label>
                                <input type="text" name="target" id="target" value="{{ $rule['target'] ?? '' }}"
                                    class="pf-input" placeholder="Internal IP address (e.g. 192.168.1.50)" required>
                            </div>
                            <div>
                                <label for="local_port" class="pf-label">Redirect Target Port</label>
                                <input type="text" name="local_port" id="local_port"
                                    value="{{ $rule['local-port'] ?? '' }}" class="pf-input"
                                    placeholder="Port (e.g. 80)" required>
                            </div>

                            {{-- Extra Options --}}
                            <div class="pf-form-field-full">
                                <h3 class="pf-section-header">Extra Options</h3>
                            </div>
                            <div class="pf-form-field-full">
                                <label for="descr" class="pf-label">Description</label>
                                <input type="text" name="descr" id="descr" value="{{ $rule['descr'] ?? '' }}"
                                    class="pf-input" placeholder="Description for this rule">
                            </div>
                            <div class="pf-form-field-full">
                                <label for="associated_rule" class="pf-label">Filter Rule Association</label>
                                <select name="associated_rule" id="associated_rule" class="pf-select">
                                    <option value="pass" {{ ($rule['associated-rule-id'] ?? 'pass') === 'pass' ? 'selected' : '' }}>Add associated filter rule</option>
                                    <option value="none" {{ ($rule['associated-rule-id'] ?? '') === 'none' ? 'selected' : '' }}>None</option>
                                </select>
                                <p class="pf-help-text">This will automatically create a firewall rule to allow this traffic.</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6 border-t border-gray-200 dark:border-gray-700 mt-6">
                            <a href="{{ route('firewall.nat.port-forward', $firewall) }}"
                                class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                                Cancel
                            </a>
                            <button type="submit" class="pf-btn pf-btn-primary">
                                Save Rule
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            @endif

        </div>
    </div>
</x-app-layout>
