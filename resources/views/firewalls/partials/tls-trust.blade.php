<div class="rounded-lg border border-gray-200 dark:border-gray-700 p-4"
     x-data="{
         fileName: '',
         dragging: false,
         pick(files) {
             if (!files || !files.length) return;
             this.fileName = files[0].name;
         },
         clear() {
             this.fileName = '';
             this.$refs.certInput.value = '';
         }
     }">
    <div class="flex items-start justify-between mb-1">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">
            Trust a native or self-signed HTTPS certificate
        </label>
        @if(!empty($firewall->tls_public_key_pin))
            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-400 text-[10px] font-medium shrink-0 ml-3">
                <svg class="w-2.5 h-2.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                Cert enrolled
            </span>
        @endif
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Export the firewall's current HTTPS server certificate using a trusted administrator connection, then upload its public PEM/CRT file here. AdmixCentral will remember its public key and refuse connections if that key changes. No certificate authority is required. Never upload a private key.</p>

    {{-- Drop zone --}}
    <div class="relative"
         @dragover.prevent="dragging = true"
         @dragleave.prevent="dragging = false"
         @drop.prevent="dragging = false; pick($event.dataTransfer.files); $refs.certInput.files = $event.dataTransfer.files">

        <div :class="dragging ? 'border-indigo-400 bg-indigo-50 dark:bg-indigo-950/30' : 'border-gray-300 dark:border-gray-600 hover:border-indigo-400 dark:hover:border-indigo-500'"
             class="flex items-center gap-3 rounded-lg border-2 border-dashed px-4 py-3 transition-colors cursor-pointer"
             @click="$refs.certInput.click()">
            <svg class="w-5 h-5 text-gray-400 dark:text-gray-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <div class="flex-1 min-w-0">
                <p x-show="!fileName" class="text-sm text-gray-500 dark:text-gray-400">
                    <span class="font-medium text-indigo-600 dark:text-indigo-400">Click to upload</span> or drag &amp; drop a <span class="font-mono text-xs">.pem</span> / <span class="font-mono text-xs">.crt</span> file
                </p>
                <p x-show="fileName" class="text-sm font-medium text-gray-800 dark:text-gray-200 truncate" x-text="fileName"></p>
            </div>
            <button x-show="fileName" type="button"
                    @click.stop="clear()"
                    class="text-gray-400 hover:text-red-500 transition-colors shrink-0"
                    title="Remove selected file">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <input type="file" name="tls_certificate" id="tls_certificate"
               accept=".pem,.crt,.cer"
               x-ref="certInput"
               class="sr-only"
               @change="pick($event.target.files)">
    </div>

    @error('tls_certificate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror

    {{-- Advanced fingerprint collapsible --}}
    <div class="mt-3" x-data="{ open: {{ $errors->has('tls_public_key_pin') ? 'true' : 'false' }} }">
        <button type="button"
                @click="open = !open"
                class="flex items-center gap-1 text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 transition-colors">
            <svg class="w-3 h-3 transition-transform duration-150" :class="open ? 'rotate-90' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
            </svg>
            Advanced: trusted public-key fingerprint
        </button>
        <div x-show="open" x-collapse class="mt-2">
            <input type="text" name="tls_public_key_pin" id="tls_public_key_pin"
                value="{{ old('tls_public_key_pin', $firewall->tls_public_key_pin ?? '') }}"
                placeholder="sha256//…=" spellcheck="false"
                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs font-mono">
            @error('tls_public_key_pin')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1.5">A certificate upload replaces this value. Leave both empty to use the system trust store. Changing trust requires re-entering API credentials. Certificates renewed with the same key continue to work.</p>
        </div>
    </div>
</div>
