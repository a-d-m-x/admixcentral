<div class="flex justify-between items-center mb-4">
    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Revocation Lists</h3>
    @if(!auth()->user()->isReadOnly())
    <a href="{{ route('system.certificate_manager.crls.create', $firewall) }}" class="btn-primary">
        Create CRL
    </a>
    @endif
</div>

@if(empty($data['crls']))
    <p class="text-gray-500 italic">No Certificate Revocation Lists found.</p>
@else
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-900">
            <tr>
                <th scope="col"
                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Name</th>
                <th scope="col"
                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    CA</th>
                <th scope="col"
                    class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Entries</th>
                @if(!auth()->user()->isReadOnly())
                <th scope="col"
                    class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                    Actions</th>
                @endif
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($data['crls'] as $crl)
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                        {{ $crl['descr'] ?? 'Unknown' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ $crl['caref'] ?? 'N/A' }}</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                        {{ isset($crl['cert']) ? count($crl['cert']) : 0 }}</td>
                    @if(!auth()->user()->isReadOnly())
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <form
                            action="{{ route('system.certificate_manager.crls.destroy', [$firewall, $crl['refid'] ?? $loop->index]) }}"
                            method="POST" class="inline-block"
                            onsubmit="return confirm('Are you sure you want to delete this CRL?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit"
                                class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-200">Delete</button>
                        </form>
                    </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif
