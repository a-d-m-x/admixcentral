{{--
    Reusable confirm-delete modal component.

    Usage:
      Wrap your page content (or the section containing delete buttons) with this component.
      Replace any <form onsubmit="return confirm(...)"> delete buttons with:

        <button type="button"
                @click="openDelete('{{ route(...) }}', 'Item name or variable')"
                class="text-red-600 ...">Delete</button>

    The component provides an Alpine scope with:
      - modal.open / modal.url / modal.label
      - openDelete(url, label)  — opens the modal targeting the given POST/DELETE route
--}}
<div x-data="{
        modal: { open: false, url: '', label: '' },
        openDelete(url, label) {
            this.modal.url   = url;
            this.modal.label = label;
            this.modal.open  = true;
        }
     }"
     @keydown.escape.window="modal.open = false">

    {{ $slot }}

    {{-- Modal overlay + panel --}}
    <div x-show="modal.open"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm"
             x-show="modal.open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @click="modal.open = false"></div>

        {{-- Panel --}}
        <div class="relative bg-white dark:bg-gray-800 rounded-xl shadow-xl max-w-md w-full p-6"
             x-show="modal.open"
             x-transition:enter="ease-out duration-200"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-150"
             x-transition:leave-start="opacity-100 scale-100"
             x-transition:leave-end="opacity-0 scale-95">

            <div class="flex items-start gap-4">
                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                    <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-900 dark:text-gray-100">Confirm deletion</h3>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                        Are you sure you want to delete
                        <span class="font-medium text-gray-700 dark:text-gray-300" x-text="modal.label"></span>?
                        This action cannot be undone.
                    </p>
                </div>
            </div>

            <form :action="modal.url" method="POST" class="mt-6 flex justify-end gap-3">
                @csrf
                @method('DELETE')
                <x-secondary-button type="button" @click="modal.open = false">
                    Cancel
                </x-secondary-button>
                <x-danger-button type="submit">
                    Delete
                </x-danger-button>
            </form>

        </div>
    </div>

</div>
