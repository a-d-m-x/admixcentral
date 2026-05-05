@props(['columns' => [], 'buttonClass' => ''])

{{--
    Column Selector Dropdown
    ─────────────────────────────────────────────────────────────────────
    Reusable component that must live within an Alpine x-data scope that
    has spread window.columnSelectorMixin(). It reads and writes:
      • cols           — {[key]: bool} visibility map
      • colSelectorOpen — open/close state for this dropdown
      • hiddenColCount  — computed count of hidden columns (for badge)
      • toggleCol(key) — flip a column's visibility
      • resetColumns() — restore all to defaults

    Usage:
      <x-column-selector :columns="[
          ['key' => 'host',    'label' => 'Host'],
          ['key' => 'address', 'label' => 'Address'],
      ]" />
--}}
<div class="relative shrink-0" @click.outside="colSelectorOpen = false">

    {{-- Trigger Button --}}
    <button
        type="button"
        @click="colSelectorOpen = !colSelectorOpen"
        :class="colSelectorOpen ? 'ring-2 ring-indigo-500' : ''"
        class="relative inline-flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-600 transition-colors focus:outline-none {{ $buttonClass }}"
        title="Show / hide columns"
    >
        {{-- Columns icon --}}
        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 0v10"/>
        </svg>
        <span class="hidden sm:inline">Columns</span>

        {{-- Hidden-count badge --}}
        <span
            x-show="hiddenColCount > 0"
            x-text="hiddenColCount"
            class="flex items-center justify-center font-bold bg-indigo-600 text-white rounded-full leading-none"
            style="display:none; position:absolute; top:-6px; right:-6px; min-width:1rem; width:auto; height:1rem; padding:0 3px; font-size:10px;"
        ></span>
    </button>

    {{-- Dropdown Panel --}}
    <div
        x-show="colSelectorOpen"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        class="absolute right-0 mt-2 w-52 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-200 dark:border-gray-700 z-[200] overflow-hidden"
        style="display:none;"
    >
        <p class="px-4 py-2.5 text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-750">
            Show / Hide Columns
        </p>

        <div class="py-1">
            @foreach($columns as $col)
                @unless($col['always'] ?? false)
                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 dark:hover:bg-gray-700 cursor-pointer transition-colors group">
                    <input
                        type="checkbox"
                        :checked="cols['{{ $col['key'] }}']"
                        @change="toggleCol('{{ $col['key'] }}')"
                        class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 focus:ring-indigo-500 cursor-pointer"
                    >
                    <span class="text-sm text-gray-700 dark:text-gray-300 group-hover:text-gray-900 dark:group-hover:text-gray-100 select-none">
                        {{ $col['label'] }}
                    </span>
                </label>
                @endunless
            @endforeach
        </div>

        <div class="border-t border-gray-100 dark:border-gray-700 px-4 py-2.5 bg-gray-50 dark:bg-gray-750 flex justify-between items-center">
            <span class="text-xs text-gray-400" x-text="hiddenColCount === 0 ? 'All columns visible' : hiddenColCount + ' hidden'"></span>
            <button
                type="button"
                @click="resetColumns()"
                x-show="hiddenColCount > 0"
                class="text-xs text-indigo-600 dark:text-indigo-400 hover:underline font-medium"
                style="display:none;"
            >
                Reset
            </button>
        </div>
    </div>
</div>
