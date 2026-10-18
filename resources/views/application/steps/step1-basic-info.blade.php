@extends('layouts.application-form-layout')

@section('title', 'Step 1: Basic Information')

@section('content')
    <x-application.step :company="$company" :driver="$driver" :step="1" title="Driver's Application"
        description="Your personal details, residences, license, experience and employment history.">
        <x-slot:aside>
            <div
                class="hidden lg:block rounded-xl border p-4 bg-card">
                <label for="driver-section-jump"
                    class="label text-xs mb-2">Jump to section</label>
                <select id="driver-section-jump"
                    class="select w-full driver-section-jump mt-2">
                    <option value="">Select…</option>
                    <option value="section-applicant">Applicant</option>
                    <option value="section-residences">Residences</option>
                    <option value="section-license">License</option>
                    <option value="section-experience">Experience</option>
                    <option value="section-accidents">Accidents</option>
                    <option value="section-violations">Violations</option>
                    <option value="section-employment">Employment</option>
                    <option value="section-photo">Photo</option>
                </select>
            </div>
        </x-slot:aside>

        <form action="{{ route('public.application.store.step1', ['slug' => $company->slug]) }}" method="POST"
            id="driverForm" enctype="multipart/form-data" class="grid grid-cols-1">
            @csrf

            <div class="mb-4 lg:hidden rounded-xl border p-4 bg-card">
                <label for="driver-section-jump-mobile"
                    class="label text-xs mb-2">Jump to section</label>
                <select id="driver-section-jump-mobile"
                    class="select w-full driver-section-jump mt-2">
                    <option value="">Select…</option>
                    <option value="section-applicant">Applicant</option>
                    <option value="section-residences">Residences</option>
                    <option value="section-license">License</option>
                    <option value="section-experience">Experience</option>
                    <option value="section-accidents">Accidents</option>
                    <option value="section-violations">Violations</option>
                    <option value="section-employment">Employment</option>
                    <option value="section-photo">Photo</option>
                </select>
            </div>

            <!-- Personal Information Section -->
            <div id="section-applicant"
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Applicant Information
                    </h3>
                </div>
                <div class="p-5 sm:p-6 space-y-6">
                    <!-- Name Row -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="first_name"
                                class="label mb-2">
                                First Name <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="first_name" name="first_name"
                                value="{{ old('first_name') }}" required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="middle_name"
                                class="label mb-2">
                                Middle Name
                            </label>
                            <input type="text" id="middle_name" name="middle_name"
                                value="{{ old('middle_name') }}"
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="last_name"
                                class="label mb-2">
                                Last Name <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="last_name" name="last_name" value="{{ old('last_name') }}"
                                required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="suffix"
                                class="label mb-2">
                                Suffix
                            </label>
                            <input type="text" id="suffix" name="suffix" value="{{ old('suffix') }}"
                                class="input w-full" />
                        </div>
                    </div>

                    <!-- Business Information Section -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="business_name"
                                class="label mb-2">
                                Business Name
                            </label>
                            <input type="text" id="business_name" name="business_name"
                                value="{{ old('business_name') }}"
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="employer_identification_number"
                                class="label mb-2">
                                Employer Identification Number
                            </label>
                            <input type="text" id="employer_identification_number"
                                name="employer_identification_number"
                                value="{{ old('employer_identification_number') }}"
                                class="input w-full" />
                        </div>
                    </div>

                    <div>
                        <label class="label mb-2">
                            Federal Tax Classification
                        </label>
                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            @foreach (['individual_sole_proprietor' => 'Individual/Sole Proprietor', 'c_corporation' => 'C Corporation', 's_corporation' => 'S Corporation', 'llc' => 'LLC'] as $value => $label)
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="federal_tax_classification"
                                        value="{{ $value }}"
                                        {{ old('federal_tax_classification') == $value ? 'checked' : '' }}
                                        class="input" />
                                    <span
                                        class="ml-2 text-sm">{{ $label }}</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <!-- Personal Details -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="date_of_birth"
                                class="label mb-2">
                                Date of Birth <span class="text-destructive">*</span>
                            </label>
                            <input type="date" id="date_of_birth" name="date_of_birth"
                                value="{{ old('date_of_birth') }}" required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="ssn"
                                class="label mb-2">
                                Social Security Number <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="ssn" name="ssn" value="{{ old('ssn') }}"
                                required placeholder="XXX-XX-XXXX"
                                class="input w-full" />
                        </div>
                    </div>

                    <!-- Contact Information -->
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <div>
                            <label for="main_phone"
                                class="label mb-2">
                                Main Phone Number <span class="text-destructive">*</span>
                            </label>
                            <input type="tel" id="main_phone" name="main_phone"
                                value="{{ old('main_phone') }}" required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="alt_phone"
                                class="label mb-2">
                                Alt Phone Number
                            </label>
                            <input type="tel" id="alt_phone" name="alt_phone"
                                value="{{ old('alt_phone') }}"
                                class="input w-full" />
                        </div>
                    </div>

                    <div>
                        <label for="email"
                            class="label mb-2">
                            Email Address <span class="text-destructive">*</span>
                        </label>
                        <input type="email" id="email" name="email" value="{{ old('email') }}"
                            required
                            class="input w-full" />
                    </div>


                    <!-- Address Information Section -->
                    <div>
                        <label for="address"
                            class="label mb-2">
                            Address <span class="text-destructive">*</span>
                        </label>
                        <input type="text" id="address" name="address" value="{{ old('address') }}"
                            required
                            class="input w-full" />
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="city"
                                class="label mb-2">
                                City <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="city" name="city" value="{{ old('city') }}"
                                required
                                class="input w-full" />
                        </div>


                        <div>
                            <label for="country"
                                class="label mb-2">
                                Country <span class="text-destructive">*</span>
                            </label>
                            <select id="country" name="country" required
                                class="select w-full">
                                <option value="">Select Country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}"
                                        {{ old('country', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('country')
                                <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="state"
                                class="label mb-2">
                                State <span class="text-destructive">*</span>
                            </label>
                            <select id="state" name="state" required
                                class="select w-full">
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
                                <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="postal_code"
                                class="label mb-2">
                                Postal Code <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="postal_code" name="postal_code"
                                value="{{ old('postal_code') }}" required
                                class="input w-full" />
                        </div>
                    </div>

                    <!-- Additional Documents -->
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="label inline-flex items-center gap-2 font-normal">
                            <input type="checkbox" name="twic_card" value="1"
                                {{ old('twic_card') ? 'checked' : '' }}
                                class="input" />
                            <span class="ml-2 text-sm">Do you have a TWIC
                                Card?</span>
                        </label>

                        <label class="label inline-flex items-center gap-2 font-normal">
                            <input type="checkbox" name="passport" value="1"
                                {{ old('passport') ? 'checked' : '' }}
                                class="input" />
                            <span class="ml-2 text-sm">Do you have a
                                passport?</span>
                        </label>
                    </div>

                    <div id="section-photo" class="pt-4 border-t">
                        <x-driver-photo-upload />
                    </div>
                </div>
            </div>


            <!-- Residences Section -->
            <div id="section-residences"
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Residences Previous 3 Years
                    </h3>
                </div>
                <div class="p-5 sm:p-6">
                    <label class="label inline-flex items-center gap-2 font-normal pb-4">
                        <input type="checkbox" name="same_address" value="1" id="same_address_checkbox"
                            {{ old('same_address') ? 'checked' : '' }}
                            class="input" />
                        <span class="ml-2 text-sm">Same address as from
                            Applicant
                            Information.</span>
                    </label>

                    <p class="text-sm pb-4 text-muted-foreground">List residence for previous 3
                        years if
                        you
                        lived at
                        the above address less than 3 years.
                    </p>

                    <div id="residence_fields">
                        <!-- Initial residence field -->
                        <div
                            class="residence-field mb-6 p-4 border rounded-lg">
                            <div class="pb-4">
                                <label for="residence_address_0"
                                    class="label mb-2">
                                    Address
                                </label>
                                <input type="text" id="residence_address_0" name="residence_address[]"
                                    value="{{ old('residence_address.0') }}"
                                    class="input w-full residence-address" />
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                                <div>
                                    <label for="residence_city_0"
                                        class="label mb-2">
                                        City
                                    </label>
                                    <input type="text" id="residence_city_0" name="residence_city[]"
                                        value="{{ old('residence_city.0') }}"
                                        class="input w-full residence-city" />
                                </div>

                                <div>
                                    <label for="residence_country_0"
                                        class="label mb-2">
                                        Country
                                    </label>
                                    <select id="residence_country_0" name="residence_country[]"
                                        class="select w-full residence-country">
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
                                        class="label mb-2">
                                        State
                                    </label>
                                    <select id="residence_state_0" name="residence_state[]"
                                        class="select w-full residence-state">
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
                                        class="label mb-2">
                                        Postal Code
                                    </label>
                                    <input type="text" id="residence_postal_code_0"
                                        name="residence_postal_code[]"
                                        value="{{ old('residence_postal_code.0') }}"
                                        class="input w-full residence-postal-code" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="my-4 flex justify-between">
                        <div>
                            <button type="button" id="residence_add"
                                class="btn" data-variant="secondary">
                                Add Another Residence
                            </button>
                        </div>
                        <div>
                            <button type="button" id="residence_remove"
                                class="btn" data-variant="destructive">
                                Remove Last Residence
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Driving License Section -->
            <div id="section-license"
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Drivers License Information
                    </h3>
                </div>
                <div class="p-5 sm:p-6">
                    <p class="text-sm pb-4 text-muted-foreground">List all driver licenses held
                        within
                        the last
                        3 years. Enter your first and last name exactly as it appears on your license.
                    </p>
                    <div
                        class="rounded-lg mb-4 py-2 px-4 border">
                        <p>Warning! Triple check the license information for accuracy. If you enter the wrong
                            information,
                            all documents relating to your license will have to be discarded and completed
                            again.
                            Failure to
                            enter accurate license information may result in non-consideration and a rejected
                            application!
                        </p>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <div>
                            <label for="license_first_name"
                                class="label mb-2">
                                First Name <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="license_first_name" name="license_first_name"
                                value="{{ old('license_first_name') }}" required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="license_last_name"
                                class="label mb-2">
                                Last Name <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="license_last_name" name="license_last_name"
                                value="{{ old('license_last_name') }}" required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="license_issued"
                                class="label mb-2">
                                Issued <span class="text-destructive">*</span>
                            </label>
                            <input type="date" id="license_issued" name="license_issued"
                                value="{{ old('license_issued') }}" required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="license_expires"
                                class="label mb-2">
                                Expires <span class="text-destructive">*</span>
                            </label>
                            <input type="date" id="license_expires" name="license_expires"
                                value="{{ old('license_expires') }}" required
                                class="input w-full" />
                        </div>
                    </div>

                    <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <label for="license_country"
                                class="label mb-2">
                                Country <span class="text-destructive">*</span>
                            </label>
                            <select id="license_country" name="license_country" required
                                class="select w-full">
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
                                class="label mb-2">
                                State <span class="text-destructive">*</span>
                            </label>
                            <select id="license_state" name="license_state" required
                                class="select w-full">
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
                                class="label mb-2">
                                Class <span class="text-destructive">*</span>
                            </label>
                            <select id="license_class" name="license_class" required
                                class="select w-full">
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
                                <option value="Class D"
                                    {{ old('license_class') == 'Class D' ? 'selected' : '' }}>
                                    Class D
                                </option>
                                <option value="Class C (Non-Commercial)"
                                    {{ old('license_class') == 'Class C (Non-Commercial)' ? 'selected' : '' }}>
                                    Class C
                                    (Non-Commercial)
                                </option>
                                <option value="Class M"
                                    {{ old('license_class') == 'Class M' ? 'selected' : '' }}>
                                    Class M
                                </option>
                                <option value="Class E"
                                    {{ old('license_class') == 'Class E' ? 'selected' : '' }}>
                                    Class E
                                </option>
                                <option value="Class F"
                                    {{ old('license_class') == 'Class F' ? 'selected' : '' }}>
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
                                class="label mb-2">
                                License Number <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="license_number" name="license_number"
                                value="{{ old('license_number') }}" required
                                class="input w-full" />
                        </div>

                        <div>
                            <label for="repeat_license_number"
                                class="label mb-2">
                                Repeat License Number: <span class="text-destructive">*</span>
                            </label>
                            <input type="text" id="repeat_license_number" name="repeat_license_number"
                                value="{{ old('repeat_license_number') }}" required
                                class="input w-full" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <p class="mb-2 text-sm font-medium">Endorsements:</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="checkbox" name="is_h_placarded_hazmat" value="1"
                                    {{ old('is_h_placarded_hazmat') ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">H - Placarded
                                    Hazmat.</span>
                            </label>
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="checkbox" name="is_n_tank_vehicle" value="1"
                                    {{ old('is_n_tank_vehicle') ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">N - Tank
                                    Vehicles.</span>
                            </label>
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="checkbox" name="is_p_passengers" value="1"
                                    {{ old('is_p_passengers') ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">P -
                                    Passengers.</span>
                            </label>
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="checkbox" name="is_t_double_trailer" value="1"
                                    {{ old('is_t_double_trailer') ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">T - Double/Triple
                                    Trailers.</span>
                            </label>
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="checkbox" name="is_s_school_bus" value="1"
                                    {{ old('is_s_school_bus') ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">S - School
                                    Bus.</span>
                            </label>
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="checkbox" name="is_x_placarded_hazmat" value="1"
                                    {{ old('is_x_placarded_hazmat') ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">X - Placarded
                                    Hazmat &
                                    Tank
                                    Vehicles.</span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Experience Section -->
            <div id="section-experience"
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Experience
                    </h3>
                </div>
                <div class="p-5 sm:p-6">
                    <div class="overflow-x-auto"><table class="w-full min-w-[40rem]">
                        <thead>
                            <tr class="border">
                                <th
                                    class="border p-3 text-left text-sm font-semibold">
                                    Equipment Class
                                </th>
                                <th
                                    class="border p-3 text-left text-sm font-semibold">
                                    Experience
                                </th>
                                <th
                                    class="border p-3 text-left text-sm font-semibold">
                                    Date From MM/DO/YYYY
                                </th>
                                <th
                                    class="border p-3 text-left text-sm font-semibold">
                                    Date To MM/DO/YYYY
                                </th>
                                <th
                                    class="border p-3 text-left text-sm font-semibold">
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
                                <tr class="border">
                                    <td
                                        class="border p-3 text-sm">
                                        {{ $equipmentClass }}
                                        <input type="hidden" name="equipment_class[]"
                                            value="{{ $equipmentClass }}">
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <select name="experience[]"
                                            class="select w-full">
                                            <option value="no"
                                                {{ old('experience.' . $index) == 'no' ? 'selected' : '' }}>No
                                            </option>
                                            <option value="yes"
                                                {{ old('experience.' . $index) == 'yes' ? 'selected' : '' }}>
                                                Yes
                                            </option>
                                        </select>
                                    </td>

                                    <td
                                        class="border p-3 text-sm">
                                        <input type="date" name="experience_from_date[]"
                                            value="{{ old('experience_from_date.' . $index) }}"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="date" name="experience_to_date[]"
                                            value="{{ old('experience_to_date.' . $index) }}"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="text" name="approx_miles[]"
                                            value="{{ old('approx_miles.' . $index) }}" placeholder="0"
                                            class="input w-full" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table></div>
                </div>
            </div>


            <!-- Accidents/Crashes Section -->
            <div id="section-accidents"
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Accidents/Crashes Previous 3 Years
                    </h3>
                    <!-- Question wrapper -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                        <p class="text-sm font-medium">Have you had any
                            accidents/crashes
                            in the last 3 years? <span class="text-destructive">*</span></p>
                        <div class="flex items-center gap-6">
                            <!-- YES -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="accident" value="yes"
                                    {{ old('accident') == 'yes' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">Yes</span>
                            </label>
                            <!-- NO -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="accident" value="no"
                                    {{ old('accident', 'no') == 'no' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">No</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <div id="accident_fields">
                        <div class="overflow-x-auto"><table class="w-full min-w-[40rem]">
                            <thead>
                                <tr class="border">
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Date MM/DO/YYYY
                                    </th>
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Location City/State
                                    </th>
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Number of Injuries
                                    </th>
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Number of Fatalities
                                    </th>
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Hazmat Spill?
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Initial row -->
                                <tr class="border">
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="date" name="accident_date[]"
                                            value="{{ old('accident_date.0') }}"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="text" name="accident_location[]"
                                            value="{{ old('accident_location.0') }}"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="text" name="number_of_injuries[]"
                                            value="{{ old('number_of_injuries.0') }}" placeholder="0"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="text" name="number_of_fatalities[]"
                                            value="{{ old('number_of_fatalities.0') }}" placeholder="0"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <select name="hazmat_spill[]"
                                            class="select w-full">
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
                        </table></div>
                    </div>

                    <div class="my-4 flex justify-between">
                        <div>
                            <button type="button" id="accident_add"
                                class="btn" data-variant="secondary">
                                Add Another Accident/Crash
                            </button>
                        </div>
                        <div>
                            <button type="button" id="accident_remove"
                                class="btn" data-variant="destructive">
                                Remove Last Accident
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Moving Traffic Violations Section -->
            <div id="section-violations"
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Moving Traffic Violations Previous 3 Years
                    </h3>
                    <!-- Question wrapper -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                        <p class="text-sm font-medium">Have you had any
                            traffic
                            violations
                            in the last 3 years? <span class="text-destructive">*</span></p>
                        <div class="flex items-center gap-6">
                            <!-- YES -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="violation" value="yes"
                                    {{ old('violation') == 'yes' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">Yes</span>
                            </label>
                            <!-- NO -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="violation" value="no"
                                    {{ old('violation', 'no') == 'no' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">No</span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="p-5 sm:p-6">
                    <div id="violation_fields">
                        <div class="overflow-x-auto"><table class="w-full min-w-[40rem]">
                            <thead>
                                <tr class="border">
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Date MM/DO/YYYY
                                    </th>
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Location City/State
                                    </th>
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Offense
                                    </th>
                                    <th
                                        class="border p-3 text-left text-sm font-semibold">
                                        Vehicle Type
                                    </th>

                                </tr>
                            </thead>
                            <tbody>
                                <!-- Initial row -->
                                <tr class="border">
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="date" name="violation_date[]"
                                            value="{{ old('violation_date.0') }}"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="text" name="violation_location[]"
                                            value="{{ old('violation_location.0') }}"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="text" name="offense[]"
                                            value="{{ old('offense.0') }}" placeholder="0"
                                            class="input w-full" />
                                    </td>
                                    <td
                                        class="border p-3 text-sm">
                                        <input type="text" name="vehicle_type[]"
                                            value="{{ old('vehicle_type.0') }}"
                                            class="input w-full" />
                                    </td>
                                </tr>
                                <!-- Additional rows will be appended here by JavaScript -->
                            </tbody>
                        </table></div>
                    </div>

                    <div class="my-4 flex justify-between">
                        <div>
                            <button type="button" id="violation_add"
                                class="btn" data-variant="secondary">
                                Add Another Violation
                            </button>
                        </div>
                        <div>
                            <button type="button" id="violation_remove"
                                class="btn" data-variant="destructive">
                                Remove Last Violation
                            </button>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Forfeitures Section -->
            <div
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Forfeitures Previous 3 Years
                    </h3>
                    <!-- Question wrapper -->
                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                        <p class="text-sm font-medium">A. Have you ever been
                            denied a
                            license, permit or privilege to operate a motor vehicle? <span
                                class="text-destructive">*</span>
                        </p>
                        <div class="flex items-center gap-6">
                            <!-- YES -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="denied_license" value="yes"
                                    {{ old('denied_license') == 'yes' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">Yes</span>
                            </label>
                            <!-- NO -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="denied_license" value="no"
                                    {{ old('denied_license', 'no') == 'no' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">No</span>
                            </label>
                        </div>
                    </div>


                    <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                        <p class="text-sm font-medium">B. Has any license,
                            permit
                            or
                            privilege ever been revoked? <span class="text-destructive">*</span></p>
                        <div class="flex items-center gap-6">
                            <!-- YES -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="license_revoked" value="yes"
                                    {{ old('license_revoked') == 'yes' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">Yes</span>
                            </label>
                            <!-- NO -->
                            <label class="label inline-flex items-center gap-2 font-normal">
                                <input type="radio" name="license_revoked" value="no"
                                    {{ old('license_revoked', 'no') == 'no' ? 'checked' : '' }}
                                    class="input" />
                                <span class="ml-2 text-sm">No</span>
                            </label>
                        </div>
                    </div>

                    <div class="">
                        <p class="text-sm font-medium">C. If yes to either
                            question above,
                            briefly describe the circumstances.
                        </p>
                        <textarea name="forfeitures" rows="3"
                            class="textarea w-full mt-2 p-2 g-gray-900">
                {{ old('forfeitures') }}</textarea>


                    </div>
                </div>

            </div>


            <!-- Employment Record Section -->
            <div id="section-employment"
                class="rounded-xl border mb-6 bg-card">
                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b">
                    <h3 class="text-lg font-semibold">
                        Employment Record Previous 3 Years
                    </h3>
                </div>
                <div class="p-5 sm:p-6">
                    <p class="text-sm pb-4 text-destructive">List all employers for the previous
                        3
                        years and
                        an additional 7 years if you were employed as a DRIVER.
                    </p>

                    <div id="employment_record_fields">
                        <!-- Initial employment record field -->
                        <div
                            class="employment-record-field mb-8 p-6 border rounded-lg bg-muted/40">
                            <h4 class="text-lg font-semibold mb-4">Employment
                                Record 1
                            </h4>

                            <div class="pb-4">
                                <label for="employer_name_0"
                                    class="label mb-2">
                                    Employer Name
                                </label>
                                <input type="text" id="employer_name_0" name="employer_name[]"
                                    value="{{ old('employer_name.0') }}"
                                    class="input w-full" />
                            </div>

                            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
                                <div>
                                    <label for="employer_record_address_0"
                                        class="label mb-2">
                                        Address
                                    </label>
                                    <input type="text" id="employer_record_address_0"
                                        name="employer_record_address[]"
                                        value="{{ old('employer_record_address.0') }}"
                                        class="input w-full" />
                                </div>

                                <div>
                                    <label for="employer_record_city_0"
                                        class="label mb-2">
                                        City
                                    </label>
                                    <input type="text" id="employer_record_city_0"
                                        name="employer_record_city[]"
                                        value="{{ old('employer_record_city.0') }}"
                                        class="input w-full" />
                                </div>
                            </div>

                            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label for="employer_record_country_0"
                                        class="label mb-2">
                                        Country
                                    </label>
                                    <select id="employer_record_country_0" name="employer_record_country[]"
                                        class="select w-full">
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
                                        class="label mb-2">
                                        State
                                    </label>
                                    <select id="employer_record_state_0" name="employer_record_state[]"
                                        class="select w-full">
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
                                        class="label mb-2">
                                        Postal Code
                                    </label>
                                    <input type="text" id="employer_record_postal_code_0"
                                        name="employer_record_postal_code[]"
                                        value="{{ old('employer_record_postal_code.0') }}"
                                        class="input w-full" />
                                </div>
                            </div>

                            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label for="employer_record_phone_0"
                                        class="label mb-2">
                                        Phone
                                    </label>
                                    <input type="text" id="employer_record_phone_0"
                                        name="employer_record_phone[]"
                                        value="{{ old('employer_record_phone.0') }}"
                                        class="input w-full" />
                                </div>

                                <div>
                                    <label for="employer_record_fax_0"
                                        class="label mb-2">
                                        Fax
                                    </label>
                                    <input type="text" id="employer_record_fax_0"
                                        name="employer_record_fax[]"
                                        value="{{ old('employer_record_fax.0') }}"
                                        class="input w-full" />
                                </div>

                                <div>
                                    <label for="employer_record_email_0"
                                        class="label mb-2">
                                        Email
                                    </label>
                                    <input type="email" id="employer_record_email_0"
                                        name="employer_record_email[]"
                                        value="{{ old('employer_record_email.0') }}"
                                        class="input w-full" />
                                </div>
                            </div>

                            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                                <div>
                                    <label for="employer_record_position_0"
                                        class="label mb-2">
                                        Position Held
                                    </label>
                                    <input type="text" id="employer_record_position_0"
                                        name="employer_record_position[]"
                                        value="{{ old('employer_record_position.0') }}"
                                        class="input w-full" />
                                </div>

                                <div>
                                    <label for="employer_record_date_from_0"
                                        class="label mb-2">
                                        Date From
                                    </label>
                                    <input type="date" id="employer_record_date_from_0"
                                        name="employer_record_date_from[]"
                                        value="{{ old('employer_record_date_from.0') }}"
                                        class="input w-full" />
                                </div>

                                <div>
                                    <label for="employer_record_date_to_0"
                                        class="label mb-2">
                                        Date To
                                    </label>
                                    <input type="date" id="employer_record_date_to_0"
                                        name="employer_record_date_to[]"
                                        value="{{ old('employer_record_date_to.0') }}"
                                        class="input w-full" />
                                </div>
                            </div>

                            <div class="my-4">
                                <label for="employer_record_reason_for_leaving_0"
                                    class="label mb-2">
                                    Reason for Leaving
                                </label>
                                <input type="text" id="employer_record_reason_for_leaving_0"
                                    name="employer_record_reason_for_leaving[]"
                                    value="{{ old('employer_record_reason_for_leaving.0') }}"
                                    class="input w-full" />
                            </div>

                            <!-- Question wrapper -->
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                <p class="text-sm font-medium">Were you
                                    subject to
                                    the
                                    DOT/FMCSA regulations while employed by this carrier?
                                </p>
                                <div class="flex items-center gap-6">
                                    <!-- YES -->
                                    <label class="label inline-flex items-center gap-2 font-normal">
                                        <input type="radio" name="employed_regulations[]" value="yes"
                                            {{ old('employed_regulations.0') == 'yes' ? 'checked' : '' }}
                                            class="input" />
                                        <span class="ml-2 text-sm">Yes</span>
                                    </label>
                                    <!-- NO -->
                                    <label class="label inline-flex items-center gap-2 font-normal">
                                        <input type="radio" name="employed_regulations[]" value="no"
                                            {{ old('employed_regulations.0', 'no') == 'no' ? 'checked' : '' }}
                                            class="input" />
                                        <span class="ml-2 text-sm">No</span>
                                    </label>
                                </div>
                            </div>

                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-2">
                                <p class="text-sm font-medium">Was your job
                                    designated as
                                    a
                                    safety sensitive function, in any DOT regulated mode, subject to the alcohol
                                    and
                                    controlled substances testing requirements required by 49 CFR Part 40?
                                </p>
                                <div class="flex items-center gap-6">
                                    <!-- YES -->
                                    <label class="label inline-flex items-center gap-2 font-normal">
                                        <input type="radio" name="safety_sensitive_function[]"
                                            value="yes"
                                            {{ old('safety_sensitive_function.0') == 'yes' ? 'checked' : '' }}
                                            class="input" />
                                        <span class="ml-2 text-sm">Yes</span>
                                    </label>
                                    <!-- NO -->
                                    <label class="label inline-flex items-center gap-2 font-normal">
                                        <input type="radio" name="safety_sensitive_function[]"
                                            value="no"
                                            {{ old('safety_sensitive_function.0', 'no') == 'no' ? 'checked' : '' }}
                                            class="input" />
                                        <span class="ml-2 text-sm">No</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        <!-- Additional employment records will be appended here by JavaScript -->
                    </div>

                    <div class="my-4 flex justify-between">
                        <div>
                            <button type="button" id="employment_record_add"
                                class="btn" data-variant="secondary">
                                Add Another Employment Record
                            </button>
                        </div>
                        <div>
                            <button type="button" id="employment_record_remove"
                                class="btn" data-variant="destructive">
                                Remove Last Employment Record
                            </button>
                        </div>
                    </div>
                </div>


            </div>

            <!-- Form Actions -->
            <div
                class="sticky bottom-0 z-30 -mx-4 mt-6 border-t px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-xl bg-background/95">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">Saves as draft and continues to
                        license documents.</p>
                    <div class="flex gap-3 justify-end">
                        <a href="{{ route('public.application.step1', ['slug' => $company->slug]) }}"
                            class="btn" data-variant="secondary">
                            Cancel
                        </a>
                        <button type="submit"
                            class="btn" data-variant="primary">
                            Continue
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
                        'residence-field mb-6 p-4 border rounded-lg';
                    newField.innerHTML = `
                <div class="pb-4">
                    <label for="residence_address_${residenceCount}"
                        class="label mb-2">
                        Address
                    </label>
                    <input type="text" id="residence_address_${residenceCount}" name="residence_address[]"
                        class="input w-full residence-address" />
                </div>

                <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    <div>
                        <label for="residence_city_${residenceCount}"
                            class="label mb-2">
                            City
                        </label>
                        <input type="text" id="residence_city_${residenceCount}" name="residence_city[]"
                            class="input w-full residence-city" />
                    </div>

                    <div>
                        <label for="residence_country_${residenceCount}"
                            class="label mb-2">
                            Country
                        </label>
                        <select id="residence_country_${residenceCount}" name="residence_country[]"
                            class="select w-full residence-country">
                            <option value="">Select Country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="residence_state_${residenceCount}"
                            class="label mb-2">
                            State
                        </label>
                        <select id="residence_state_${residenceCount}" name="residence_state[]"
                            class="select w-full residence-state">
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
                            class="label mb-2">
                            Postal Code
                        </label>
                        <input type="text" id="residence_postal_code_${residenceCount}" name="residence_postal_code[]"
                            class="input w-full residence-postal-code" />
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
                    newRow.className = 'border';
                    newRow.innerHTML = `
            <td class="border p-3 text-sm">
                <input type="date" name="accident_date[]"
                    class="input w-full" />
            </td>
            <td class="border p-3 text-sm">
                <input type="text" name="accident_location[]"
                    class="input w-full" />
            </td>
            <td class="border p-3 text-sm">
                <input type="text" name="number_of_injuries[]" placeholder="0"
                    class="input w-full" />
            </td>
            <td class="border p-3 text-sm">
                <input type="text" name="number_of_fatalities[]" placeholder="0"
                    class="input w-full" />
            </td>
            <td class="border p-3 text-sm">
                <select name="hazmat_spill[]"
                    class="select w-full">
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
                    newRow.className = 'border';
                    newRow.innerHTML = `
                <td class="border p-3 text-sm">
                    <input type="date" name="violation_date[]"
                        class="input w-full" />
                </td>
                <td class="border p-3 text-sm">
                    <input type="text" name="violation_location[]"
                        class="input w-full" />
                </td>
                <td class="border p-3 text-sm">
                    <input type="text" name="offense[]" placeholder="0"
                        class="input w-full" />
                </td>
                <td class="border p-3 text-sm">
                    <input type="text" name="vehicle_type[]" placeholder="0"
                        class="input w-full" />
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
                        'employment-record-field mb-8 p-6 border rounded-lg';
                    newField.innerHTML = `
            <h4 class="text-lg font-semibold mb-4">Employment Record ${employmentRecordCount + 1}</h4>
            
            <div class="pb-4">
                <label for="employer_name_${employmentRecordCount}"
                    class="label mb-2">
                    Employer Name
                </label>
                <input type="text" id="employer_name_${employmentRecordCount}" name="employer_name[]"
                    class="input w-full" />
            </div>

            <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-2">
                <div>
                    <label for="employer_record_address_${employmentRecordCount}"
                        class="label mb-2">
                        Address
                    </label>
                    <input type="text" id="employer_record_address_${employmentRecordCount}" name="employer_record_address[]"
                        class="input w-full" />
                </div>

                <div>
                    <label for="employer_record_city_${employmentRecordCount}"
                        class="label mb-2">
                        City
                    </label>
                    <input type="text" id="employer_record_city_${employmentRecordCount}" name="employer_record_city[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="employer_record_country_${employmentRecordCount}"
                        class="label mb-2">
                        Country
                    </label>
                    <select id="employer_record_country_${employmentRecordCount}" name="employer_record_country[]"
                        class="select w-full">
                        <option value="">Select Country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="employer_record_state_${employmentRecordCount}"
                        class="label mb-2">
                        State
                    </label>
                    <select id="employer_record_state_${employmentRecordCount}" name="employer_record_state[]"
                        class="select w-full">
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
                        class="label mb-2">
                        Postal Code
                    </label>
                    <input type="text" id="employer_record_postal_code_${employmentRecordCount}" name="employer_record_postal_code[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="employer_record_phone_${employmentRecordCount}"
                        class="label mb-2">
                        Phone
                    </label>
                    <input type="text" id="employer_record_phone_${employmentRecordCount}" name="employer_record_phone[]"
                        class="input w-full" />
                </div>

                <div>
                    <label for="employer_record_fax_${employmentRecordCount}"
                        class="label mb-2">
                        Fax
                    </label>
                    <input type="text" id="employer_record_fax_${employmentRecordCount}" name="employer_record_fax[]"
                        class="input w-full" />
                </div>

                <div>
                    <label for="employer_record_email_${employmentRecordCount}"
                        class="label mb-2">
                        Email
                    </label>
                    <input type="email" id="employer_record_email_${employmentRecordCount}" name="employer_record_email[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="grid mt-4 grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="employer_record_position_${employmentRecordCount}"
                        class="label mb-2">
                        Position Held
                    </label>
                    <input type="text" id="employer_record_position_${employmentRecordCount}" name="employer_record_position[]"
                        class="input w-full" />
                </div>

                <div>
                    <label for="employer_record_date_from_${employmentRecordCount}"
                        class="label mb-2">
                        Date From
                    </label>
                    <input type="date" id="employer_record_date_from_${employmentRecordCount}" name="employer_record_date_from[]"
                        class="input w-full" />
                </div>

                <div>
                    <label for="employer_record_date_to_${employmentRecordCount}"
                        class="label mb-2">
                        Date To
                    </label>
                    <input type="date" id="employer_record_date_to_${employmentRecordCount}" name="employer_record_date_to[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="my-4">
                <label for="employer_record_reason_for_leaving_${employmentRecordCount}"
                    class="label mb-2">
                    Reason for Leaving
                </label>
                <input type="text" id="employer_record_reason_for_leaving_${employmentRecordCount}" name="employer_record_reason_for_leaving[]"
                    class="input w-full" />
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-4">
                <p class="text-sm font-medium">Were you subject to the DOT/FMCSA regulations while employed by this carrier?</p>
                <div class="flex items-center gap-6">
                    <label class="label inline-flex items-center gap-2 font-normal">
                        <input type="radio" name="employed_regulations[]" value="yes"
                            class="input" />
                        <span class="ml-2 text-sm">Yes</span>
                    </label>
                    <label class="label inline-flex items-center gap-2 font-normal">
                        <input type="radio" name="employed_regulations[]" value="no" checked
                            class="input" />
                        <span class="ml-2 text-sm">No</span>
                    </label>
                </div>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-3 mt-4">
                <p class="text-sm font-medium">Was your job designated as a safety sensitive function?</p>
                <div class="flex items-center gap-6">
                    <label class="label inline-flex items-center gap-2 font-normal">
                        <input type="radio" name="safety_sensitive_function[]" value="yes"
                            class="input" />
                        <span class="ml-2 text-sm">Yes</span>
                    </label>
                    <label class="label inline-flex items-center gap-2 font-normal">
                        <input type="radio" name="safety_sensitive_function[]" value="no" checked
                            class="input" />
                        <span class="ml-2 text-sm">No</span>
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





        });
    </script>
    <script src="{{ asset('js/driver-form-validation.js') }}"></script>
@endpush
