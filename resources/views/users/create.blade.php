<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Add New User') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form action="{{ route('users.store') }}" method="POST" x-data="{
                              companyId: '{{ old('company_id', request('company_id', '')) }}',
                              role: '{{ old('role', '') }}',
                              email: '{{ old('email', '') }}',
                              password: '',
                              passwordConfirm: '',
                              passwordScore: 0,
                              passwordRequirements: {
                                  length: false,
                                  number: false,
                                  symbol: false,
                                  uppercase: false
                              },
                              checkPassword() {
                                  const p = this.password;
                                  this.passwordRequirements.length = p.length >= 8;
                                  this.passwordRequirements.number = /\d/.test(p);
                                  this.passwordRequirements.symbol = /[!@#$%^&*(),.?\x22:{}|<>]/.test(p);
                                  this.passwordRequirements.uppercase = /[A-Z]/.test(p);

                                  let score = 0;
                                  if (this.passwordRequirements.length) score++;
                                  if (this.passwordRequirements.number) score++;
                                  if (this.passwordRequirements.symbol) score++;
                                  if (this.passwordRequirements.uppercase) score++;
                                  this.passwordScore = score;
                              },
                              emailError: false,
                              isCheckingEmail: false,
                              companyOpen: false,
                              companySearch: '',
                              companies: {{ $companies->map(fn($c) => ['id' => $c->id, 'name' => $c->name])->values()->toJson() }},
                              async checkEmail() {
                                  if (this.email === '') return;
                                  this.isCheckingEmail = true;
                                  this.emailError = false;
                                  try {
                                      const response = await fetch('{{ route('users.check-email') }}', {
                                          method: 'POST',
                                          headers: {
                                              'Content-Type': 'application/json',
                                              'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                          },
                                          body: JSON.stringify({ email: this.email })
                                      });
                                      const data = await response.json();
                                      if (data.exists) {
                                          this.emailError = true;
                                      }
                                  } catch (error) {
                                      console.error('Error checking email:', error);
                                  } finally {
                                      this.isCheckingEmail = false;
                                  }
                              },
                              get filteredCompanies() {
                                  if (this.companySearch === '') return this.companies;
                                  return this.companies.filter(c => c.name.toLowerCase().includes(this.companySearch.toLowerCase()));
                              },
                              get selectedCompanyName() {
                                  if (this.companyId === 'global') return 'All Companies (Global Admin)';
                                  const c = this.companies.find(i => i.id == this.companyId);
                                  return c ? c.name : 'Select a Company...';
                              },
                              selectCompany(id) {
                                  this.companyId = id;
                                  this.companyOpen = false;
                                  this.companySearch = '';
                                  this.role = ''; // Force explicit re-selection
                              }
                          }">
                        @csrf

                        <!-- Company -->
                        <div class="mb-4">
                            @if($companies->count() > 1 || auth()->user()->isGlobalAdmin())
                                <label for="company_id"
                                    class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Company</label>
                                <input type="hidden" name="company_id" :value="companyId === 'global' ? '' : companyId">

                                <div class="relative" @click.outside="companyOpen = false"
                                    @keydown.escape="companyOpen = false">
                                    <!-- Trigger Button -->
                                    <button @click="companyOpen = !companyOpen" type="button"
                                        class="flex items-center justify-between w-full rounded-md border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-900 text-sm px-3 py-2 text-left focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition ease-in-out duration-150">
                                        <span x-text="selectedCompanyName"
                                            :class="{'text-gray-500': companyId === '' || companyId === 'global' && false, 'text-gray-900 dark:text-gray-300': companyId !== ''}"></span>
                                        <svg class="h-4 w-4 ml-2 text-gray-500 transform transition-transform duration-200"
                                            :class="{'rotate-180': companyOpen}" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round"
                                                stroke-linejoin="round" />
                                        </svg>
                                    </button>

                                    <!-- Dropdown Menu -->
                                    <div x-show="companyOpen" x-transition.opacity.duration.200ms style="display: none;"
                                        class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg overflow-hidden">

                                        <!-- Search Input -->
                                        <div
                                            class="p-2 border-b border-gray-100 dark:border-gray-700 bg-gray-50 dark:bg-gray-900">
                                            <input x-model="companySearch" x-ref="companySearchInput" type="text"
                                                placeholder="Search..."
                                                class="w-full text-sm rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-200 px-2 py-1 focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>

                                        <!-- List -->
                                        <ul class="max-h-60 overflow-y-auto py-1">
                                            <!-- All Companies Option -->
                                            <li @click="selectCompany('global')"
                                                class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm italic text-gray-800 dark:text-gray-200 border-b border-gray-100 dark:border-gray-700 mb-1"
                                                :class="{'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700': companyId === 'global'}">
                                                All Companies (Global Admin)
                                            </li>

                                            <template x-for="company in filteredCompanies" :key="company.id">
                                                <li @click="selectCompany(company.id)"
                                                    class="px-4 py-2 hover:bg-gray-100 dark:hover:bg-gray-700 cursor-pointer text-sm text-gray-700 dark:text-gray-300 truncate"
                                                    :class="{'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700': companyId == company.id}"
                                                    x-text="company.name">
                                                </li>
                                            </template>

                                            <li x-show="filteredCompanies.length === 0"
                                                class="px-4 py-2 text-sm text-gray-400 italic text-center">No matches</li>
                                        </ul>
                                    </div>
                                </div>

                            @else
                                <!-- Single company available (Company Admin) -->
                                <input type="hidden" name="company_id" value="{{ $companies->first()->id }}"
                                    x-init="companyId = '{{ $companies->first()->id }}'">
                                <!-- Hidden for clean UI as per request -->
                            @endif
                            @error('company_id')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Name -->
                        <div class="mb-4">
                            <label for="name"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Name</label>
                            <input type="text" name="name" id="name" value="{{ old('name') }}" required
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            @error('name')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Email -->
                        <div class="mb-4">
                            <label for="email"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Email</label>
                            <div class="relative">
                                <input type="email" name="email" id="email" value="{{ old('email') }}" required
                                    x-model="email" @blur="checkEmail()"
                                    class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    :class="{'border-red-500 focus:border-red-500 focus:ring-red-500': emailError}">
                                <div x-show="isCheckingEmail" class="absolute right-3 top-3">
                                    <svg class="animate-spin h-5 w-5 text-gray-500" xmlns="http://www.w3.org/2000/svg"
                                        fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor"
                                            stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor"
                                            d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                        </path>
                                    </svg>
                                </div>
                            </div>
                            <p x-show="emailError" class="text-red-500 text-xs mt-1" style="display: none;">This email
                                is already taken.</p>
                            @error('email')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Password -->
                        <div class="mb-4">
                            <label for="password"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Password</label>
                            <input type="password" name="password" id="password" required x-model="password"
                                @input="checkPassword()"
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">

                            <!-- Strength Meter -->
                            <div class="mt-3 p-3 bg-gray-50 dark:bg-gray-800/50 rounded-md border border-gray-100 dark:border-gray-700"
                                x-show="password.length > 0" x-transition.opacity>
                                <div class="flex items-center justify-between mb-2">
                                    <span
                                        class="text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Strength</span>
                                    <span class="text-xs font-bold" :class="{
                                              'text-red-500': passwordScore <= 1,
                                              'text-yellow-500': passwordScore === 2,
                                              'text-blue-500': passwordScore === 3,
                                              'text-green-500': passwordScore === 4
                                          }"
                                        x-text="['Weak', 'Fair', 'Good', 'Strong'][Math.max(0, passwordScore - 1)] || 'Weak'"></span>
                                </div>
                                <div
                                    class="h-1.5 w-full bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden mb-3">
                                    <div class="h-full transition-all duration-500 ease-out" :class="{
                                             'w-1/4 bg-red-500': passwordScore <= 1,
                                             'w-2/4 bg-yellow-500': passwordScore === 2,
                                             'w-3/4 bg-blue-500': passwordScore === 3,
                                             'w-full bg-green-500': passwordScore === 4
                                         }"></div>
                                </div>
                                <ul class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                                    <template x-for="(met, name) in {
                                        '8+ Characters': passwordRequirements.length,
                                        'One Number': passwordRequirements.number,
                                        'One Symbol': passwordRequirements.symbol,
                                        'Uppercase Letter': passwordRequirements.uppercase
                                    }">
                                        <li class="flex items-center gap-2 transition-colors duration-200"
                                            :class="met ? 'text-green-600 dark:text-green-400 font-medium' : 'text-gray-400 dark:text-gray-500'">
                                            <div class="w-4 h-4 rounded-full flex items-center justify-center border transition-colors duration-200"
                                                :class="met ? 'bg-green-100 dark:bg-green-900/30 border-green-200 dark:border-green-800' : 'border-gray-300 dark:border-gray-600'">
                                                <svg x-show="met" class="w-2.5 h-2.5" fill="currentColor"
                                                    viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd"
                                                        d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z"
                                                        clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <span x-text="name"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            @error('password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Confirm Password -->
                        <div class="mb-4">
                            <label for="password_confirmation"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Confirm
                                Password</label>
                            <input type="password" name="password_confirmation" id="password_confirmation" required
                                x-model="passwordConfirm"
                                class="w-full rounded-md shadow-sm border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                :class="{'border-red-500 focus:border-red-500 focus:ring-red-500': passwordConfirm && password !== passwordConfirm, 'border-green-500 focus:border-green-500 focus:ring-green-500': passwordConfirm && password === passwordConfirm}">

                            <p x-show="passwordConfirm && password !== passwordConfirm"
                                class="text-red-500 text-xs mt-1 transition-opacity duration-200">
                                Passwords do not match.
                            </p>
                        </div>



                        <!-- Role -->
                        <div class="mb-4">
                            <label for="role"
                                class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Role</label>
                            <input type="hidden" name="role" :value="role">
                            <div class="relative" x-data="{ roleOpen: false }" @click.outside="roleOpen = false"
                                @keydown.escape="roleOpen = false">
                                <button @click="if(companyId !== '') roleOpen = !roleOpen" type="button"
                                    :class="{'opacity-50 cursor-not-allowed bg-gray-100 dark:bg-gray-800': companyId === '', 'bg-white dark:bg-gray-900': companyId !== ''}"
                                    :disabled="companyId === ''"
                                    class="flex items-center justify-between w-full rounded-md border border-gray-300 dark:border-gray-700 text-sm px-3 py-2 text-left focus:outline-none focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500 shadow-sm transition ease-in-out duration-150">
                                    <span x-text="
                                        role === 'admin' && companyId === 'global' ? 'Global Admin (Full System Access)' :
                                        (role === 'admin' && companyId !== 'global' ? 'Company Admin (Full Company Access)' :
                                        (role === 'user' ? 'End User (Standard Access)' : 'Select a user role...'))
                                    "
                                        :class="{'text-gray-500': role === '', 'text-gray-900 dark:text-gray-300': role !== ''}"></span>
                                    <svg class="h-4 w-4 ml-2 text-gray-500 transform transition-transform duration-200"
                                        :class="{'rotate-180': roleOpen}" fill="none" stroke="currentColor"
                                        viewBox="0 0 24 24">
                                        <path d="M19 9l-7 7-7-7" stroke-width="2" stroke-linecap="round"
                                            stroke-linejoin="round" />
                                    </svg>
                                </button>

                                <div x-show="roleOpen" x-transition.opacity.duration.200ms style="display: none;"
                                    class="absolute z-50 mt-1 w-full bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-600 rounded-md shadow-lg overflow-hidden">
                                    <ul class="py-1">
                                        <!-- Global Admin (Only visible if All Companies selected) -->
                                        <li x-show="companyId === 'global'" @click="role = 'admin'; roleOpen = false;"
                                            class="px-4 py-2 cursor-pointer text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                                            :class="{'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700': role === 'admin'}">
                                            Global Admin (Full System Access)
                                        </li>

                                        <!-- Customer Admin (Only visible if Specific Company selected) -->
                                        <li x-show="companyId !== 'global'" @click="role = 'admin'; roleOpen = false;"
                                            class="px-4 py-2 cursor-pointer text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                                            :class="{'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700': role === 'admin'}">
                                            Company Admin (Full Company Access)
                                        </li>

                                        <!-- End User (Only visible if Specific Company selected) -->
                                        <li x-show="companyId !== 'global'" @click="role = 'user'; roleOpen = false;"
                                            class="px-4 py-2 cursor-pointer text-sm hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-150"
                                            :class="{'bg-indigo-50 dark:bg-indigo-900/30 text-indigo-700': role === 'user'}">
                                            End User (Standard Access)
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            @error('role')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center gap-4">
                            <x-primary-button type="submit"
                                x-bind:disabled="emailError || role === '' || companyId === '' || isCheckingEmail || passwordScore < 3 || password !== passwordConfirm"
                                x-bind:class="{'opacity-50 cursor-not-allowed': emailError || role === '' || companyId === '' || isCheckingEmail || passwordScore < 3 || password !== passwordConfirm}">
                                {{ __('Create User') }}
                            </x-primary-button>
                            <a href="{{ route('users.index') }}">
                                <x-secondary-button>
                                    {{ __('Cancel') }}
                                </x-secondary-button>
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>