@extends('layouts.application-form-layout')

@section('title', 'Step 1: Basic Information')

@section('content')
    <x-application.step :company="$company" :driver="$driver" :step="1" title="Driver's Application"
        description="Your personal details, residences, license, experience and employment history.">
        <x-slot:aside>
            <div class="hidden rounded-xl border bg-card p-4 lg:block">
                <label for="driver-section-jump" class="label text-xs">Jump to section</label>
                <select id="driver-section-jump" class="select driver-section-jump mt-2 w-full">
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
            id="driverForm" enctype="multipart/form-data" class="grid grid-cols-1 gap-6">
            @csrf

            <div class="rounded-xl border bg-card p-4 lg:hidden">
                <label for="driver-section-jump-mobile" class="label text-xs">Jump to section</label>
                <select id="driver-section-jump-mobile" class="select driver-section-jump mt-2 w-full">
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

            {{-- section:applicant --}}
            <x-ui.card id="section-applicant" title="Applicant Information" class="scroll-mt-24">
                <div class="space-y-6">
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="First Name" for="first_name" name="first_name" required>
                            <x-ui.input id="first_name" name="first_name" required />
                        </x-ui.field>
                        <x-ui.field label="Middle Name" for="middle_name" name="middle_name">
                            <x-ui.input id="middle_name" name="middle_name" />
                        </x-ui.field>
                        <x-ui.field label="Last Name" for="last_name" name="last_name" required>
                            <x-ui.input id="last_name" name="last_name" required />
                        </x-ui.field>
                        <x-ui.field label="Suffix" for="suffix" name="suffix">
                            <x-ui.input id="suffix" name="suffix" />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <x-ui.field label="Business Name" for="business_name" name="business_name">
                            <x-ui.input id="business_name" name="business_name" />
                        </x-ui.field>
                        <x-ui.field label="Employer Identification Number" for="employer_identification_number" name="employer_identification_number">
                            <x-ui.input id="employer_identification_number" name="employer_identification_number" />
                        </x-ui.field>
                    </div>

                    <fieldset class="grid gap-2" @error('federal_tax_classification') data-invalid="true" @enderror>
                        <legend class="label mb-2">Federal Tax Classification</legend>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach (['individual_sole_proprietor' => 'Individual/Sole Proprietor', 'c_corporation' => 'C Corporation', 's_corporation' => 'S Corporation', 'llc' => 'LLC'] as $value => $label)
                                <label class="label gap-2 font-normal">
                                    <input type="radio" name="federal_tax_classification" value="{{ $value }}"
                                        {{ old('federal_tax_classification') == $value ? 'checked' : '' }} class="input" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                        @error('federal_tax_classification')
                            <p role="alert" class="text-sm text-destructive">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <x-ui.field label="Date of Birth" for="date_of_birth" name="date_of_birth" required>
                            <x-ui.input type="date" id="date_of_birth" name="date_of_birth" required />
                        </x-ui.field>
                        <x-ui.field label="Social Security Number" for="ssn" name="ssn" required>
                            <x-ui.input id="ssn" name="ssn" required placeholder="XXX-XX-XXXX" />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <x-ui.field label="Main Phone Number" for="main_phone" name="main_phone" required>
                            <x-ui.input type="tel" id="main_phone" name="main_phone" required />
                        </x-ui.field>
                        <x-ui.field label="Alt Phone Number" for="alt_phone" name="alt_phone">
                            <x-ui.input type="tel" id="alt_phone" name="alt_phone" />
                        </x-ui.field>
                    </div>

                    <x-ui.field label="Email Address" for="email" name="email" required>
                        <x-ui.input type="email" id="email" name="email" required />
                    </x-ui.field>

                    <x-ui.field label="Address" for="address" name="address" required>
                        <x-ui.input id="address" name="address" required />
                    </x-ui.field>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="City" for="city" name="city" required>
                            <x-ui.input id="city" name="city" required />
                        </x-ui.field>
                        <x-ui.field label="Country" for="country" name="country" required>
                            <x-ui.select id="country" name="country" required>
                                <option value="">Select Country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}"
                                        {{ old('country', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field label="State" for="state" name="state" required>
                            <x-ui.select id="state" name="state" required>
                                <option value="">Select State</option>
                                @if (isset($states) && $states->count())
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}" {{ old('state') == $state->id ? 'selected' : '' }}>
                                            {{ $state->name }}
                                        </option>
                                    @endforeach
                                @endif
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field label="Postal Code" for="postal_code" name="postal_code" required>
                            <x-ui.input id="postal_code" name="postal_code" required />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <label class="label gap-2 font-normal">
                            <input type="checkbox" name="twic_card" value="1" {{ old('twic_card') ? 'checked' : '' }} class="input" />
                            Do you have a TWIC Card?
                        </label>
                        <label class="label gap-2 font-normal">
                            <input type="checkbox" name="passport" value="1" {{ old('passport') ? 'checked' : '' }} class="input" />
                            Do you have a passport?
                        </label>
                    </div>

                    <div id="section-photo" class="scroll-mt-24 border-t pt-6">
                        <x-driver-photo-upload />
                    </div>
                </div>
            </x-ui.card>
            {{-- /section:applicant --}}

            {{-- section:residences --}}
            <x-ui.card id="section-residences" title="Residences Previous 3 Years" class="scroll-mt-24">
                <label class="label mb-3 gap-2 font-normal">
                    <input type="checkbox" name="same_address" value="1" id="same_address_checkbox"
                        {{ old('same_address') ? 'checked' : '' }} class="input" />
                    Same address as from Applicant Information.
                </label>

                <p class="mb-4 text-sm text-muted-foreground">List residence for previous 3 years if you lived at the above address less than 3 years.</p>

                <div id="residence_fields" class="space-y-4">
                    <div class="residence-field grid gap-4 rounded-lg border p-4">
                        <x-ui.field label="Address" for="residence_address_0" name="residence_address.0">
                            <input type="text" id="residence_address_0" name="residence_address[]"
                                value="{{ old('residence_address.0') }}" @error('residence_address.0') aria-invalid="true" @enderror
                                class="input w-full residence-address" />
                        </x-ui.field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <x-ui.field label="City" for="residence_city_0" name="residence_city.0">
                                <input type="text" id="residence_city_0" name="residence_city[]"
                                    value="{{ old('residence_city.0') }}" @error('residence_city.0') aria-invalid="true" @enderror
                                    class="input w-full residence-city" />
                            </x-ui.field>
                            <x-ui.field label="Country" for="residence_country_0" name="residence_country.0">
                                <select id="residence_country_0" name="residence_country[]" @error('residence_country.0') aria-invalid="true" @enderror
                                    class="select w-full residence-country">
                                    <option value="">Select Country</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}"
                                            {{ old('residence_country.0', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field label="State" for="residence_state_0" name="residence_state.0">
                                <select id="residence_state_0" name="residence_state[]" @error('residence_state.0') aria-invalid="true" @enderror
                                    class="select w-full residence-state">
                                    <option value="">Select State</option>
                                    @if (isset($states) && $states->count())
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}" {{ old('residence_state.0') == $state->id ? 'selected' : '' }}>
                                                {{ $state->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </x-ui.field>
                            <x-ui.field label="Postal Code" for="residence_postal_code_0" name="residence_postal_code.0">
                                <input type="text" id="residence_postal_code_0" name="residence_postal_code[]"
                                    value="{{ old('residence_postal_code.0') }}" @error('residence_postal_code.0') aria-invalid="true" @enderror
                                    class="input w-full residence-postal-code" />
                            </x-ui.field>
                        </div>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap justify-between gap-2">
                    <x-ui.button id="residence_add" variant="secondary" icon="plus">Add Another Residence</x-ui.button>
                    <x-ui.button id="residence_remove" variant="destructive" icon="trash-2">Remove Last Residence</x-ui.button>
                </div>
            </x-ui.card>
            {{-- /section:residences --}}

            {{-- section:license --}}
            <x-ui.card id="section-license" title="Drivers License Information" class="scroll-mt-24">
                <div class="space-y-6">
                    <p class="text-sm text-muted-foreground">List all driver licenses held within the last 3 years. Enter your first and last name exactly as it appears on your license.</p>

                    <x-ui.alert tone="warning" icon="triangle-alert">
                        Warning! Triple check the license information for accuracy. If you enter the wrong information, all documents relating to your license will have to be discarded and completed again. Failure to enter accurate license information may result in non-consideration and a rejected application!
                    </x-ui.alert>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                        <x-ui.field label="First Name" for="license_first_name" name="license_first_name" required>
                            <x-ui.input id="license_first_name" name="license_first_name" required />
                        </x-ui.field>
                        <x-ui.field label="Last Name" for="license_last_name" name="license_last_name" required>
                            <x-ui.input id="license_last_name" name="license_last_name" required />
                        </x-ui.field>
                        <x-ui.field label="Issued" for="license_issued" name="license_issued" required>
                            <x-ui.input type="date" id="license_issued" name="license_issued" required />
                        </x-ui.field>
                        <x-ui.field label="Expires" for="license_expires" name="license_expires" required>
                            <x-ui.input type="date" id="license_expires" name="license_expires" required />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <x-ui.field label="Country" for="license_country" name="license_country" required>
                            <x-ui.select id="license_country" name="license_country" required>
                                <option value="">Select Country</option>
                                @foreach ($countries as $country)
                                    <option value="{{ $country->id }}"
                                        {{ old('license_country', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                        {{ $country->name }}
                                    </option>
                                @endforeach
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field label="State" for="license_state" name="license_state" required>
                            <x-ui.select id="license_state" name="license_state" required>
                                <option value="">Select State</option>
                                @if (isset($states) && $states->count())
                                    @foreach ($states as $state)
                                        <option value="{{ $state->id }}" {{ old('license_state') == $state->id ? 'selected' : '' }}>
                                            {{ $state->name }}
                                        </option>
                                    @endforeach
                                @endif
                            </x-ui.select>
                        </x-ui.field>
                        <x-ui.field label="Class" for="license_class" name="license_class" required>
                            <x-ui.select id="license_class" name="license_class" required placeholder="Select license class"
                                :options="collect(['Class A (CDL-A)', 'Class B (CDL-B)', 'Class C (CDL-C)', 'Class D', 'Class C (Non-Commercial)', 'Class M', 'Class E', 'Class F', 'Class DJ/MJ'])->mapWithKeys(fn ($class) => [$class => $class])" />
                        </x-ui.field>
                    </div>

                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2">
                        <x-ui.field label="License Number" for="license_number" name="license_number" required>
                            <x-ui.input id="license_number" name="license_number" required />
                        </x-ui.field>
                        <x-ui.field label="Repeat License Number:" for="repeat_license_number" name="repeat_license_number" required>
                            <x-ui.input id="repeat_license_number" name="repeat_license_number" required />
                        </x-ui.field>
                    </div>

                    <fieldset>
                        <legend class="label mb-2">Endorsements:</legend>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach (['is_h_placarded_hazmat' => 'H - Placarded Hazmat.', 'is_n_tank_vehicle' => 'N - Tank Vehicles.', 'is_p_passengers' => 'P - Passengers.', 'is_t_double_trailer' => 'T - Double/Triple Trailers.', 'is_s_school_bus' => 'S - School Bus.', 'is_x_placarded_hazmat' => 'X - Placarded Hazmat & Tank Vehicles.'] as $endorsement => $label)
                                <label class="label gap-2 font-normal">
                                    <input type="checkbox" name="{{ $endorsement }}" value="1" {{ old($endorsement) ? 'checked' : '' }} class="input" />
                                    {{ $label }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                </div>
            </x-ui.card>
            {{-- /section:license --}}

            {{-- section:experience --}}
            <x-ui.card id="section-experience" title="Experience" class="scroll-mt-24">
                <x-ui.table>
                    <thead>
                        <tr>
                            <th>Equipment Class</th>
                            <th>Experience</th>
                            <th>Date From MM/DO/YYYY</th>
                            <th>Date To MM/DO/YYYY</th>
                            <th>Approx Miles</th>
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
                            <tr>
                                <td data-label="Equipment Class" class="font-medium">
                                    {{ $equipmentClass }}
                                    <input type="hidden" name="equipment_class[]" value="{{ $equipmentClass }}">
                                </td>
                                <td data-label="Experience">
                                    <select name="experience[]" aria-label="{{ $equipmentClass }} experience" class="select w-full">
                                        <option value="no" {{ old('experience.' . $index) == 'no' ? 'selected' : '' }}>No</option>
                                        <option value="yes" {{ old('experience.' . $index) == 'yes' ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </td>
                                <td data-label="Date From">
                                    <input type="date" name="experience_from_date[]" value="{{ old('experience_from_date.' . $index) }}"
                                        aria-label="{{ $equipmentClass }} date from" @error('experience_from_date.' . $index) aria-invalid="true" @enderror class="input w-full" />
                                    @error('experience_from_date.' . $index)
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Date To">
                                    <input type="date" name="experience_to_date[]" value="{{ old('experience_to_date.' . $index) }}"
                                        aria-label="{{ $equipmentClass }} date to" @error('experience_to_date.' . $index) aria-invalid="true" @enderror class="input w-full" />
                                    @error('experience_to_date.' . $index)
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Approx Miles">
                                    <input type="text" name="approx_miles[]" value="{{ old('approx_miles.' . $index) }}" placeholder="0"
                                        aria-label="{{ $equipmentClass }} approx miles" @error('approx_miles.' . $index) aria-invalid="true" @enderror class="input w-full" />
                                    @error('approx_miles.' . $index)
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
            </x-ui.card>
            {{-- /section:experience --}}

            {{-- section:accidents --}}
            <x-ui.card id="section-accidents" title="Accidents/Crashes Previous 3 Years" class="scroll-mt-24">
                <fieldset class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" @error('accident') data-invalid="true" @enderror>
                    <legend class="contents text-sm font-medium">Have you had any accidents/crashes in the last 3 years? <span class="text-destructive">*</span></legend>
                    <div class="flex items-center gap-6">
                        <label class="label gap-2 font-normal">
                            <input type="radio" name="accident" value="yes" {{ old('accident') == 'yes' ? 'checked' : '' }} class="input" />
                            Yes
                        </label>
                        <label class="label gap-2 font-normal">
                            <input type="radio" name="accident" value="no" {{ old('accident', 'no') == 'no' ? 'checked' : '' }} class="input" />
                            No
                        </label>
                    </div>
                    @error('accident')
                        <p role="alert" class="text-sm text-destructive">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div id="accident_fields">
                    <x-ui.table>
                        <thead>
                            <tr>
                                <th>Date MM/DO/YYYY</th>
                                <th>Location City/State</th>
                                <th>Number of Injuries</th>
                                <th>Number of Fatalities</th>
                                <th>Hazmat Spill?</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-label="Date">
                                    <input type="date" name="accident_date[]" value="{{ old('accident_date.0') }}" aria-label="Accident date"
                                        @error('accident_date.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('accident_date.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Location">
                                    <input type="text" name="accident_location[]" value="{{ old('accident_location.0') }}" aria-label="Accident location"
                                        @error('accident_location.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('accident_location.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Injuries">
                                    <input type="text" name="number_of_injuries[]" value="{{ old('number_of_injuries.0') }}" placeholder="0" aria-label="Number of injuries"
                                        @error('number_of_injuries.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('number_of_injuries.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Fatalities">
                                    <input type="text" name="number_of_fatalities[]" value="{{ old('number_of_fatalities.0') }}" placeholder="0" aria-label="Number of fatalities"
                                        @error('number_of_fatalities.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('number_of_fatalities.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Hazmat Spill?">
                                    <select name="hazmat_spill[]" aria-label="Hazmat spill" class="select w-full">
                                        <option value="no" {{ old('hazmat_spill.0') == 'no' ? 'selected' : '' }}>No</option>
                                        <option value="yes" {{ old('hazmat_spill.0') == 'yes' ? 'selected' : '' }}>Yes</option>
                                    </select>
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.table>
                </div>

                <div class="mt-4 flex flex-wrap justify-between gap-2">
                    <x-ui.button id="accident_add" variant="secondary" icon="plus">Add Another Accident/Crash</x-ui.button>
                    <x-ui.button id="accident_remove" variant="destructive" icon="trash-2">Remove Last Accident</x-ui.button>
                </div>
            </x-ui.card>
            {{-- /section:accidents --}}

            {{-- section:violations --}}
            <x-ui.card id="section-violations" title="Moving Traffic Violations Previous 3 Years" class="scroll-mt-24">
                <fieldset class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center" @error('violation') data-invalid="true" @enderror>
                    <legend class="contents text-sm font-medium">Have you had any traffic violations in the last 3 years? <span class="text-destructive">*</span></legend>
                    <div class="flex items-center gap-6">
                        <label class="label gap-2 font-normal">
                            <input type="radio" name="violation" value="yes" {{ old('violation') == 'yes' ? 'checked' : '' }} class="input" />
                            Yes
                        </label>
                        <label class="label gap-2 font-normal">
                            <input type="radio" name="violation" value="no" {{ old('violation', 'no') == 'no' ? 'checked' : '' }} class="input" />
                            No
                        </label>
                    </div>
                    @error('violation')
                        <p role="alert" class="text-sm text-destructive">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div id="violation_fields">
                    <x-ui.table>
                        <thead>
                            <tr>
                                <th>Date MM/DO/YYYY</th>
                                <th>Location City/State</th>
                                <th>Offense</th>
                                <th>Vehicle Type</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td data-label="Date">
                                    <input type="date" name="violation_date[]" value="{{ old('violation_date.0') }}" aria-label="Violation date"
                                        @error('violation_date.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('violation_date.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Location">
                                    <input type="text" name="violation_location[]" value="{{ old('violation_location.0') }}" aria-label="Violation location"
                                        @error('violation_location.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('violation_location.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Offense">
                                    <input type="text" name="offense[]" value="{{ old('offense.0') }}" placeholder="0" aria-label="Offense"
                                        @error('offense.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('offense.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                                <td data-label="Vehicle Type">
                                    <input type="text" name="vehicle_type[]" value="{{ old('vehicle_type.0') }}" aria-label="Vehicle type"
                                        @error('vehicle_type.0') aria-invalid="true" @enderror class="input w-full" />
                                    @error('vehicle_type.0')
                                        <p role="alert" class="mt-1 text-xs text-destructive">{{ $message }}</p>
                                    @enderror
                                </td>
                            </tr>
                        </tbody>
                    </x-ui.table>
                </div>

                <div class="mt-4 flex flex-wrap justify-between gap-2">
                    <x-ui.button id="violation_add" variant="secondary" icon="plus">Add Another Violation</x-ui.button>
                    <x-ui.button id="violation_remove" variant="destructive" icon="trash-2">Remove Last Violation</x-ui.button>
                </div>
            </x-ui.card>
            {{-- /section:violations --}}

            {{-- section:forfeitures --}}
            <x-ui.card title="Forfeitures Previous 3 Years">
                <div class="space-y-4">
                    @foreach (['denied_license' => 'A. Have you ever been denied a license, permit or privilege to operate a motor vehicle?', 'license_revoked' => 'B. Has any license, permit or privilege ever been revoked?'] as $question => $legend)
                        <fieldset class="flex flex-col gap-3 sm:flex-row sm:items-center" @error($question) data-invalid="true" @enderror>
                            <legend class="contents text-sm font-medium">{{ $legend }} <span class="text-destructive">*</span></legend>
                            <div class="flex items-center gap-6">
                                <label class="label gap-2 font-normal">
                                    <input type="radio" name="{{ $question }}" value="yes" {{ old($question) == 'yes' ? 'checked' : '' }} class="input" />
                                    Yes
                                </label>
                                <label class="label gap-2 font-normal">
                                    <input type="radio" name="{{ $question }}" value="no" {{ old($question, 'no') == 'no' ? 'checked' : '' }} class="input" />
                                    No
                                </label>
                            </div>
                            @error($question)
                                <p role="alert" class="text-sm text-destructive">{{ $message }}</p>
                            @enderror
                        </fieldset>
                    @endforeach

                    <x-ui.field label="C. If yes to either question above, briefly describe the circumstances." for="forfeitures" name="forfeitures">
                        <textarea id="forfeitures" name="forfeitures" rows="3" @error('forfeitures') aria-invalid="true" @enderror class="textarea w-full">{{ old('forfeitures') }}</textarea>
                    </x-ui.field>
                </div>
            </x-ui.card>
            {{-- /section:forfeitures --}}

            {{-- section:employment --}}
            <x-ui.card id="section-employment" title="Employment Record Previous 3 Years" class="scroll-mt-24">
                <p class="mb-4 text-sm text-destructive">List all employers for the previous 3 years and an additional 7 years if you were employed as a DRIVER.</p>

                <div id="employment_record_fields" class="space-y-6">
                    <div class="employment-record-field grid gap-4 rounded-lg border bg-muted/40 p-4 sm:p-6">
                        <h3 class="text-base font-semibold">Employment Record 1</h3>

                        <x-ui.field label="Employer Name" for="employer_name_0" name="employer_name.0">
                            <input type="text" id="employer_name_0" name="employer_name[]"
                                value="{{ old('employer_name.0') }}" @error('employer_name.0') aria-invalid="true" @enderror class="input w-full" />
                        </x-ui.field>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <x-ui.field label="Address" for="employer_record_address_0" name="employer_record_address.0">
                                <input type="text" id="employer_record_address_0" name="employer_record_address[]"
                                    value="{{ old('employer_record_address.0') }}" @error('employer_record_address.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                            <x-ui.field label="City" for="employer_record_city_0" name="employer_record_city.0">
                                <input type="text" id="employer_record_city_0" name="employer_record_city[]"
                                    value="{{ old('employer_record_city.0') }}" @error('employer_record_city.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <x-ui.field label="Country" for="employer_record_country_0" name="employer_record_country.0">
                                <select id="employer_record_country_0" name="employer_record_country[]" @error('employer_record_country.0') aria-invalid="true" @enderror class="select w-full">
                                    <option value="">Select Country</option>
                                    @foreach ($countries as $country)
                                        <option value="{{ $country->id }}"
                                            {{ old('employer_record_country.0', $defaultCountry) == $country->id ? 'selected' : '' }}>
                                            {{ $country->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </x-ui.field>
                            <x-ui.field label="State" for="employer_record_state_0" name="employer_record_state.0">
                                <select id="employer_record_state_0" name="employer_record_state[]" @error('employer_record_state.0') aria-invalid="true" @enderror class="select w-full">
                                    <option value="">Select State</option>
                                    @if (isset($states) && $states->count())
                                        @foreach ($states as $state)
                                            <option value="{{ $state->id }}" {{ old('employer_record_state.0') == $state->id ? 'selected' : '' }}>
                                                {{ $state->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </x-ui.field>
                            <x-ui.field label="Postal Code" for="employer_record_postal_code_0" name="employer_record_postal_code.0">
                                <input type="text" id="employer_record_postal_code_0" name="employer_record_postal_code[]"
                                    value="{{ old('employer_record_postal_code.0') }}" @error('employer_record_postal_code.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <x-ui.field label="Phone" for="employer_record_phone_0" name="employer_record_phone.0">
                                <input type="text" id="employer_record_phone_0" name="employer_record_phone[]"
                                    value="{{ old('employer_record_phone.0') }}" @error('employer_record_phone.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                            <x-ui.field label="Fax" for="employer_record_fax_0" name="employer_record_fax.0">
                                <input type="text" id="employer_record_fax_0" name="employer_record_fax[]"
                                    value="{{ old('employer_record_fax.0') }}" @error('employer_record_fax.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                            <x-ui.field label="Email" for="employer_record_email_0" name="employer_record_email.0">
                                <input type="email" id="employer_record_email_0" name="employer_record_email[]"
                                    value="{{ old('employer_record_email.0') }}" @error('employer_record_email.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                        </div>

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                            <x-ui.field label="Position Held" for="employer_record_position_0" name="employer_record_position.0">
                                <input type="text" id="employer_record_position_0" name="employer_record_position[]"
                                    value="{{ old('employer_record_position.0') }}" @error('employer_record_position.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                            <x-ui.field label="Date From" for="employer_record_date_from_0" name="employer_record_date_from.0">
                                <input type="date" id="employer_record_date_from_0" name="employer_record_date_from[]"
                                    value="{{ old('employer_record_date_from.0') }}" @error('employer_record_date_from.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                            <x-ui.field label="Date To" for="employer_record_date_to_0" name="employer_record_date_to.0">
                                <input type="date" id="employer_record_date_to_0" name="employer_record_date_to[]"
                                    value="{{ old('employer_record_date_to.0') }}" @error('employer_record_date_to.0') aria-invalid="true" @enderror class="input w-full" />
                            </x-ui.field>
                        </div>

                        <x-ui.field label="Reason for Leaving" for="employer_record_reason_for_leaving_0" name="employer_record_reason_for_leaving.0">
                            <input type="text" id="employer_record_reason_for_leaving_0" name="employer_record_reason_for_leaving[]"
                                value="{{ old('employer_record_reason_for_leaving.0') }}" @error('employer_record_reason_for_leaving.0') aria-invalid="true" @enderror class="input w-full" />
                        </x-ui.field>

                        <fieldset class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <legend class="contents text-sm font-medium">Were you subject to the DOT/FMCSA regulations while employed by this carrier?</legend>
                            <div class="flex items-center gap-6">
                                <label class="label gap-2 font-normal">
                                    <input type="radio" name="employed_regulations[]" value="yes" {{ old('employed_regulations.0') == 'yes' ? 'checked' : '' }} class="input" />
                                    Yes
                                </label>
                                <label class="label gap-2 font-normal">
                                    <input type="radio" name="employed_regulations[]" value="no" {{ old('employed_regulations.0', 'no') == 'no' ? 'checked' : '' }} class="input" />
                                    No
                                </label>
                            </div>
                        </fieldset>

                        <fieldset class="flex flex-col gap-3 sm:flex-row sm:items-center">
                            <legend class="contents text-sm font-medium">Was your job designated as a safety sensitive function, in any DOT regulated mode, subject to the alcohol and controlled substances testing requirements required by 49 CFR Part 40?</legend>
                            <div class="flex items-center gap-6">
                                <label class="label gap-2 font-normal">
                                    <input type="radio" name="safety_sensitive_function[]" value="yes" {{ old('safety_sensitive_function.0') == 'yes' ? 'checked' : '' }} class="input" />
                                    Yes
                                </label>
                                <label class="label gap-2 font-normal">
                                    <input type="radio" name="safety_sensitive_function[]" value="no" {{ old('safety_sensitive_function.0', 'no') == 'no' ? 'checked' : '' }} class="input" />
                                    No
                                </label>
                            </div>
                        </fieldset>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap justify-between gap-2">
                    <x-ui.button id="employment_record_add" variant="secondary" icon="plus">Add Another Employment Record</x-ui.button>
                    <x-ui.button id="employment_record_remove" variant="destructive" icon="trash-2">Remove Last Employment Record</x-ui.button>
                </div>
            </x-ui.card>
            {{-- /section:employment --}}

            {{-- section:actions --}}
            <div class="sticky bottom-0 z-30 -mx-4 border-t bg-background/95 px-4 py-4 backdrop-blur sm:mx-0 sm:rounded-xl">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p class="text-xs text-muted-foreground">Saves as draft and continues to license documents.</p>
                    <div class="flex justify-end gap-3">
                        <x-ui.button :href="route('public.application.step1', ['slug' => $company->slug])" variant="secondary">Cancel</x-ui.button>
                        <x-ui.button type="submit" variant="primary" icon-end="arrow-right">Continue</x-ui.button>
                    </div>
                </div>
            </div>
            {{-- /section:actions --}}
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

            // script:residences
            // Residence fields management
            let residenceCount = 1;
            const residenceFields = document.getElementById('residence_fields');
            const residenceAddBtn = document.getElementById('residence_add');
            const residenceRemoveBtn = document.getElementById('residence_remove');

            if (residenceAddBtn) {
                residenceAddBtn.addEventListener('click', function() {
                    const newField = document.createElement('div');
                    newField.className = 'residence-field grid gap-4 rounded-lg border p-4';
                    newField.innerHTML = `
                <div class="field gap-2">
                    <label for="residence_address_${residenceCount}" class="label">Address</label>
                    <input type="text" id="residence_address_${residenceCount}" name="residence_address[]"
                        class="input w-full residence-address" />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="field gap-2">
                        <label for="residence_city_${residenceCount}" class="label">City</label>
                        <input type="text" id="residence_city_${residenceCount}" name="residence_city[]"
                            class="input w-full residence-city" />
                    </div>

                    <div class="field gap-2">
                        <label for="residence_country_${residenceCount}" class="label">Country</label>
                        <select id="residence_country_${residenceCount}" name="residence_country[]"
                            class="select w-full residence-country">
                            <option value="">Select Country</option>
                            @foreach ($countries as $country)
                                <option value="{{ $country->id }}">{{ $country->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field gap-2">
                        <label for="residence_state_${residenceCount}" class="label">State</label>
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

                    <div class="field gap-2">
                        <label for="residence_postal_code_${residenceCount}" class="label">Postal Code</label>
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
            // /script:residences

            // script:accidents
            // Accident fields management
            let accidentCount = 1;
            const accidentAddBtn = document.getElementById('accident_add');
            const accidentRemoveBtn = document.getElementById('accident_remove');
            const accidentTableBody = document.querySelector('#accident_fields tbody');

            if (accidentAddBtn && accidentTableBody) {
                accidentAddBtn.addEventListener('click', function() {
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
            <td data-label="Date">
                <input type="date" name="accident_date[]" aria-label="Accident date"
                    class="input w-full" />
            </td>
            <td data-label="Location">
                <input type="text" name="accident_location[]" aria-label="Accident location"
                    class="input w-full" />
            </td>
            <td data-label="Injuries">
                <input type="text" name="number_of_injuries[]" placeholder="0" aria-label="Number of injuries"
                    class="input w-full" />
            </td>
            <td data-label="Fatalities">
                <input type="text" name="number_of_fatalities[]" placeholder="0" aria-label="Number of fatalities"
                    class="input w-full" />
            </td>
            <td data-label="Hazmat Spill?">
                <select name="hazmat_spill[]" aria-label="Hazmat spill"
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
            // /script:accidents

            // script:violations
            // violation fields management
            let violationCount = 1;
            const violationFields = document.getElementById('violation_fields');
            const violationAddBtn = document.getElementById('violation_add');
            const violationRemoveBtn = document.getElementById('violation_remove');
            const violationTableBody = document.querySelector('#violation_fields tbody');

            if (violationAddBtn && violationTableBody) {
                violationAddBtn.addEventListener('click', function() {
                    const newRow = document.createElement('tr');
                    newRow.innerHTML = `
                <td data-label="Date">
                    <input type="date" name="violation_date[]" aria-label="Violation date"
                        class="input w-full" />
                </td>
                <td data-label="Location">
                    <input type="text" name="violation_location[]" aria-label="Violation location"
                        class="input w-full" />
                </td>
                <td data-label="Offense">
                    <input type="text" name="offense[]" placeholder="0" aria-label="Offense"
                        class="input w-full" />
                </td>
                <td data-label="Vehicle Type">
                    <input type="text" name="vehicle_type[]" placeholder="0" aria-label="Vehicle type"
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
            // /script:violations

            // script:employment
            // Employment Record fields management
            let employmentRecordCount = 1;
            const employmentRecordFields = document.getElementById('employment_record_fields');
            const employmentRecordAddBtn = document.getElementById('employment_record_add');
            const employmentRecordRemoveBtn = document.getElementById('employment_record_remove');

            if (employmentRecordAddBtn) {
                employmentRecordAddBtn.addEventListener('click', function() {
                    const newField = document.createElement('div');
                    newField.className = 'employment-record-field grid gap-4 rounded-lg border bg-muted/40 p-4 sm:p-6';
                    newField.innerHTML = `
            <h3 class="text-base font-semibold">Employment Record ${employmentRecordCount + 1}</h3>

            <div class="field gap-2">
                <label for="employer_name_${employmentRecordCount}" class="label">Employer Name</label>
                <input type="text" id="employer_name_${employmentRecordCount}" name="employer_name[]"
                    class="input w-full" />
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="field gap-2">
                    <label for="employer_record_address_${employmentRecordCount}" class="label">Address</label>
                    <input type="text" id="employer_record_address_${employmentRecordCount}" name="employer_record_address[]"
                        class="input w-full" />
                </div>

                <div class="field gap-2">
                    <label for="employer_record_city_${employmentRecordCount}" class="label">City</label>
                    <input type="text" id="employer_record_city_${employmentRecordCount}" name="employer_record_city[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="field gap-2">
                    <label for="employer_record_country_${employmentRecordCount}" class="label">Country</label>
                    <select id="employer_record_country_${employmentRecordCount}" name="employer_record_country[]"
                        class="select w-full">
                        <option value="">Select Country</option>
                        @foreach ($countries as $country)
                            <option value="{{ $country->id }}">{{ $country->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field gap-2">
                    <label for="employer_record_state_${employmentRecordCount}" class="label">State</label>
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

                <div class="field gap-2">
                    <label for="employer_record_postal_code_${employmentRecordCount}" class="label">Postal Code</label>
                    <input type="text" id="employer_record_postal_code_${employmentRecordCount}" name="employer_record_postal_code[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="field gap-2">
                    <label for="employer_record_phone_${employmentRecordCount}" class="label">Phone</label>
                    <input type="text" id="employer_record_phone_${employmentRecordCount}" name="employer_record_phone[]"
                        class="input w-full" />
                </div>

                <div class="field gap-2">
                    <label for="employer_record_fax_${employmentRecordCount}" class="label">Fax</label>
                    <input type="text" id="employer_record_fax_${employmentRecordCount}" name="employer_record_fax[]"
                        class="input w-full" />
                </div>

                <div class="field gap-2">
                    <label for="employer_record_email_${employmentRecordCount}" class="label">Email</label>
                    <input type="email" id="employer_record_email_${employmentRecordCount}" name="employer_record_email[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div class="field gap-2">
                    <label for="employer_record_position_${employmentRecordCount}" class="label">Position Held</label>
                    <input type="text" id="employer_record_position_${employmentRecordCount}" name="employer_record_position[]"
                        class="input w-full" />
                </div>

                <div class="field gap-2">
                    <label for="employer_record_date_from_${employmentRecordCount}" class="label">Date From</label>
                    <input type="date" id="employer_record_date_from_${employmentRecordCount}" name="employer_record_date_from[]"
                        class="input w-full" />
                </div>

                <div class="field gap-2">
                    <label for="employer_record_date_to_${employmentRecordCount}" class="label">Date To</label>
                    <input type="date" id="employer_record_date_to_${employmentRecordCount}" name="employer_record_date_to[]"
                        class="input w-full" />
                </div>
            </div>

            <div class="field gap-2">
                <label for="employer_record_reason_for_leaving_${employmentRecordCount}" class="label">Reason for Leaving</label>
                <input type="text" id="employer_record_reason_for_leaving_${employmentRecordCount}" name="employer_record_reason_for_leaving[]"
                    class="input w-full" />
            </div>

            <fieldset class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <legend class="contents text-sm font-medium">Were you subject to the DOT/FMCSA regulations while employed by this carrier?</legend>
                <div class="flex items-center gap-6">
                    <label class="label gap-2 font-normal">
                        <input type="radio" name="employed_regulations[]" value="yes"
                            class="input" />
                        Yes
                    </label>
                    <label class="label gap-2 font-normal">
                        <input type="radio" name="employed_regulations[]" value="no" checked
                            class="input" />
                        No
                    </label>
                </div>
            </fieldset>

            <fieldset class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <legend class="contents text-sm font-medium">Was your job designated as a safety sensitive function?</legend>
                <div class="flex items-center gap-6">
                    <label class="label gap-2 font-normal">
                        <input type="radio" name="safety_sensitive_function[]" value="yes"
                            class="input" />
                        Yes
                    </label>
                    <label class="label gap-2 font-normal">
                        <input type="radio" name="safety_sensitive_function[]" value="no" checked
                            class="input" />
                        No
                    </label>
                </div>
            </fieldset>
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
            // /script:employment
        });
    </script>
    <script src="{{ asset('js/driver-form-validation.js') }}"></script>
@endpush
