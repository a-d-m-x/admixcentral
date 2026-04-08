<x-app-layout>
    <x-slot name="header">
        <x-firewall-header title="{{ __('System: Routing: Static Routes: Edit') }}" :firewall="$firewall" />
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 bg-white border-b border-gray-200">
                    <form method="POST"
                        action="{{ route('system.routing.static_routes.update', [$firewall->id, $route['id']]) }}">
                        @csrf
                        @method('PATCH')
                        <fieldset @if(auth()->user()->isReadOnly()) disabled @endif class="[&:disabled]:opacity-60 [&:disabled]:pointer-events-none">

                        <!-- Network -->
                        <div class="mb-4">
                            <x-input-label for="network" :value="__('Destination Network')" />
                            <x-text-input id="network" class="block mt-1 w-full" type="text" name="network"
                                :value="old('network', $route['network'])" required
                                placeholder="e.g., 192.168.10.0/24" />
                            <x-input-error :messages="$errors->get('network')" class="mt-2" />
                        </div>

                        <!-- Gateway -->
                        <div class="mb-4">
                            <x-input-label for="gateway" :value="__('Gateway')" />
                            <select id="gateway" name="gateway"
                                class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm">
                                @foreach($gateways as $gw)
                                    <option value="{{ $gw['name'] }}" {{ ($route['gateway'] == $gw['name']) ? 'selected' : '' }}>{{ $gw['name'] }} ({{ $gw['gateway'] }})</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('gateway')" class="mt-2" />
                        </div>

                        <!-- Description -->
                        <div class="mb-4">
                            <x-input-label for="descr" :value="__('Description')" />
                            <x-text-input id="descr" class="block mt-1 w-full" type="text" name="descr"
                                :value="old('descr', $route['descr'] ?? '')" />
                            <x-input-error :messages="$errors->get('descr')" class="mt-2" />
                        </div>

                        <!-- Disabled -->
                        <div class="flex items-center mb-4">
                            <input id="disabled" name="disabled" type="checkbox"
                                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" {{ (old('disabled') || !empty($route['disabled'])) ? 'checked' : '' }}>
                            <label for="disabled"
                                class="ml-2 block text-sm text-gray-900">{{ __('Disable this static route') }}</label>
                        </div>

                        </fieldset>

                        <div class="flex items-center justify-end mt-4">
                            <a href="{{ route('system.routing.index', ['firewall' => $firewall->id, 'tab' => 'static_routes']) }}"
                                class="text-gray-600 hover:text-gray-900 mr-4">Cancel</a>
                            @if(!auth()->user()->isReadOnly())
                            <x-primary-button>
                                {{ __('Save') }}
                            </x-primary-button>
                            @endif
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
