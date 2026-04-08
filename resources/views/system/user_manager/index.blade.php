<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System: User Manager') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">

                    <!-- Tabs -->
                    <div class="mb-6 border-b border-gray-200">
                        <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                            @foreach(['users' => 'Users', 'groups' => 'Groups', 'auth_servers' => 'Authentication Servers'] as $key => $label)
                                <a href="{{ route('system.user_manager.index', ['firewall' => $firewall->id, 'tab' => $key]) }}"
                                   class="{{ $tab === $key ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                                    {{ $label }}
                                </a>
                            @endforeach
                        </nav>
                    </div>

                    @if (session('success'))
                        <div class="mb-4 font-medium text-sm text-green-600">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 font-medium text-sm text-red-600">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    @if (isset($data['error']))
                        <div class="mb-4 font-medium text-sm text-red-600">
                            Error fetching data: {{ $data['error'] }}
                        </div>
                    @endif

                    <!-- Content -->
                    @if($tab === 'users')
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Users</h3>
                            @if(!auth()->user()->isReadOnly())
                            <x-link-button-add href="{{ route('system.user_manager.users.create', $firewall->id) }}">
                                Add User
                            </x-link-button-add>
                            @endif
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Full Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Groups</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        @if(!auth()->user()->isReadOnly())
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($data['users'] as $user)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $user['name'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $user['descr'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <!-- Need to cross-reference groups if possible, or just list count -->
                                                {{ count($user['groups'] ?? []) }} groups
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                @if(!empty($user['disabled']))
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Disabled</span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                                @endif
                                            </td>
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('system.user_manager.users.edit', [$firewall->id, $user['id']]) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                                                @if($user['name'] !== 'admin')
                                                    <form action="{{ route('system.user_manager.users.destroy', [$firewall->id, $user['id']]) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete user {{ $user['name'] }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                    </form>
                                                @endif
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No users found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    @elseif($tab === 'groups')
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Groups</h3>
                            @if(!auth()->user()->isReadOnly())
                            <x-link-button-add href="{{ route('system.user_manager.groups.create', $firewall->id) }}">
                                Add Group
                            </x-link-button-add>
                            @endif
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Group Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Member Count</th>
                                        @if(!auth()->user()->isReadOnly())
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        @endif
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    @forelse($data['groups'] as $group)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $group['name'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ $group['description'] }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">{{ count($group['member'] ?? []) }}</td>
                                            @if(!auth()->user()->isReadOnly())
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('system.user_manager.groups.edit', [$firewall->id, $group['id']]) }}" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</a>
                                                @if(!in_array($group['name'], ['all', 'admins']))
                                                    <form action="{{ route('system.user_manager.groups.destroy', [$firewall->id, $group['id']]) }}" method="POST" class="inline-block" onsubmit="return confirm('Are you sure you want to delete group {{ $group['name'] }}?');">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="text-red-600 hover:text-red-900">Delete</button>
                                                    </form>
                                                @endif
                                            </td>
                                            @endif
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No groups found.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                    @elseif($tab === 'auth_servers')
                        <div class="p-4 text-gray-500">Authentication Servers management is not yet implemented in this view.</div>
                    @endif

                </div>
            </div>
        </div>
    </div>
</x-app-layout>
