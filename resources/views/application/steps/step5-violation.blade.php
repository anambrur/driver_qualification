@extends('layouts.application-form-layout')

@section('title', 'Forfeiture Statement | DOT Driver Qualification')

@section('content')
    <div class="min-h-screen bg-gray-50 flex flex-col items-center p-4 md:p-8">
        <div class="w-full max-w-4xl lg:max-w-5xl xl:max-w-6xl mx-auto">
            <!-- Header Section -->
            <div class="mb-8 md:mb-12 bg-blue-950 p-4 rounded-lg flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white mb-1">
                    {{ $company->company_name }}
                </h3>
                <p class="text-gray-200 text-sm">
                    © {{ now()->year }} {{ url('/') }}
                </p>
            </div>

            <!-- Main Card -->
            <div class="p-4 mx-auto max-w-7xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                        @if ($isEditMode)
                            Edit Violation Record
                        @else
                            Record of Violations
                        @endif
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        @if ($isEditMode)
                            Update driver's violation record (Step 5 of 10)
                        @else
                            Driver's violation record (Step 5 of 10)
                        @endif
                    </p>
                    <div class="mt-2 flex items-center text-sm text-gray-500 dark:text-gray-400">
                        <i class="fas fa-user mr-2"></i>
                        <span>Driver: {{ $driver->first_name }} {{ $driver->last_name }}</span>
                        @if ($driver->company)
                            <span class="mx-2">•</span>
                            <i class="fas fa-building mr-2"></i>
                            <span>Company: {{ $driver->company->company_name }}</span>
                        @endif
                        @if ($driver->licenses->isNotEmpty())
                            @foreach ($driver->licenses as $license)
                                <span class="mx-2">•</span>
                                <i class="fas fa-id-card mr-2"></i>
                                <span>License: {{ $license->license_number }} ({{ $license->state }})</span>
                            @endforeach
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
                    <div class="md:col-span-9">
                        @if ($errors->any())
                            <div
                                class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <h3 class="text-red-800 dark:text-red-200 font-medium">Please fix the following errors:
                                    </h3>
                                </div>
                                <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('public.application.store.step5', ['slug' => $company->slug]) }}" method="POST">
                            @csrf
                            <input type="hidden" name="driver_id" value="{{ $driver->id }}">
                            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

                            <!-- Moving Traffic Violations Section -->
                            <div
                                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90 mb-4">
                                                Traffic Violations Record (Past 12 Months)
                                            </h3>
                                            <!-- Certification Statement -->
                                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                                                <strong>I certify that the following is a true and complete list of traffic
                                                    violations
                                                    (other than parking violations) for which I have been convicted or
                                                    forfeited
                                                    bond or collateral
                                                    during the past 12 months.</strong>
                                            </p>
                                        </div>
                                        @if ($isEditMode)
                                            <span
                                                class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Edit Mode - Step 5 of 10
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Question wrapper -->
                                    <div class="flex items-center gap-6">
                                        <label class="inline-flex items-center cursor-pointer select-none">
                                            <input type="radio" name="violation" value="no"
                                                class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
                                                {{ $driverDocument && $driverDocument->violation_record_signature && (!$driver->violations || $driver->violations->isEmpty() || $driver->violations->first()->violation == 'no') ? 'checked' : '' }} />
                                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">I have
                                                had no
                                                violations</span>
                                        </label>
                                        <label class="inline-flex items-center cursor-pointer select-none">
                                            <input type="radio" name="violation" value="yes"
                                                class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
                                                {{ $driver->violations && $driver->violations->isNotEmpty() && $driver->violations->first()->violation == 'yes' ? 'checked' : '' }} />
                                            <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">I have
                                                had
                                                violations (list below)</span>
                                        </label>
                                    </div>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <div id="violation_fields">
                                        <table class="w-full">
                                            <thead>
                                                <tr class="border border-gray-200 dark:border-gray-700">
                                                    <th
                                                        class="border border-gray-200 p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                        Date MM/DD/YYYY
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
                                                @if ($driver->violations && $driver->violations->isNotEmpty() && $driver->violations->first()->violation == 'yes')
                                                    @foreach ($driver->violations as $index => $violation)
                                                        @if ($violation->violation == 'yes' && $violation->violation_date)
                                                            <tr class="border border-gray-200 dark:border-gray-700">
                                                                <td
                                                                    class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                                    <input type="date" name="violation_date[]"
                                                                        value="{{ old('violation_date.' . $index, $violation->violation_date) }}"
                                                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                                                </td>
                                                                <td
                                                                    class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                                    <input type="text" name="violation_location[]"
                                                                        value="{{ old('violation_location.' . $index, $violation->violation_location) }}"
                                                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                                                </td>
                                                                <td
                                                                    class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                                    <input type="text" name="offense[]"
                                                                        value="{{ old('offense.' . $index, $violation->offense) }}"
                                                                        placeholder="Enter offense"
                                                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                                                </td>
                                                                <td
                                                                    class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                                    <input type="text" name="vehicle_type[]"
                                                                        value="{{ old('vehicle_type.' . $index, $violation->vehicle_type) }}"
                                                                        placeholder="Enter vehicle type"
                                                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                                                </td>
                                                            </tr>
                                                        @endif
                                                    @endforeach
                                                @else
                                                    <!-- Initial row for new entries -->
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
                                                            <input type="text" name="offense[]"
                                                                value="{{ old('offense.0') }}" placeholder="Enter offense"
                                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                                        </td>
                                                        <td
                                                            class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                            <input type="text" name="vehicle_type[]"
                                                                value="{{ old('vehicle_type.0') }}"
                                                                placeholder="Enter vehicle type"
                                                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                                        </td>
                                                    </tr>
                                                @endif
                                                <!-- Additional rows will be appended here by JavaScript -->
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="my-4 flex justify-between">
                                        <div>
                                            <button type="button" id="violation_add"
                                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-gray-600 border border-transparent rounded-lg shadow-theme-xs hover:bg-gray-700 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                                <i class="fas fa-plus mr-2"></i>
                                                Add Another Violation
                                            </button>
                                        </div>
                                        <div>
                                            <button type="button" id="violation_remove"
                                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-red-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-red-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                                <i class="fas fa-minus mr-2"></i>
                                                Remove Last Violation
                                            </button>
                                        </div>
                                    </div>

                                    <!-- No Violations Certification -->
                                    <div
                                        class="mt-6 p-4 rounded-lg bg-gray-50 border border-gray-200 dark:bg-gray-900/20 dark:border-gray-800">
                                        <p class="text-sm text-gray-700 dark:text-gray-300">
                                            <strong>If no violations are listed above, I certify that I have not been
                                                convicted or
                                                forfeited bond or collateral on account of any violation required to be
                                                listed
                                                during the past 12 months.</strong>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Fair Credit Reporting Act Authorization -->
                            <div
                                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        Fair Credit Reporting Act Authorization
                                    </h3>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                                            Pursuant to the federal Fair Credit Reporting Act, I hereby authorize this
                                            company and
                                            its designated agents and representatives to conduct a comprehensive review of
                                            my
                                            background through any consumer report for employment. I understand that the
                                            scope of
                                            the consumer report/investigative consumer report may include, but is not
                                            limited to,
                                            the following areas:
                                        </p>
                                        <ul
                                            class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 ml-4">
                                            <li>verification of Social Security number</li>
                                            <li>current and previous residences</li>
                                            <li>employment history, including all personnel files</li>
                                            <li>education</li>
                                            <li>references</li>
                                            <li>credit history and reports</li>
                                            <li>criminal history, including records from any criminal justice agency in any
                                                or all
                                                federal, state or county jurisdictions</li>
                                            <li>birth records</li>
                                            <li>motor vehicle records, including traffic citations and registration</li>
                                            <li>any other public records</li>
                                        </ul>
                                    </div>

                                    <!-- Signature Section -->
                                    <div class="mt-6">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label for="applicant_signature"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                                                    Applicant Signature <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="applicant_signature" id="applicant_signature"
                                                    value="{{ old('applicant_signature', $driverDocument->violation_record_signature ?? '') }}"
                                                    placeholder="Type your full name as signature"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                    required>
                                            </div>
                                            <div>
                                                <label for="date_signed"
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                                                    Date Signed <span class="text-red-500">*</span>
                                                </label>
                                                <input type="date" name="date_signed" id="date_signed"
                                                    value="{{ old('date_signed', $driverDocument->violation_record_date_signed ?? date('Y-m-d')) }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                    required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Current Violation Information -->
                            @if ($driver->violations && $driver->violations->isNotEmpty())
                                <div
                                    class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                                    <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                            Current Violation Information
                                        </h3>
                                    </div>
                                    <div class="p-5 sm:p-6">
                                        @if ($driver->violations->first()->violation == 'no')
                                            <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                                                <div class="flex items-center">
                                                    <i
                                                        class="fas fa-check-circle text-green-500 dark:text-green-400 mr-3"></i>
                                                    <p class="text-green-800 dark:text-green-200 font-medium">
                                                        No violations recorded for this driver.
                                                    </p>
                                                </div>
                                                <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                                                    <p><strong>Signature:</strong>
                                                        {{ $driver->violations->first()->violation_record_signature ?? 'N/A' }}
                                                    </p>
                                                    <p><strong>Date Signed:</strong>
                                                        {{ $driver->violations->first()->violation_record_date_signed ? \Carbon\Carbon::parse($driver->violations->first()->violation_record_date_signed)->format('m/d/Y') : 'N/A' }}
                                                    </p>
                                                </div>
                                            </div>
                                        @else
                                            <div class="overflow-x-auto">
                                                <table class="w-full">
                                                    <thead>
                                                        <tr class="bg-gray-50 dark:bg-gray-800">
                                                            <th
                                                                class="p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                                Date</th>
                                                            <th
                                                                class="p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                                Location</th>
                                                            <th
                                                                class="p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                                Offense</th>
                                                            <th
                                                                class="p-3 text-left text-sm font-semibold text-gray-800 dark:text-white/90">
                                                                Vehicle Type</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach ($driver->violations as $violation)
                                                            @if ($violation->violation == 'yes' && $violation->violation_date)
                                                                <tr class="border-b border-gray-200 dark:border-gray-700">
                                                                    <td
                                                                        class="p-3 text-sm text-gray-800 dark:text-white/90">
                                                                        {{ $violation->violation_date ? \Carbon\Carbon::parse($violation->violation_date)->format('m/d/Y') : 'N/A' }}
                                                                    </td>
                                                                    <td
                                                                        class="p-3 text-sm text-gray-800 dark:text-white/90">
                                                                        {{ $violation->violation_location ?? 'N/A' }}
                                                                    </td>
                                                                    <td
                                                                        class="p-3 text-sm text-gray-800 dark:text-white/90">
                                                                        {{ $violation->offense ?? 'N/A' }}
                                                                    </td>
                                                                    <td
                                                                        class="p-3 text-sm text-gray-800 dark:text-white/90">
                                                                        {{ $violation->vehicle_type ?? 'N/A' }}
                                                                    </td>
                                                                </tr>
                                                            @endif
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="mt-4 bg-gray-50 dark:bg-gray-800/50 rounded-lg p-4">
                                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                                    <strong>Signature:</strong>
                                                    {{ $driver->violations->first()->violation_record_signature ?? 'N/A' }}
                                                </p>
                                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                                    <strong>Date Signed:</strong>
                                                    {{ $driver->violations->first()->violation_record_date_signed ? \Carbon\Carbon::parse($driver->violations->first()->violation_record_date_signed)->format('m/d/Y') : 'N/A' }}
                                                </p>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            @endif

                            <!-- Form Actions -->
                            <div
                                class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                                @if ($isEditMode)
                                    <a href="{{ route('admin.driver.edit', ['id' => $driver_id]) }}"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Driver Edit
                                    </a>
                                @else
                                    <a href="{{ route('public.application.step4', ['driver_id' => $driver->id, 'slug' => $company->slug]) }}"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Step 4
                                    </a>
                                @endif

                                <div class="flex gap-4">
                                    <button type="submit" name="action" value="save"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        <i class="fas fa-save mr-2"></i>
                                        {{ $isEditMode ? 'Update & Continue to Step 6' : 'Save & Continue to Step 6' }}
                                    </button>

                                    @if ($isEditMode)
                                        <a href="{{ route('admin.driver.alcohol.and.drug.test', ['driver_id' => $driver_id, 'edit' => '1']) }}"
                                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                            Skip to Next Step
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Progress Bar Sidebar -->
                    <div class="md:col-span-3">
                        @include('components.progress-bar', [
                            'currentStep' => $currentStep,
                            'totalSteps' => 10,
                            'isEditMode' => $isEditMode,
                            'driver_id' => $driver->id,
                        ])

                        <!-- Driver Info Card -->
                        <div
                            class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4">
                            <h3 class="font-medium text-gray-800 dark:text-white/90 mb-3">Driver Information</h3>
                            <div class="space-y-2">
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Name:</span>
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $driver->first_name }}
                                        {{ $driver->last_name }}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Email:</span>
                                    <span class="font-medium text-gray-800 dark:text-white/90">{{ $driver->email }}</span>
                                </div>
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Phone:</span>
                                    <span
                                        class="font-medium text-gray-800 dark:text-white/90">{{ $driver->main_phone }}</span>
                                </div>
                                @if ($driver->company)
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Company:</span>
                                        <span
                                            class="font-medium text-gray-800 dark:text-white/90">{{ $driver->company->company_name }}</span>
                                    </div>
                                @endif
                                <div class="flex items-center text-sm">
                                    <span class="w-24 text-gray-500 dark:text-gray-400">Status:</span>
                                    <span
                                        class="font-medium {{ $driver->status == 'active' ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                        {{ ucfirst($driver->status) }}
                                    </span>
                                </div>
                                @if ($driver->violations && $driver->violations->isNotEmpty())
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Violations:</span>
                                        <span
                                            class="font-medium {{ $driver->violations->first()->violation == 'no' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400' }}">
                                            {{ $driver->violations->first()->violation == 'no' ? 'None' : 'Has Violations' }}
                                        </span>
                                    </div>
                                @endif
                                @if ($driverDocument && $driverDocument->violation_record_signature)
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Last Signed:</span>
                                        <span class="font-medium text-gray-800 dark:text-white/90">
                                            {{ $driverDocument->violation_record_date_signed ? \Carbon\Carbon::parse($driverDocument->violation_record_date_signed)->format('m/d/Y') : 'N/A' }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set initial state based on violation selection
            const noViolationsRadio = document.querySelector('input[name="violation"][value="no"]');
            const hasViolationsRadio = document.querySelector('input[name="violation"][value="yes"]');
            const violationTable = document.getElementById('violation_fields');

            function toggleViolationTable() {
                if (noViolationsRadio && noViolationsRadio.checked) {
                    // If "no violations" is checked, hide the violation table
                    violationTable.style.display = 'none';
                } else if (hasViolationsRadio && hasViolationsRadio.checked) {
                    // If "has violations" is checked, show the violation table
                    violationTable.style.display = 'block';
                }
            }

            // Add event listeners to radio buttons
            if (noViolationsRadio) {
                noViolationsRadio.addEventListener('change', toggleViolationTable);
            }
            if (hasViolationsRadio) {
                hasViolationsRadio.addEventListener('change', toggleViolationTable);
            }

            // Set initial state
            toggleViolationTable();
        });

        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const signature = document.getElementById('applicant_signature');
                const dateSigned = document.getElementById('date_signed');
                const noViolationsRadio = document.querySelector('input[name="violation"][value="no"]');
                const hasViolationsRadio = document.querySelector('input[name="violation"][value="yes"]');

                // Validate radio selection
                if (!noViolationsRadio.checked && !hasViolationsRadio.checked) {
                    e.preventDefault();
                    showAppAlert('Please select whether you have had violations or not.');
                    return false;
                }

                // If "has violations" is selected, check if at least one date is filled
                if (hasViolationsRadio && hasViolationsRadio.checked) {
                    const violationDates = document.querySelectorAll('input[name="violation_date[]"]');
                    let hasValidViolation = false;

                    violationDates.forEach(input => {
                        if (input.value.trim() !== '') {
                            hasValidViolation = true;
                        }
                    });

                    if (!hasValidViolation) {
                        e.preventDefault();
                        showAppAlert(
                            'Please add at least one violation with a date, or select "I have had no violations".'
                            );
                        return false;
                    }
                }

                // Validate signature
                if (!signature.value.trim()) {
                    e.preventDefault();
                    showAppAlert('Please provide your signature.');
                    signature.focus();
                    return false;
                }

                // Validate date
                if (!dateSigned.value) {
                    e.preventDefault();
                    showAppAlert('Please select the date signed.');
                    dateSigned.focus();
                    return false;
                }
            });
        }

        // violation fields management
        let violationCount =
            {{ $driver->violations && $driver->violations->isNotEmpty() && $driver->violations->first()->violation == 'yes' ? $driver->violations->count() : 1 }};
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
                    <input type="text" name="offense[]" placeholder="Enter offense"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </td>
                <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                    <input type="text" name="vehicle_type[]" placeholder="Enter vehicle type"
                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                </td>
            `;
                violationTableBody.appendChild(newRow);
                violationCount++;

                // Show the table if it was hidden
                violationFields.style.display = 'block';
            });
        }

        if (violationRemoveBtn && violationTableBody) {
            violationRemoveBtn.addEventListener('click', function() {
                const rows = violationTableBody.querySelectorAll('tr');
                if (rows.length > 1) { // Keep at least one row
                    rows[rows.length - 1].remove();
                    violationCount--;

                    // If no rows left (shouldn't happen due to min 1), hide the table
                    if (violationCount <= 1) {
                        const remainingRows = violationTableBody.querySelectorAll('tr');
                        let hasData = false;
                        remainingRows.forEach(row => {
                            const inputs = row.querySelectorAll('input');
                            inputs.forEach(input => {
                                if (input.value.trim() !== '') {
                                    hasData = true;
                                }
                            });
                        });

                        if (!hasData) {
                            violationFields.style.display = 'none';
                            const noViolationsRadio = document.querySelector('input[name="violation"][value="no"]');
                            if (noViolationsRadio) {
                                noViolationsRadio.checked = true;
                            }
                        }
                    }
                }
            });
        }
    </script>
@endpush
