<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('System Backups') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

            <!-- Actions -->
            <div class="flex justify-end">
                <button type="button" @click="$dispatch('open-modal', 'create-backup-modal')"
                    class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Create New Backup
                </button>
            </div>

            <!-- Backups List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('success'))
                        <div class="mb-4 p-4 rounded-md bg-green-50 text-green-700 dark:bg-green-900/50 dark:text-green-300">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 p-4 rounded-md bg-red-50 text-red-700 dark:bg-red-900/50 dark:text-red-300">
                            {{ session('error') }}
                        </div>
                    @endif

                     @if($errors->any())
                        <div class="mb-4 p-4 rounded-md bg-red-50 text-red-700 dark:bg-red-900/50 dark:text-red-300">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Filename</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Size</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Created At</th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($backups as $backup)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $backup['name'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ number_format($backup['size'] / 1024, 2) }} KB
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ \Carbon\Carbon::createFromTimestamp($backup['last_modified'])->format('M d, Y H:i:s') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-3">
                                            <a href="{{ route('system.backups.download', $backup['name']) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Download</a>
                                            
                                            <button type="button" @click="$dispatch('open-modal', 'restore-backup-modal-{{ md5($backup['name']) }}')" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">Restore</button>
                                            
                                            <form action="{{ route('system.backups.destroy', $backup['name']) }}" method="POST" class="inline" @submit.prevent="
                                                Swal.fire({
                                                    title: 'Are you sure?',
                                                    text: 'You will not be able to recover this backup!',
                                                    icon: 'warning',
                                                    showCancelButton: true,
                                                    confirmButtonColor: '#d33',
                                                    cancelButtonColor: '#3085d6',
                                                    confirmButtonText: 'Yes, delete it!'
                                                }).then((result) => {
                                                    if (result.isConfirmed) {
                                                        $el.submit();
                                                    }
                                                })
                                            ">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Delete</button>
                                            </form>

                                            <!-- Restore Modal for this file -->
                                            <x-modal name="restore-backup-modal-{{ md5($backup['name']) }}" focusable maxWidth="md">
                                                <form method="POST" action="{{ route('system.backups.restore') }}" class="p-6">
                                                    @csrf
                                                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                                                        {{ __('Restore System Backup') }}
                                                    </h2>
                                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                        {{ __('Enter the decryption password to restore from: ') }} <strong>{{ $backup['name'] }}</strong>
                                                        <br>
                                                        <span class="text-red-500 font-bold">WARNING: This will overwrite current system data!</span>
                                                    </p>
                                                    <input type="hidden" name="local_filename" value="{{ $backup['name'] }}">
                                                    
                                                    <div class="mt-6">
                                                        <x-input-label for="password_restore_{{ md5($backup['name']) }}" value="{{ __('Decryption Password') }}" />
                                                        <x-text-input id="password_restore_{{ md5($backup['name']) }}" name="password" type="password" class="mt-1 block w-full" placeholder="Enter password" required />
                                                    </div>

                                                    <div class="mt-4 space-y-2">
                                                        <div class="flex items-center">
                                                            <input id="exclude_global_admins_{{ md5($backup['name']) }}" name="exclude_global_admins" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                            <label for="exclude_global_admins_{{ md5($backup['name']) }}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                                                Exclude Global Admins (Keep current)
                                                            </label>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <input id="exclude_end_users_{{ md5($backup['name']) }}" name="exclude_end_users" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                            <label for="exclude_end_users_{{ md5($backup['name']) }}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                                                Exclude End Users & Company Admins (Keep current)
                                                            </label>
                                                        </div>
                                                        <div class="flex items-center">
                                                            <input id="exclude_hostname_{{ md5($backup['name']) }}" name="exclude_hostname" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                                            <label for="exclude_hostname_{{ md5($backup['name']) }}" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                                                Exclude Hostname/SSL (Keep current settings)
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-6 flex justify-end">
                                                        <x-secondary-button x-on:click="$dispatch('close')">
                                                            {{ __('Cancel') }}
                                                        </x-secondary-button>
                                                        <x-primary-button class="ml-3">
                                                            {{ __('Restore') }}
                                                        </x-primary-button>
                                                    </div>
                                                </form>
                                            </x-modal>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">
                                            No system backups found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                 <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Restore from Upload</h3>
                 <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                     <form method="POST" action="{{ route('system.backups.restore') }}" enctype="multipart/form-data" class="space-y-4" @submit.prevent="
                        Swal.fire({
                            title: 'Are you sure?',
                            text: 'Starting a restore will overwrite system data! This action cannot be undone.',
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d33',
                            cancelButtonColor: '#3085d6',
                            confirmButtonText: 'Yes, restore it!'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                $el.submit();
                            }
                        })
                     ">
                        @csrf
                        <div>
                            <x-input-label for="backup_file" value="{{ __('Backup File (.enc)') }}" />
                            <input type="file" name="backup_file" id="backup_file" class="mt-1 block w-full text-sm text-gray-900 border border-gray-300 rounded-lg cursor-pointer bg-gray-50 dark:text-gray-400 focus:outline-none dark:bg-gray-700 dark:border-gray-600 dark:placeholder-gray-400" accept=".enc" required>
                        </div>
                        <div>
                             <x-input-label for="password_upload" value="{{ __('Decryption Password') }}" />
                             <x-text-input id="password_upload" name="password" type="password" class="mt-1 block w-full" placeholder="Enter password" required />
                        </div>

                        <div class="mt-4 space-y-2">
                            <div class="flex items-center">
                                <input id="exclude_global_admins_upload" name="exclude_global_admins" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="exclude_global_admins_upload" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                    Exclude Global Admins (Keep current)
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input id="exclude_end_users_upload" name="exclude_end_users" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="exclude_end_users_upload" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                    Exclude End Users & Company Admins (Keep current)
                                </label>
                            </div>
                            <div class="flex items-center">
                                <input id="exclude_hostname_upload" name="exclude_hostname" type="checkbox" value="1" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                                <label for="exclude_hostname_upload" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                    Exclude Hostname/SSL (Keep current settings)
                                </label>
                            </div>
                        </div>
                         <div class="flex justify-end">
                             <x-primary-button>
                                {{ __('Upload & Restore') }}
                            </x-primary-button>
                        </div>
                     </form>
                 </div>
            </div>

        </div>
    </div>


    <!-- Create Backup Modal -->
    <x-modal name="create-backup-modal" focusable maxWidth="md">
        <form method="POST" action="{{ route('system.backups.store') }}" class="p-6">
            @csrf
            <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                {{ __('Create System Backup') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ __('Please set a password to encrypt this backup. You will need this password to restore it later.') }}
            </p>
            
            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Encryption Password') }}" />
                <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" placeholder="Enter password" required />
            </div>

            <div class="mt-4">
                <x-input-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" placeholder="Confirm password" required />
            </div>
            
            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancel') }}
                </x-secondary-button>
                <x-primary-button class="ml-3">
                    {{ __('Create Backup') }}
                </x-primary-button>
            </div>
        </form>
    </x-modal>
</x-app-layout>
