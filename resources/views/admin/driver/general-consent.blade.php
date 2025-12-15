@extends('layouts.main-layout')

@section('title', 'General Consent Form - FMCSA Clearinghouse')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">General Consent for Limited Queries of the FMCSA Drug and Alcohol Clearinghouse</h1>
            <p class="text-gray-600 dark:text-gray-400">Authorization for FMCSA Clearinghouse queries</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
                <form action="{{ route('admin.driver.fmcsa.consent.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">
                    <input type="hidden" name="consent_type" value="multiple_unlimited">

                    <!-- Consent Form Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                General Consent for Limited Queries
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-8">
                                <!-- Main Consent Statement -->
                                <div class="p-6 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/10 dark:border-blue-800 mb-6">
                                    <p class="text-lg text-gray-800 dark:text-white/90 mb-4">
                                        I, <span class="font-bold">{{ $driver_name ?? 'Applicant' }}</span>, hereby provide consent to <span class="font-bold">SKYROS LOGISTICS INC</span> to conduct a limited query of the FMCSA Commercial Driver's License Drug and Alcohol Clearinghouse (Clearinghouse) to determine whether drug or alcohol violation information about me exists in the Clearinghouse.
                                    </p>
                                    
                                    <!-- Multiple Unlimited Queries Declaration -->
                                    <div class="mt-4 p-4 bg-white border border-blue-100 rounded-lg dark:bg-gray-900 dark:border-blue-800">
                                        <p class="text-md font-semibold text-gray-800 dark:text-white/90">
                                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                            I am consenting to multiple unlimited queries and for the duration of employment with SKYROS LOGISTICS INC.
                                        </p>
                                    </div>
                                </div>

                                <!-- Important Information Sections -->
                                <div class="space-y-4 mb-8">
                                    <div class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/10 dark:border-yellow-800">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                            </svg>
                                            <div>
                                                <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-300 mb-1">Important Information About Limited Queries:</h4>
                                                <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                                    I understand that if the limited query conducted by SKYROS LOGISTICS INC indicates that drug or alcohol violation information about me exists in the Clearinghouse, FMCSA will not disclose that information to SKYROS LOGISTICS INC without first obtaining additional specific consent from me.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/10 dark:border-red-800">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 mr-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                            </svg>
                                            <div>
                                                <h4 class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">Consequences of Refusing Consent:</h4>
                                                <p class="text-sm text-red-700 dark:text-red-400">
                                                    I further understand that if I refuse to provide consent for SKYROS LOGISTICS INC to conduct a limited query of the Clearinghouse, SKYROS LOGISTICS INC must prohibit me from performing safety-sensitive functions, including driving a commercial motor vehicle, as required by FMCSA's drug and alcohol program regulations.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Agreement Checkbox -->
                                <div class="mb-6">
                                    <label class="inline-flex items-start cursor-pointer select-none">
                                        <input type="checkbox" name="consent_agreement" value="1" required
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 rounded focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 mt-1" />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            I have read and understand the above consent statements. I voluntarily provide my consent for SKYROS LOGISTICS INC to conduct multiple unlimited queries of the FMCSA Drug and Alcohol Clearinghouse for the duration of my employment.
                                        </span>
                                    </label>
                                </div>
                            </div>

                            <!-- Signature Section -->
                            <div class="mt-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="employee_signature"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                                            Employee Signature <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="employee_signature" id="employee_signature"
                                            value="{{ old('employee_signature') }}"
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
                    <div class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.psp', $driver_id) }}"
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
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const agreement = document.querySelector('input[name="consent_agreement"]');
                    const signature = document.getElementById('employee_signature');
                    const dateSigned = document.getElementById('date_signed');

                    // Validate agreement checkbox
                    if (!agreement.checked) {
                        e.preventDefault();
                        alert('You must agree to the consent terms by checking the box.');
                        agreement.focus();
                        return false;
                    }

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

                    // Confirmation dialog
                    const confirmation = confirm('You are consenting to MULTIPLE UNLIMITED queries of the FMCSA Clearinghouse for the duration of your employment. Do you wish to proceed?');
                    if (!confirmation) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Auto-fill date if not already set
            const dateSignedInput = document.getElementById('date_signed');
            if (dateSignedInput && !dateSignedInput.value) {
                dateSignedInput.value = new Date().toISOString().split('T')[0];
            }

            // Display driver name in consent statement
            const driverName = "{{ $driver_name ?? '' }}";
            console.log('Driver name loaded:', driverName);
        });
    </script>
@endpush