<div class="mb-4 rounded-md border border-gray-200 dark:border-gray-700 p-4">
    <label for="tls_certificate" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Trust a native or self-signed HTTPS certificate</label>
    <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Export the firewall's current HTTPS server certificate using a trusted administrator connection, then upload its public PEM/CRT file here. AdmixCentral will remember its public key and refuse connections if that key changes. No public certificate authority is required. Never upload a private key.</p>
    <input type="file" name="tls_certificate" id="tls_certificate" accept=".pem,.crt,.cer"
        class="block w-full text-sm text-gray-700 dark:text-gray-300">
    @error('tls_certificate')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
    <details class="mt-3" @if($errors->has('tls_public_key_pin')) open @endif>
        <summary class="text-xs cursor-pointer text-gray-600 dark:text-gray-400">Advanced: trusted public-key fingerprint</summary>
        <input type="text" name="tls_public_key_pin" id="tls_public_key_pin"
            value="{{ old('tls_public_key_pin', $firewall->tls_public_key_pin ?? '') }}"
            placeholder="sha256//…=" spellcheck="false"
            class="mt-2 w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-xs font-mono">
        @error('tls_public_key_pin')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">A certificate upload replaces this value. Leave both empty to use the system or configured private CA trust store. Changing trust on an existing firewall requires re-entering its API credentials. Certificates renewed with the same key continue to work.</p>
    </details>
</div>
