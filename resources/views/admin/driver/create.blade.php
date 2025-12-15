@extends('layouts.main-layout')

@section('title', 'Create Driver')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Create Driver</h1>
            <p class="text-gray-600 dark:text-gray-400">Add a new driver to the system</p>
        </div>

        @if ($errors->any())
            <div class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd"
                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                            clip-rule="evenodd" />
                    </svg>
                    <h3 class="text-red-800 dark:text-red-200 font-medium">Please fix the following errors:</h3>
                </div>
                <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
                <form action="{{ route('admin.driver.store') }}" method="POST" id="driverForm">
                    @csrf

                    <!-- Company Selection -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Company Information
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div>
                                <label for="company_id"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Company <span class="text-error-500">*</span>
                                </label>
                                <select id="company_id" name="company_id" required
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                    <option value="">Select Company</option>
                                    @foreach ($companies as $company)
                                        <option value="{{ $company->id }}"
                                            {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                            {{ $company->company_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Applicant Information
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6 space-y-6">
                            <!-- Name Row -->
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label for="first_name"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        First Name <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="first_name" name="first_name" value="{{ old('first_name') }}"
                                        required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="middle_name"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Middle Name
                                    </label>
                                    <input type="text" id="middle_name" name="middle_name"
                                        value="{{ old('middle_name') }}"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="last_name"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Last Name <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}"
                                        required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="suffix"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Suffix
                                    </label>
                                    <input type="text" id="suffix" name="suffix" value="{{ old('suffix') }}"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                            </div>

                            <!-- Business Information Section -->
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="business_name"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Business Name
                                    </label>
                                    <input type="text" id="business_name" name="business_name"
                                        value="{{ old('business_name') }}"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="employer_identification_number"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Employer Identification Number
                                    </label>
                                    <input type="text" id="employer_identification_number"
                                        name="employer_identification_number"
                                        value="{{ old('employer_identification_number') }}"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                            </div>

                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Federal Tax Classification
                                </label>
                                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                    @foreach (['individual_sole_proprietor' => 'Individual/Sole Proprietor', 'c_corporation' => 'C Corporation', 's_corporation' => 'S Corporation', 'llc' => 'LLC'] as $value => $label)
                                        <label class="flex items-center">
                                            <input type="radio" name="federal_tax_classification"
                                                value="{{ $value }}"
                                                {{ old('federal_tax_classification') == $value ? 'checked' : '' }}
                                                class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                            <span
                                                class="ml-2 text-sm text-gray-700 dark:text-gray-300">{{ $label }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Personal Details -->
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="date_of_birth"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Date of Birth <span class="text-error-500">*</span>
                                    </label>
                                    <input type="date" id="date_of_birth" name="date_of_birth"
                                        value="{{ old('date_of_birth') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="ssn"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Social Security Number <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="ssn" name="ssn" value="{{ old('ssn') }}"
                                        required placeholder="XXX-XX-XXXX"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                            </div>

                            <!-- Contact Information -->
                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                                <div>
                                    <label for="main_phone"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Main Phone Number <span class="text-error-500">*</span>
                                    </label>
                                    <input type="tel" id="main_phone" name="main_phone"
                                        value="{{ old('main_phone') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="alt_phone"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Alt Phone Number
                                    </label>
                                    <input type="tel" id="alt_phone" name="alt_phone"
                                        value="{{ old('alt_phone') }}"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                            </div>

                            <div>
                                <label for="email"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Email Address <span class="text-error-500">*</span>
                                </label>
                                <input type="email" id="email" name="email" value="{{ old('email') }}"
                                    required
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>


                            <!-- Address Information Section -->
                            <div>
                                <label for="address"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Address <span class="text-error-500">*</span>
                                </label>
                                <input type="text" id="address" name="address" value="{{ old('address') }}"
                                    required
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label for="city"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        City <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="city" name="city" value="{{ old('city') }}"
                                        required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>


                                <div>
                                    <label for="country"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Country <span class="text-error-500">*</span>
                                    </label>
                                    <select id="country" name="country" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}"
                                                {{ old('country', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('country')
                                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="state"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        State <span class="text-error-500">*</span>
                                    </label>
                                    <select id="state" name="state" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="">Select State</option>
                                        @if (isset($states) && $states->count())
                                            @foreach ($states as $state)
                                                <option value="{{ $state->id }}"
                                                    {{ old('state') == $state->id ? 'selected' : '' }}>
                                                    {{ $state->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    @error('state')
                                        <p class="mt-1 text-sm text-error-500">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <label for="postal_code"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Postal Code <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="postal_code" name="postal_code"
                                        value="{{ old('postal_code') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                            </div>

                            <!-- Additional Documents -->
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <label class="flex items-center">
                                    <input type="checkbox" name="twic_card" value="1"
                                        {{ old('twic_card') ? 'checked' : '' }}
                                        class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Do you have a TWIC
                                        Card?</span>
                                </label>

                                <label class="flex items-center">
                                    <input type="checkbox" name="passport" value="1"
                                        {{ old('passport') ? 'checked' : '' }}
                                        class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Do you have a
                                        passport?</span>
                                </label>
                            </div>
                        </div>
                    </div>


                    <!-- Residences Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Residences Previous 3 Years
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <label class="flex items-center pb-4">
                                <input type="checkbox" name="same_address" value="1" id="same_address_checkbox"
                                    {{ old('same_address') ? 'checked' : '' }}
                                    class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Same address as from Applicant
                                    Information.</span>
                            </label>

                            <p class="text-sm text-gray-500 dark:text-gray-400 pb-4">List residence for previous 3 years if
                                you
                                lived at
                                the above address less than 3 years.
                            </p>

                            <div id="residence_fields">
                                <!-- Initial residence field -->
                                <div
                                    class="residence-field mb-6 p-4 border border-gray-200 rounded-lg dark:border-gray-700">
                                    <div class="pb-4">
                                        <label for="residence_address_0"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Address
                                        </label>
                                        <input type="text" id="residence_address_0" name="residence_address[]"
                                            value="{{ old('residence_address.0') }}"
                                            class="residence-address shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                    </div>

                                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                        <div>
                                            <label for="residence_city_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                City
                                            </label>
                                            <input type="text" id="residence_city_0" name="residence_city[]"
                                                value="{{ old('residence_city.0') }}"
                                                class="residence-city shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>

                                        <div>
                                            <label for="residence_country_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Country
                                            </label>
                                            <select id="residence_country_0" name="residence_country[]"
                                                class="residence-country shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                <option value="">Select Country</option>
                                                @foreach ($countries as $country)
                                                    <option value="{{ $country->id }}"
                                                        {{ old('residence_country.0', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                                        {{ $country->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label for="residence_state_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                State
                                            </label>
                                            <select id="residence_state_0" name="residence_state[]"
                                                class="residence-state shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                <option value="">Select State</option>
                                                @if (isset($states) && $states->count())
                                                    @foreach ($states as $state)
                                                        <option value="{{ $state->id }}"
                                                            {{ old('residence_state.0') == $state->id ? 'selected' : '' }}>
                                                            {{ $state->name }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>

                                        <div>
                                            <label for="residence_postal_code_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Postal Code
                                            </label>
                                            <input type="text" id="residence_postal_code_0"
                                                name="residence_postal_code[]"
                                                value="{{ old('residence_postal_code.0') }}"
                                                class="residence-postal-code shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="my-4 flex justify-between">
                                <div>
                                    <button type="button" id="residence_add"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-lg shadow-theme-xs hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Add Another Residence
                                    </button>
                                </div>
                                <div>
                                    <button type="button" id="residence_remove"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-red-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-red-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Remove Last Residence
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Driving License Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Drivers License Information
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <p class="text-sm text-gray-500 dark:text-gray-400 pb-4">List all driver licenses held within
                                the last
                                3 years. Enter your first and last name exactly as it appears on your license.
                            </p>
                            <div
                                class="rounded-lg mb-4 py-2 px-4 text-orange-900 border border-amber-100 bg-amber-100 dark:border-gray-800 dark:bg-white/[0.03]">
                                <p>Warning! Triple check the license information for accuracy. If you enter the wrong
                                    information,
                                    all documents relating to your license will have to be discarded and completed again.
                                    Failure to
                                    enter accurate license information may result in non-consideration and a rejected
                                    application!
                                </p>
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label for="license_first_name"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        First Name <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="license_first_name" name="license_first_name"
                                        value="{{ old('license_first_name') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="license_last_name"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Last Name <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="license_last_name" name="license_last_name"
                                        value="{{ old('license_last_name') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="license_issued"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Issued <span class="text-error-500">*</span>
                                    </label>
                                    <input type="date" id="license_issued" name="license_issued"
                                        value="{{ old('license_issued') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="license_expires"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Expires <span class="text-error-500">*</span>
                                    </label>
                                    <input type="date" id="license_expires" name="license_expires"
                                        value="{{ old('license_expires') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                            </div>

                            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label for="license_country"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Country <span class="text-error-500">*</span>
                                    </label>
                                    <select id="license_country" name="license_country" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="">Select Country</option>
                                        @foreach ($countries as $country)
                                            <option value="{{ $country->id }}"
                                                {{ old('license_country', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                                {{ $country->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="license_state"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        State <span class="text-error-500">*</span>
                                    </label>
                                    <select id="license_state" name="license_state" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="">Select State</option>
                                        @if (isset($states) && $states->count())
                                            @foreach ($states as $state)
                                                <option value="{{ $state->id }}"
                                                    {{ old('license_state') == $state->id ? 'selected' : '' }}>
                                                    {{ $state->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                </div>

                                <div>
                                    <label for="license_class"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Class <span class="text-error-500">*</span>
                                    </label>
                                    <select id="license_class" name="license_class" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                        <option value="">Select license class</option>
                                        <option value="Class A (CDL-A)"
                                            {{ old('license_class') == 'Class A (CDL-A)' ? 'selected' : '' }}>Class A
                                            (CDL-A)
                                        </option>
                                        <option value="Class B (CDL-B)"
                                            {{ old('license_class') == 'Class B (CDL-B)' ? 'selected' : '' }}>Class B
                                            (CDL-B)
                                        </option>
                                        <option value="Class C (CDL-C)"
                                            {{ old('license_class') == 'Class C (CDL-C)' ? 'selected' : '' }}>Class C
                                            (CDL-C)
                                        </option>
                                        <option value="Class D" {{ old('license_class') == 'Class D' ? 'selected' : '' }}>
                                            Class D
                                        </option>
                                        <option value="Class C (Non-Commercial)"
                                            {{ old('license_class') == 'Class C (Non-Commercial)' ? 'selected' : '' }}>
                                            Class C
                                            (Non-Commercial)
                                        </option>
                                        <option value="Class M" {{ old('license_class') == 'Class M' ? 'selected' : '' }}>
                                            Class M
                                        </option>
                                        <option value="Class E" {{ old('license_class') == 'Class E' ? 'selected' : '' }}>
                                            Class E
                                        </option>
                                        <option value="Class F" {{ old('license_class') == 'Class F' ? 'selected' : '' }}>
                                            Class F
                                        </option>
                                        <option value="Class DJ/MJ"
                                            {{ old('license_class') == 'Class DJ/MJ' ? 'selected' : '' }}>
                                            Class DJ/MJ
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
                                <div>
                                    <label for="license_number"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        License Number <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="license_number" name="license_number"
                                        value="{{ old('license_number') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>

                                <div>
                                    <label for="repeat_license_number"
                                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                        Repeat License Number: <span class="text-error-500">*</span>
                                    </label>
                                    <input type="text" id="repeat_license_number" name="repeat_license_number"
                                        value="{{ old('repeat_license_number') }}" required
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                </div>
                            </div>

                            <div class="mt-4">
                                <p class="mb-2 text-sm font-medium text-gray-700 dark:text-gray-400">Endorsements:</p>
                                <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_h_placarded_hazmat" value="1"
                                            {{ old('is_h_placarded_hazmat') ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">H - Placarded
                                            Hazmat.</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_n_tank_vehicle" value="1"
                                            {{ old('is_n_tank_vehicle') ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">N - Tank
                                            Vehicles.</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_p_passengers" value="1"
                                            {{ old('is_p_passengers') ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">P - Passengers.</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_t_double_trailer" value="1"
                                            {{ old('is_t_double_trailer') ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">T - Double/Triple
                                            Trailers.</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_s_school_bus" value="1"
                                            {{ old('is_s_school_bus') ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">S - School Bus.</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="is_x_placarded_hazmat" value="1"
                                            {{ old('is_x_placarded_hazmat') ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">X - Placarded Hazmat &
                                            Tank
                                            Vehicles.</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Medical certificate Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Medical Certificate
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div>
                                <label for="medical_certificate_expiration_date"
                                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                    Medical Certificate Expiration Date <span class="text-error-500">*</span>
                                </label>
                                <input type="date" id="medical_certificate_expiration_date"
                                    name="medical_certificate_expiration_date"
                                    value="{{ old('medical_certificate_expiration_date') }}" required
                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                            </div>
                        </div>
                    </div>


                    <!-- Experience Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Experience
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <table class="w-full">
                                <thead>
                                    <tr class="border border-gray-200 dark:border-gray-700">
                                        <th
                                            class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                            Equipment Class
                                        </th>
                                        <th
                                            class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                            Experience
                                        </th>
                                        <th
                                            class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                            Date From MM/DO/YYYY
                                        </th>
                                        <th
                                            class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                            Date To MM/DO/YYYY
                                        </th>
                                        <th
                                            class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                            Approx Miles
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $equipmentClasses = [
                                            'Straight Truck',
                                            'Truck-Tractor',
                                            'Semi-Trailers',
                                            'Double/Triples',
                                            'Flatbed',
                                            'Bus',
                                            'Other',
                                        ];
                                    @endphp

                                    @foreach ($equipmentClasses as $index => $equipmentClass)
                                        <tr class="border border-gray-200 dark:border-gray-700">
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                {{ $equipmentClass }}
                                                <input type="hidden" name="equipment_class[]"
                                                    value="{{ $equipmentClass }}">
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <select name="experience[]"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                    <option value="no"
                                                        {{ old('experience.' . $index) == 'no' ? 'selected' : '' }}>No
                                                    </option>
                                                    <option value="yes"
                                                        {{ old('experience.' . $index) == 'yes' ? 'selected' : '' }}>Yes
                                                    </option>
                                                </select>
                                            </td>

                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="date" name="experience_from_date[]"
                                                    value="{{ old('experience_from_date.' . $index) }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="date" name="experience_to_date[]"
                                                    value="{{ old('experience_to_date.' . $index) }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="approx_miles[]"
                                                    value="{{ old('approx_miles.' . $index) }}" placeholder="0"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>


                    <!-- Accidents/Crashes Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Accidents/Crashes Previous 3 Years
                            </h3>
                            <!-- Question wrapper -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Have you had any
                                    accidents/crashes
                                    in the last 3 years? <span class="text-red-500">*</span></p>
                                <div class="flex items-center gap-6">
                                    <!-- YES -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="accident" value="yes"
                                            {{ old('accident') == 'yes' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                    </label>
                                    <!-- NO -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="accident" value="no"
                                            {{ old('accident', 'no') == 'no' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div id="accident_fields">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border border-gray-200 dark:border-gray-700">
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Date MM/DO/YYYY
                                            </th>
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Location City/State
                                            </th>
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Number of Injuries
                                            </th>
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Number of Fatalities
                                            </th>
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Hazmat Spill?
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Initial row -->
                                        <tr class="border border-gray-200 dark:border-gray-700">
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="date" name="accident_date[]"
                                                    value="{{ old('accident_date.0') }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="accident_location[]"
                                                    value="{{ old('accident_location.0') }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="number_of_injuries[]"
                                                    value="{{ old('number_of_injuries.0') }}" placeholder="0"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="number_of_fatalities[]"
                                                    value="{{ old('number_of_fatalities.0') }}" placeholder="0"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <select name="hazmat_spill[]"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                    <option value="no"
                                                        {{ old('hazmat_spill.0') == 'no' ? 'selected' : '' }}>
                                                        No</option>
                                                    <option value="yes"
                                                        {{ old('hazmat_spill.0') == 'yes' ? 'selected' : '' }}>
                                                        Yes</option>
                                                </select>
                                            </td>
                                        </tr>
                                        <!-- Additional rows will be appended here by JavaScript -->
                                    </tbody>
                                </table>
                            </div>

                            <div class="my-4 flex justify-between">
                                <div>
                                    <button type="button" id="accident_add"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-lg shadow-theme-xs hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Add Another Accident/Crash
                                    </button>
                                </div>
                                <div>
                                    <button type="button" id="accident_remove"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-red-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-red-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Remove Last Accident
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Moving Traffic Violations Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Moving Traffic Violations Previous 3 Years
                            </h3>
                            <!-- Question wrapper -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Have you had any traffic
                                    violations
                                    in the last 3 years? <span class="text-red-500">*</span></p>
                                <div class="flex items-center gap-6">
                                    <!-- YES -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="violation" value="yes"
                                            {{ old('violation') == 'yes' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                    </label>
                                    <!-- NO -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="violation" value="no"
                                            {{ old('violation', 'no') == 'no' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div id="violation_fields">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border border-gray-200 dark:border-gray-700">
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Date MM/DO/YYYY
                                            </th>
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Location City/State
                                            </th>
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Offense
                                            </th>
                                            <th
                                                class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                Vehicle Type
                                            </th>

                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Initial row -->
                                        <tr class="border border-gray-200 dark:border-gray-700">
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="date" name="violation_date[]"
                                                    value="{{ old('violation_date.0') }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="violation_location[]"
                                                    value="{{ old('violation_location.0') }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="offense[]" value="{{ old('offense.0') }}"
                                                    placeholder="0"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td
                                                class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="vehicle_type[]"
                                                    value="{{ old('vehicle_type.0') }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                        </tr>
                                        <!-- Additional rows will be appended here by JavaScript -->
                                    </tbody>
                                </table>
                            </div>

                            <div class="my-4 flex justify-between">
                                <div>
                                    <button type="button" id="violation_add"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-lg shadow-theme-xs hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Add Another Violation
                                    </button>
                                </div>
                                <div>
                                    <button type="button" id="violation_remove"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-red-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-red-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Remove Last Violation
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Forfeitures Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Forfeitures Previous 3 Years
                            </h3>
                            <!-- Question wrapper -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">A. Have you ever been
                                    denied a
                                    license, permit or privilege to operate a motor vehicle? <span
                                        class="text-red-500">*</span>
                                </p>
                                <div class="flex items-center gap-6">
                                    <!-- YES -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="denied_license" value="yes"
                                            {{ old('denied_license') == 'yes' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                    </label>
                                    <!-- NO -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="denied_license" value="no"
                                            {{ old('denied_license', 'no') == 'no' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                    </label>
                                </div>
                            </div>


                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">B. Has any license, permit
                                    or
                                    privilege ever been revoked? <span class="text-red-500">*</span></p>
                                <div class="flex items-center gap-6">
                                    <!-- YES -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="license_revoked" value="yes"
                                            {{ old('license_revoked') == 'yes' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                    </label>
                                    <!-- NO -->
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="license_revoked" value="no"
                                            {{ old('license_revoked', 'no') == 'no' ? 'checked' : '' }}
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                    </label>
                                </div>
                            </div>

                            <div class="">
                                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">C. If yes to either
                                    question above,
                                    briefly describe the circumstances.
                                </p>
                                <textarea name="forfeitures" rows="3"
                                    class="w-full mt-2 p-2 border border-gray-300 rounded-lg focus:ring-brand-500/20 focus:border-brand-500 
                        g-gray-900 dark:text-gray-300">
                            {{ old('forfeitures') }}</textarea>


                            </div>
                        </div>

                    </div>


                    <!-- Employment Record Section -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Employment Record Previous 3 Years
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <p class="text-sm text-red-500 dark:text-red-400 pb-4">List all employers for the previous 3
                                years and
                                an additional 7 years if you were employed as a DRIVER.
                            </p>

                            <div id="employment_record_fields">
                                <!-- Initial employment record field -->
                                <div
                                    class="employment-record-field mb-8 p-6 border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700">
                                    <h4 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white/90">Employment
                                        Record 1
                                    </h4>

                                    <div class="pb-4">
                                        <label for="employer_name_0"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Employer Name
                                        </label>
                                        <input type="text" id="employer_name_0" name="employer_name[]"
                                            value="{{ old('employer_name.0') }}"
                                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                    </div>

                                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
                                        <div>
                                            <label for="employer_record_address_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Address
                                            </label>
                                            <input type="text" id="employer_record_address_0"
                                                name="employer_record_address[]"
                                                value="{{ old('employer_record_address.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>

                                        <div>
                                            <label for="employer_record_city_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                City
                                            </label>
                                            <input type="text" id="employer_record_city_0"
                                                name="employer_record_city[]" value="{{ old('employer_record_city.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                    </div>

                                    <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <label for="employer_record_country_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Country
                                            </label>
                                            <select id="employer_record_country_0" name="employer_record_country[]"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                <option value="">Select Country</option>
                                                @foreach ($countries as $country)
                                                    <option value="{{ $country->id }}"
                                                        {{ old('employer_record_country.0', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                                        {{ $country->name }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div>
                                            <label for="employer_record_state_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                State
                                            </label>
                                            <select id="employer_record_state_0" name="employer_record_state[]"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                                                <option value="">Select State</option>
                                                @if (isset($states) && $states->count())
                                                    @foreach ($states as $state)
                                                        <option value="{{ $state->id }}"
                                                            {{ old('employer_record_state.0') == $state->id ? 'selected' : '' }}>
                                                            {{ $state->name }}
                                                        </option>
                                                    @endforeach
                                                @endif
                                            </select>
                                        </div>

                                        <div>
                                            <label for="employer_record_postal_code_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Postal Code
                                            </label>
                                            <input type="text" id="employer_record_postal_code_0"
                                                name="employer_record_postal_code[]"
                                                value="{{ old('employer_record_postal_code.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                    </div>

                                    <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <label for="employer_record_phone_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Phone
                                            </label>
                                            <input type="text" id="employer_record_phone_0"
                                                name="employer_record_phone[]"
                                                value="{{ old('employer_record_phone.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>

                                        <div>
                                            <label for="employer_record_fax_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Fax
                                            </label>
                                            <input type="text" id="employer_record_fax_0" name="employer_record_fax[]"
                                                value="{{ old('employer_record_fax.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>

                                        <div>
                                            <label for="employer_record_email_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Email
                                            </label>
                                            <input type="email" id="employer_record_email_0"
                                                name="employer_record_email[]"
                                                value="{{ old('employer_record_email.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                    </div>

                                    <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                        <div>
                                            <label for="employer_record_position_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Position Held
                                            </label>
                                            <input type="text" id="employer_record_position_0"
                                                name="employer_record_position[]"
                                                value="{{ old('employer_record_position.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>

                                        <div>
                                            <label for="employer_record_date_from_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Date From
                                            </label>
                                            <input type="date" id="employer_record_date_from_0"
                                                name="employer_record_date_from[]"
                                                value="{{ old('employer_record_date_from.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>

                                        <div>
                                            <label for="employer_record_date_to_0"
                                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                                Date To
                                            </label>
                                            <input type="date" id="employer_record_date_to_0"
                                                name="employer_record_date_to[]"
                                                value="{{ old('employer_record_date_to.0') }}"
                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                        </div>
                                    </div>

                                    <div class="my-4">
                                        <label for="employer_record_reason_for_leaving_0"
                                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                            Reason for Leaving
                                        </label>
                                        <input type="text" id="employer_record_reason_for_leaving_0"
                                            name="employer_record_reason_for_leaving[]"
                                            value="{{ old('employer_record_reason_for_leaving.0') }}"
                                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                    </div>

                                    <!-- Question wrapper -->
                                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Were you subject to
                                            the
                                            DOT/FMCSA regulations while employed by this carrier?
                                        </p>
                                        <div class="flex items-center gap-6">
                                            <!-- YES -->
                                            <label class="inline-flex items-center cursor-pointer select-none">
                                                <input type="radio" name="employed_regulations[]" value="yes"
                                                    {{ old('employed_regulations.0') == 'yes' ? 'checked' : '' }}
                                                    class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                            </label>
                                            <!-- NO -->
                                            <label class="inline-flex items-center cursor-pointer select-none">
                                                <input type="radio" name="employed_regulations[]" value="no"
                                                    {{ old('employed_regulations.0', 'no') == 'no' ? 'checked' : '' }}
                                                    class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Was your job
                                            designated as
                                            a
                                            safety sensitive function, in any DOT regulated mode, subject to the alcohol and
                                            controlled substances testing requirements required by 49 CFR Part 40?
                                        </p>
                                        <div class="flex items-center gap-6">
                                            <!-- YES -->
                                            <label class="inline-flex items-center cursor-pointer select-none">
                                                <input type="radio" name="safety_sensitive_function[]" value="yes"
                                                    {{ old('safety_sensitive_function.0') == 'yes' ? 'checked' : '' }}
                                                    class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                            </label>
                                            <!-- NO -->
                                            <label class="inline-flex items-center cursor-pointer select-none">
                                                <input type="radio" name="safety_sensitive_function[]" value="no"
                                                    {{ old('safety_sensitive_function.0', 'no') == 'no' ? 'checked' : '' }}
                                                    class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <!-- Additional employment records will be appended here by JavaScript -->
                            </div>

                            <div class="my-4 flex justify-between">
                                <div>
                                    <button type="button" id="employment_record_add"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-lg shadow-theme-xs hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Add Another Employment Record
                                    </button>
                                </div>
                                <div>
                                    <button type="button" id="employment_record_remove"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-red-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-red-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        Remove Last Employment Record
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>


                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-end pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.index') }}"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                            Cancel
                        </a>

                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                            Continue
                        </button>
                    </div>
                </form>
            </div>
            <div class="md:col-span-3">
                @include('components.progress-bar', ['currentStep' => $currentStep])
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Format SSN input
            const ssnInput = document.getElementById('ssn');
            if (ssnInput) {
                ssnInput.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 3 && value.length <= 5) {
                        value = value.slice(0, 3) + '-' + value.slice(3);
                    } else if (value.length > 5) {
                        value = value.slice(0, 3) + '-' + value.slice(3, 5) + '-' + value.slice(5, 9);
                    }
                    e.target.value = value;
                });
            }

            // Format phone numbers
            const phoneInputs = document.querySelectorAll('input[type="tel"]');
            phoneInputs.forEach(input => {
                input.addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '');
                    if (value.length > 3 && value.length <= 6) {
                        value = '(' + value.slice(0, 3) + ') ' + value.slice(3);
                    } else if (value.length > 6) {
                        value = '(' + value.slice(0, 3) + ') ' + value.slice(3, 6) + '-' + value
                            .slice(6, 10);
                    }
                    e.target.value = value;
                });
            });

            // Same address checkbox functionality
            const sameAddressCheckbox = document.getElementById('same_address_checkbox');
            if (sameAddressCheckbox) {
                sameAddressCheckbox.addEventListener('change', function() {
                    if (this.checked) {
                        // Copy main address to first residence
                        const mainAddress = document.getElementById('address').value;
                        const mainCity = document.getElementById('city').value;
                        const mainCountry = document.getElementById('country').value;
                        const mainState = document.getElementById('state').value;
                        const mainPostalCode = document.getElementById('postal_code').value;

                        document.getElementById('residence_address_0').value = mainAddress;
                        document.getElementById('residence_city_0').value = mainCity;
                        document.getElementById('residence_country_0').value = mainCountry;
                        document.getElementById('residence_state_0').value = mainState;
                        document.getElementById('residence_postal_code_0').value = mainPostalCode;
                    }
                });
            }

            // Residence fields management
            let residenceCount = 1;
            const residenceFields = document.getElementById('residence_fields');
            const residenceAddBtn = document.getElementById('residence_add');
            const residenceRemoveBtn = document.getElementById('residence_remove');

            if (residenceAddBtn) {
                residenceAddBtn.addEventListener('click', function() {
                    const newField = document.createElement('div');
                    newField.className =
                        'residence-field mb-6 p-4 border border-gray-200 rounded-lg dark:border-gray-700';
                    newField.innerHTML = `
                <div class="pb-4">
                    <label for="residence_address_${residenceCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Address
                    </label>
                    <input type="text" id="residence_address_${residenceCount}" name="residence_address[]"
                        class="residence-address shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="residence_city_${residenceCount}"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            City
                        </label>
                        <input type="text" id="residence_city_${residenceCount}" name="residence_city[]"
                            class="residence-city shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>

                    <div>
                        <label for="residence_country_${residenceCount}"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Country
                        </label>
                        <select id="residence_country_${residenceCount}" name="residence_country[]"
                            class="residence-country shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                            <option value="">Select Country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="residence_state_${residenceCount}"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            State
                        </label>
                        <select id="residence_state_${residenceCount}" name="residence_state[]"
                            class="residence-state shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                            <option value="">Select State</option>
                            @if (isset($states) && $states->count())
                                @foreach ($states as $state)
                                    <option value="{{ $state->id }}">{{ $state->name }}</option>
                                @endforeach
                            @endif
                        </select>
                    </div>

                    <div>
                        <label for="residence_postal_code_${residenceCount}"
                            class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                            Postal Code
                        </label>
                        <input type="text" id="residence_postal_code_${residenceCount}" name="residence_postal_code[]"
                            class="residence-postal-code shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                    </div>
                </div>
            `;
                    residenceFields.appendChild(newField);
                    residenceCount++;
                });
            }

            if (residenceRemoveBtn) {
                residenceRemoveBtn.addEventListener('click', function() {
                    const fields = document.querySelectorAll('.residence-field');
                    if (fields.length > 1) {
                        fields[fields.length - 1].remove();
                        residenceCount--;
                    }
                });
            }

            // Accident fields management
            let accidentCount = 1;
            const accidentAddBtn = document.getElementById('accident_add');
            const accidentRemoveBtn = document.getElementById('accident_remove');
            const accidentTableBody = document.querySelector('#accident_fields tbody');

            if (accidentAddBtn && accidentTableBody) {
                accidentAddBtn.addEventListener('click', function() {
                    const newRow = document.createElement('tr');
                    newRow.className = 'border border-gray-200 dark:border-gray-700';
                    newRow.innerHTML = `
            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                <input type="date" name="accident_date[]"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </td>
            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                <input type="text" name="accident_location[]"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </td>
            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                <input type="text" name="number_of_injuries[]" placeholder="0"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </td>
            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                <input type="text" name="number_of_fatalities[]" placeholder="0"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </td>
            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                <select name="hazmat_spill[]"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                    <option value="no">No</option>
                    <option value="yes">Yes</option>
                </select>
            </td>
        `;
                    accidentTableBody.appendChild(newRow);
                    accidentCount++;
                });
            }

            if (accidentRemoveBtn && accidentTableBody) {
                accidentRemoveBtn.addEventListener('click', function() {
                    const rows = accidentTableBody.querySelectorAll('tr');
                    if (rows.length > 1) { // Keep at least one row
                        rows[rows.length - 1].remove();
                        accidentCount--;
                    }
                });
            }


            // violation fields management
            let violationCount = 1;
            const violationFields = document.getElementById('violation_fields');
            const violationAddBtn = document.getElementById('violation_add');
            const violationRemoveBtn = document.getElementById('violation_remove');
            const violationTableBody = document.querySelector('#violation_fields tbody');

            if (violationAddBtn && violationTableBody) {
                violationAddBtn.addEventListener('click', function() {
                    const newRow = document.createElement('tr');
                    newRow.className = 'border border-gray-200 dark:border-gray-700';
                    newRow.innerHTML = `
                <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                    <input type="date" name="violation_date[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </td>
                <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                    <input type="text" name="violation_location[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </td>
                <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                    <input type="text" name="offense[]" placeholder="0"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </td>
                <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                    <input type="text" name="vehicle_type[]" placeholder="0"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </td>
            `;
                    violationTableBody.appendChild(newRow);
                    violationCount++;
                });
            }

            if (violationRemoveBtn && violationTableBody) {
                violationRemoveBtn.addEventListener('click', function() {
                    const rows = violationTableBody.querySelectorAll('tr');
                    if (rows.length > 1) { // Keep at least one row
                        rows[rows.length - 1].remove();
                        violationCount--;
                    }
                });
            }



            // Employment Record fields management
            let employmentRecordCount = 1;
            const employmentRecordFields = document.getElementById('employment_record_fields');
            const employmentRecordAddBtn = document.getElementById('employment_record_add');
            const employmentRecordRemoveBtn = document.getElementById('employment_record_remove');

            if (employmentRecordAddBtn) {
                employmentRecordAddBtn.addEventListener('click', function() {
                    const newField = document.createElement('div');
                    newField.className =
                        'employment-record-field mb-8 p-6 border border-gray-300 rounded-lg bg-gray-50 dark:bg-gray-800 dark:border-gray-700';
                    newField.innerHTML = `
            <h4 class="text-lg font-semibold mb-4 text-gray-800 dark:text-white/90">Employment Record ${employmentRecordCount + 1}</h4>
            
            <div class="pb-4">
                <label for="employer_name_${employmentRecordCount}"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Employer Name
                </label>
                <input type="text" id="employer_name_${employmentRecordCount}" name="employer_name[]"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
                <div>
                    <label for="employer_record_address_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Address
                    </label>
                    <input type="text" id="employer_record_address_${employmentRecordCount}" name="employer_record_address[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>

                <div>
                    <label for="employer_record_city_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        City
                    </label>
                    <input type="text" id="employer_record_city_${employmentRecordCount}" name="employer_record_city[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>
            </div>

            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="employer_record_country_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Country
                    </label>
                    <select id="employer_record_country_${employmentRecordCount}" name="employer_record_country[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <option value="">Select Country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="employer_record_state_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        State
                    </label>
                    <select id="employer_record_state_${employmentRecordCount}" name="employer_record_state[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30">
                        <option value="">Select State</option>
                        @if (isset($states) && $states->count())
                            @foreach ($states as $state)
                                <option value="{{ $state->id }}">{{ $state->name }}</option>
                            @endforeach
                        @endif
                    </select>
                </div>

                <div>
                    <label for="employer_record_postal_code_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Postal Code
                    </label>
                    <input type="text" id="employer_record_postal_code_${employmentRecordCount}" name="employer_record_postal_code[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>
            </div>

            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="employer_record_phone_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Phone
                    </label>
                    <input type="text" id="employer_record_phone_${employmentRecordCount}" name="employer_record_phone[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>

                <div>
                    <label for="employer_record_fax_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Fax
                    </label>
                    <input type="text" id="employer_record_fax_${employmentRecordCount}" name="employer_record_fax[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>

                <div>
                    <label for="employer_record_email_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Email
                    </label>
                    <input type="email" id="employer_record_email_${employmentRecordCount}" name="employer_record_email[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>
            </div>

            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="employer_record_position_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Position Held
                    </label>
                    <input type="text" id="employer_record_position_${employmentRecordCount}" name="employer_record_position[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>

                <div>
                    <label for="employer_record_date_from_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Date From
                    </label>
                    <input type="date" id="employer_record_date_from_${employmentRecordCount}" name="employer_record_date_from[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>

                <div>
                    <label for="employer_record_date_to_${employmentRecordCount}"
                        class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                        Date To
                    </label>
                    <input type="date" id="employer_record_date_to_${employmentRecordCount}" name="employer_record_date_to[]"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </div>
            </div>

            <div class="my-4">
                <label for="employer_record_reason_for_leaving_${employmentRecordCount}"
                    class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                    Reason for Leaving
                </label>
                <input type="text" id="employer_record_reason_for_leaving_${employmentRecordCount}" name="employer_record_reason_for_leaving[]"
                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Were you subject to the DOT/FMCSA regulations while employed by this carrier?</p>
                <div class="flex items-center gap-6">
                    <label class="inline-flex items-center cursor-pointer select-none">
                        <input type="radio" name="employed_regulations[]" value="yes"
                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer select-none">
                        <input type="radio" name="employed_regulations[]" value="no" checked
                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-4">
                <p class="text-sm font-medium text-gray-700 dark:text-gray-300">Was your job designated as a safety sensitive function?</p>
                <div class="flex items-center gap-6">
                    <label class="inline-flex items-center cursor-pointer select-none">
                        <input type="radio" name="safety_sensitive_function[]" value="yes"
                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                    </label>
                    <label class="inline-flex items-center cursor-pointer select-none">
                        <input type="radio" name="safety_sensitive_function[]" value="no" checked
                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                    </label>
                </div>
            </div>
        `;
                    employmentRecordFields.appendChild(newField);
                    employmentRecordCount++;
                });
            }

            if (employmentRecordRemoveBtn) {
                employmentRecordRemoveBtn.addEventListener('click', function() {
                    const fields = document.querySelectorAll('.employment-record-field');
                    if (fields.length > 1) { // Keep at least one field
                        fields[fields.length - 1].remove();
                        employmentRecordCount--;
                    }
                });
            }



            // License number validation
            const licenseNumber = document.getElementById('license_number');
            const repeatLicenseNumber = document.getElementById('repeat_license_number');

            if (licenseNumber && repeatLicenseNumber) {
                repeatLicenseNumber.addEventListener('input', function() {
                    if (licenseNumber.value !== repeatLicenseNumber.value) {
                        repeatLicenseNumber.classList.add('border-red-500');
                    } else {
                        repeatLicenseNumber.classList.remove('border-red-500');
                    }
                });
            }

            // Form validation before submission
            const form = document.getElementById('driverForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Validate license numbers match
                    if (licenseNumber && repeatLicenseNumber && licenseNumber.value !== repeatLicenseNumber
                        .value) {
                        e.preventDefault();
                        alert('License numbers do not match. Please check and try again.');
                        repeatLicenseNumber.focus();
                        return false;
                    }

                    // Validate dates
                    const licenseIssued = document.getElementById('license_issued');
                    const licenseExpires = document.getElementById('license_expires');

                    if (licenseIssued && licenseExpires) {
                        const issued = new Date(licenseIssued.value);
                        const expires = new Date(licenseExpires.value);

                        if (issued >= expires) {
                            e.preventDefault();
                            alert('License expiration date must be after the issued date.');
                            licenseExpires.focus();
                            return false;
                        }
                    }

                    // Validate date of birth (must be at least 18 years old)
                    const dateOfBirth = document.getElementById('date_of_birth');
                    if (dateOfBirth) {
                        const dob = new Date(dateOfBirth.value);
                        const today = new Date();
                        const age = today.getFullYear() - dob.getFullYear();
                        const monthDiff = today.getMonth() - dob.getMonth();

                        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                            age--;
                        }

                        if (age < 18) {
                            e.preventDefault();
                            alert('Driver must be at least 18 years old.');
                            dateOfBirth.focus();
                            return false;
                        }
                    }
                });
            }
        });
    </script>
@endpush
