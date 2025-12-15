@extends('layouts.main-layout')

@section('title', 'Violation Record')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Record of Violations Regarding Arizona License
                #{{ $license_number ?? 'N/A' }}</h1>
            <p class="text-gray-600 dark:text-gray-400">Driver's violation record</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
                <form action="{{ route('admin.driver.violation.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">

                    <!-- Moving Traffic Violations Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <!-- Certification Statement -->
                            <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                                <strong>I certify that the following is a true and complete list of traffic violations
                                    (other than
                                    parking violations) for which I have been convicted or forfeited bond or collateral
                                    during the
                                    past 12 months.</strong>
                            </p>
                            <!-- Question wrapper -->
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center cursor-pointer select-none">
                                    <input type="radio" name="violation" value="no"
                                        class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">I have had no
                                        violations</span>
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
                                        <!-- Initial row -->
                                        <tr class="border border-gray-200 dark:border-gray-700">
                                            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="date" name="violation_date[]"
                                                    value="{{ old('violation_date.0') }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="violation_location[]"
                                                    value="{{ old('violation_location.0') }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
                                                <input type="text" name="offense[]" value="{{ old('offense.0') }}"
                                                    placeholder="0"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30" />
                                            </td>
                                            <td class="border border-gray-200 p-3 text-sm text-gray-800 dark:text-white/90">
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

                            <!-- No Violations Certification -->
                            <div
                                class="mt-6 p-4 rounded-lg bg-gray-50 border border-gray-200 dark:bg-gray-900/20 dark:border-gray-800">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <strong>If no violations are listed above, I certify that I have not been convicted or
                                        forfeited bond or collateral on account of any violation required to be listed
                                        during the past 12 months.</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Fair Credit Reporting Act Authorization -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Fair Credit Reporting Act Authorization
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                                    Pursuant to the federal Fair Credit Reporting Act, I hereby authorize this company and
                                    its designated agents and representatives to conduct a comprehensive review of my
                                    background through any consumer report for employment. I understand that the scope of
                                    the consumer report/investigative consumer report may include, but is not limited to,
                                    the following areas:
                                </p>
                                <ul class="list-disc list-inside text-sm text-gray-600 dark:text-gray-400 space-y-1 ml-4">
                                    <li>verification of Social Security number</li>
                                    <li>current and previous residences</li>
                                    <li>employment history, including all personnel files</li>
                                    <li>education</li>
                                    <li>references</li>
                                    <li>credit history and reports</li>
                                    <li>criminal history, including records from any criminal justice agency in any or all
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
                                            value="{{ old('applicant_signature') }}"
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
                                            value="{{ old('date_signed', date('Y-m-d')) }}"
                                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>



                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.medical.card', $driver_id) }}"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium 
                            text-gray-700 bg-gray-100 border border-gray-300 rounded-lg 
                            hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 
                            focus:ring-gray-400 dark:bg-gray-700 dark:text-gray-200 dark:border-gray-600 dark:hover:bg-gray-600">
                            Back
                        </a>

                        <button type="submit"
                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                            Save & Continue
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
            setupFilePreview('forfeiture_document');

            // Add validation for no violations checkbox
            const noViolationsRadio = document.querySelector('input[name="no_violations"]');
            const violationRadio = document.querySelectorAll('input[name="violation"]');
            const violationTable = document.getElementById('violation_fields');

            if (noViolationsRadio) {
                noViolationsRadio.addEventListener('change', function() {
                    if (this.value === 'yes' && this.checked) {
                        // If "no violations" is checked, automatically select "No" for violation question
                        document.querySelector('input[name="violation"][value="no"]').checked = true;
                        // Hide violation table
                        violationTable.style.display = 'none';
                    } else {
                        violationTable.style.display = 'block';
                    }
                });
            }

            // If "No" is selected for violation question, check "no violations" checkbox
            violationRadio.forEach(radio => {
                radio.addEventListener('change', function() {
                    if (this.value === 'no' && this.checked) {
                        document.querySelector('input[name="no_violations"]').checked = true;
                    }
                });
            });
        });


        // Form validation
        const form = document.querySelector('form');
        if (form) {
            form.addEventListener('submit', function(e) {
                const signature = document.getElementById('applicant_signature');
                const dateSigned = document.getElementById('date_signed');

                // Validate signature
                if (!signature.value.trim()) {
                    e.preventDefault();
                    alert('Please provide your signature.');
                    signature.focus();
                    return false;
                }

                // Validate date
                if (!dateSigned.value) {
                    e.preventDefault();
                    alert('Please select the date signed.');
                    dateSigned.focus();
                    return false;
                }

                // Validate file size (5MB limit) if file is uploaded
                const fileInput = document.getElementById('forfeiture_document');
                if (fileInput && fileInput.files[0]) {
                    const maxSize = 5 * 1024 * 1024; // 5MB in bytes
                    const file = fileInput.files[0];

                    if (file.size > maxSize) {
                        e.preventDefault();
                        alert(`File ${file.name} is too large. Maximum size is 5MB.`);
                        return false;
                    }
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
    </script>
@endpush
