<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('Firewall Categories') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12" x-data="{
        showModal: false,
        isEdit: false,
        editUuid: '',
        form: {
            name: '',
            color: '#336699',
            auto: false
        },
        openAddModal() {
            this.isEdit = false;
            this.editUuid = '';
            this.form = {
                name: '',
                color: '#336699',
                auto: false
            };
            this.showModal = true;
        },
        openEditModal(cat) {
            this.isEdit = true;
            this.editUuid = cat.uuid || cat.id || '';
            const hex = cat.color ? (cat.color.startsWith('#') ? cat.color : '#' + cat.color) : '#336699';
            this.form = {
                name: cat.name || '',
                color: hex,
                auto: cat.auto == '1' || cat.auto === true
            };
            this.showModal = true;
        }
    }">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-medium">Categories</h3>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Manage tags and color badges used to group firewall rules and aliases in OPNsense.</p>
                        </div>
                        @if(!auth()->user()->isReadOnly())
                        <button @click="openAddModal()"
                            class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            Add Category
                        </button>
                        @endif
                    </div>

                    @if (session('success'))
                        <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative">
                            <ul class="list-disc pl-5">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <div class="overflow-x-auto relative shadow-md sm:rounded-lg">
                        <table class="w-full text-sm text-left text-gray-500 dark:text-gray-400">
                            <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                                <tr>
                                    <th scope="col" class="py-3 px-6">Color</th>
                                    <th scope="col" class="py-3 px-6">Name</th>
                                    <th scope="col" class="py-3 px-6">Type</th>
                                    <th scope="col" class="py-3 px-6 text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($categories as $category)
                                    @php
                                        $colorHex = !empty($category['color']) ? (str_starts_with($category['color'], '#') ? $category['color'] : '#' . $category['color']) : '#336699';
                                    @endphp
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center gap-2">
                                                <span class="w-5 h-5 rounded-full border border-gray-300 shadow-sm inline-block" style="background-color: {{ $colorHex }};"></span>
                                                <span class="font-mono text-xs text-gray-500">{{ $colorHex }}</span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 font-semibold text-gray-900 dark:text-white">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium text-white" style="background-color: {{ $colorHex }};">
                                                {{ $category['name'] ?? 'Unnamed' }}
                                            </span>
                                        </td>
                                        <td class="py-4 px-6 text-sm">
                                            {{ (!empty($category['auto']) && $category['auto'] == '1') ? 'Automatic' : 'Manual' }}
                                        </td>
                                        <td class="py-4 px-6 text-right space-x-2">
                                            @if(!auth()->user()->isReadOnly())
                                            <button @click="openEditModal({{ Js::from($category) }})"
                                                class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-900">Edit</button>
                                            <form method="POST" action="{{ route('firewall.categories.destroy', ['firewall' => $firewall, 'uuid' => $category['uuid'] ?? $category['id']]) }}" class="inline" onsubmit="return confirm('Are you sure you want to delete this category?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 dark:text-red-400 hover:text-red-900">Delete</button>
                                            </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                        <td colspan="4" class="py-6 px-6 text-center text-gray-500">
                                            No categories found. Click "Add Category" to create a new category.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Add / Edit Modal --}}
        <div x-show="showModal" style="display: none;" class="fixed inset-0 z-50 overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
            <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                <div x-show="showModal" @click="showModal = false" class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                <div x-show="showModal" class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-md sm:w-full">
                    <form :action="isEdit ? '{{ url('/firewall/' . $firewall->id . '/categories') }}/' + editUuid : '{{ route('firewall.categories.store', $firewall) }}'" method="POST">
                        @csrf
                        <template x-if="isEdit">
                            <input type="hidden" name="_method" value="PATCH">
                        </template>

                        <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100" id="modal-title" x-text="isEdit ? 'Edit Category' : 'Add Category'"></h3>

                            <div class="mt-4 space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Name</label>
                                    <input type="text" name="name" x-model="form.name" required class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="e.g. Web Servers, VoIP">
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Color</label>
                                    <div class="flex items-center gap-3 mt-1">
                                        <input type="color" name="color" x-model="form.color" class="h-10 w-16 p-1 rounded border border-gray-300 cursor-pointer">
                                        <input type="text" x-model="form.color" class="block w-full font-mono text-sm rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm sm:text-sm">
                                    </div>
                                </div>

                                <div>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="auto" value="1" x-model="form.auto" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500">
                                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Auto Generated</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                            <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                                Save Category
                            </button>
                            <button type="button" @click="showModal = false" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
