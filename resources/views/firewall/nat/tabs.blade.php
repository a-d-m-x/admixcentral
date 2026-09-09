<div class="border-b border-gray-200 dark:border-gray-700 mb-6">
    <nav class="-mb-px flex space-x-8 overflow-x-auto">
        <a href="{{ route('firewall.nat.port-forward', $firewall) }}"
            class="{{ $active === 'port-forward' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            {{ $firewall->isOpnSense() ? 'Port Forward (DNAT)' : 'Port Forward' }}
        </a>
        <a href="{{ route('firewall.nat.one-to-one', $firewall) }}"
            class="{{ $active === 'one-to-one' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            {{ $firewall->isOpnSense() ? '1:1 (BINAT)' : '1:1' }}
        </a>
        <a href="{{ route('firewall.nat.outbound', $firewall) }}"
            class="{{ $active === 'outbound' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
            {{ $firewall->isOpnSense() ? 'Outbound (SNAT)' : 'Outbound' }}
        </a>
    </nav>
</div>
