@extends('layouts.main-layout')

@section('title', 'General Consent Form - FMCSA Clearinghouse')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                @if ($isEditMode)
                    Edit FMCSA Clearinghouse Consent
                @else
                    General Consent for Limited Queries of the FMCSA Drug and Alcohol Clearinghouse
                @endif
            </h1>
            <p class="text-gray-600 dark:text-gray-400">
                @if ($isEditMode)
                    Update authorization for FMCSA Clearinghouse queries (Step 7 of 10)
                @else
                    Authorization for FMCSA Clearinghouse queries (Step 7 of 10)
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
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
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

                <form action="{{ route('admin.driver.fmcsa.consent.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">
                    <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">
                    <input type="hidden" name="consent_type" value="multiple_unlimited">

                    <!-- Current Consent Status -->
                    @if ($isEditMode && $driver_document && $driver_document->fmcsa_consent)
                        <div
                            class="rounded-2xl border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/10 mb-6">
                            <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-blue-100 dark:border-blue-800">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">
                                        Current FMCSA Consent Status
                                    </h3>
                                    <span
                                        class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Consent Granted
                                    </span>
                                </div>
                            </div>
                            <div class="p-5 sm:p-6">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Status:</span>
                                        <span class="ml-2 font-medium text-green-600 dark:text-green-400">
                                            Multiple Unlimited Queries
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Consent Date:</span>
                                        <span class="ml-2 text-blue-800 dark:text-blue-200">
                                            {{ $driver_document->fmcsa_consent_date ? \Carbon\Carbon::parse($driver_document->fmcsa_consent_date)->format('m/d/Y H:i') : 'N/A' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Signature:</span>
                                        <span class="ml-2 text-blue-800 dark:text-blue-200">
                                            {{ $driver_document->fmcsa_consent_signature ?? 'Not Available' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Date Signed:</span>
                                        <span class="ml-2 text-blue-800 dark:text-blue-200">
                                            {{ $driver_document->fmcsa_date_signed ?? 'N/A' }}
                                        </span>
                                    </div>
                                </div>
                                <p class="mt-4 text-sm text-blue-700 dark:text-blue-300">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    Updating this form will create a new consent record.
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Consent Form Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                    General Consent for Limited Queries
                                </h3>
                                @if ($isEditMode)
                                    <span
                                        class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Edit Mode - Step 7 of 10
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-8">
                                <!-- Main Consent Statement -->
                                <div
                                    class="p-6 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/10 dark:border-blue-800 mb-6">
                                    <p class="text-lg text-gray-800 dark:text-white/90 mb-4">
                                        I, <span class="font-bold">{{ $driver->first_name }}
                                            {{ $driver->last_name }}</span>, hereby provide consent to <span
                                            class="font-bold">{{ $authUser->name }}</span> to conduct a limited query of
                                        the FMCSA Commercial Driver's License Drug and Alcohol Clearinghouse (Clearinghouse)
                                        to determine whether drug or alcohol violation information about me exists in the
                                        Clearinghouse.
                                    </p>

                                    <!-- Multiple Unlimited Queries Declaration -->
                                    <div
                                        class="mt-4 p-4 bg-white border border-blue-100 rounded-lg dark:bg-gray-900 dark:border-blue-800">
                                        <p class="text-md font-semibold text-gray-800 dark:text-white/90">
                                            <i class="fas fa-check-circle text-green-500 mr-2"></i>
                                            I am consenting to multiple unlimited queries and for the duration of employment
                                            with {{ $authUser->name }}.
                                        </p>
                                    </div>
                                </div>

                                <!-- Important Information Sections -->
                                <div class="space-y-4 mb-8">
                                    <div
                                        class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/10 dark:border-yellow-800">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mt-0.5 mr-3 flex-shrink-0"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            <div>
                                                <h4 class="text-sm font-semibold text-yellow-800 dark:text-yellow-300 mb-1">
                                                    Important Information About Limited Queries:</h4>
                                                <p class="text-sm text-yellow-700 dark:text-yellow-400">
                                                    I understand that if the limited query conducted by
                                                    {{ $authUser->name }} indicates that drug or alcohol violation
                                                    information about me exists in the Clearinghouse, FMCSA will not
                                                    disclose that information to {{ $authUser->name }} without first
                                                    obtaining additional specific consent from me.
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        class="p-4 bg-red-50 border border-red-200 rounded-lg dark:bg-red-900/10 dark:border-red-800">
                                        <div class="flex items-start">
                                            <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 mr-3 flex-shrink-0"
                                                fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd"
                                                    d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                                    clip-rule="evenodd" />
                                            </svg>
                                            <div>
                                                <h4 class="text-sm font-semibold text-red-800 dark:text-red-300 mb-1">
                                                    Consequences of Refusing Consent:</h4>
                                                <p class="text-sm text-red-700 dark:text-red-400">
                                                    I further understand that if I refuse to provide consent for
                                                    {{ $authUser->name }} to conduct a limited query of the Clearinghouse,
                                                    {{ $authUser->name }} must prohibit me from performing safety-sensitive
                                                    functions, including driving a commercial motor vehicle, as required by
                                                    FMCSA's drug and alcohol program regulations.
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Agreement Checkbox -->
                                <div class="mb-6">
                                    <label class="inline-flex items-start cursor-pointer select-none">
                                        <input type="checkbox" name="consent_agreement" value="1"
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 rounded focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 mt-1"
                                            @if (!$isEditMode) required @endif />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                            I have read and understand the above consent statements. I voluntarily provide
                                            my consent for {{ $authUser->name }} to conduct multiple unlimited queries of
                                            the FMCSA Drug and Alcohol Clearinghouse for the duration of my employment.
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
                                            value="{{ old('employee_signature', $driver_document->fmcsa_consent_signature ?? '') }}"
                                            placeholder="Type your full name as signature"
                                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                            @if (!$isEditMode) required @endif>
                                    </div>
                                    <div>
                                        <label for="date_signed"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                                            Date Signed <span class="text-red-500">*</span>
                                        </label>
                                        <input type="date" name="date_signed" id="date_signed"
                                            value="{{ old('date_signed', $driver_document->fmcsa_date_signed ? \Carbon\Carbon::parse($driver_document->fmcsa_date_signed)->format('Y-m-d') : date('Y-m-d')) }}"
                                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                            @if (!$isEditMode) required @endif>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- FMCSA Clearinghouse Information -->
                    <div
                        class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                About FMCSA Clearinghouse
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-2">
                                        <i class="fas fa-database mr-2"></i>What is the Clearinghouse?
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        A secure online database that gives employers, FMCSA, State Driver Licensing
                                        Agencies, and State law enforcement personnel real-time information about commercial
                                        driver's license (CDL) and commercial learner's permit (CLP) holders' drug and
                                        alcohol program violations.
                                    </p>
                                </div>
                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-2">
                                        <i class="fas fa-search mr-2"></i>Limited Queries
                                    </h4>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        A check to see if there is any information in the Clearinghouse about a current or
                                        prospective driver. If a limited query returns a result of "Driver record not
                                        found," no further action is required. If it returns a result of "Requires further
                                        investigation," a full query must be conducted within 24 hours.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

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
                            <a href="{{ route('admin.driver.psp', ['driver_id' => $driver_id]) }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                <i class="fas fa-arrow-left mr-2"></i>
                                Back to Step 7
                            </a>
                        @endif

                        <div class="flex gap-4">
                            <button type="submit" name="action" value="save"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                <i class="fas fa-save mr-2"></i>
                                {{ $isEditMode ? 'Update & Continue to Step 8' : 'Save & Continue to Step 9' }}
                            </button>

                            @if ($isEditMode)
                                <a href="{{ route('admin.driver.alcohol.and.drug.test.policy', ['driver_id' => $driver_id, 'edit' => '1']) }}"
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
                    'driver_id' => $driver_id,
                ])

                <!-- Driver Info Card -->
                <div class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4">
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
                            <span class="font-medium text-gray-800 dark:text-white/90">{{ $driver->main_phone }}</span>
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
                        @if ($driver_document && $driver_document->fmcsa_consent)
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-gray-500 dark:text-gray-400">FMCSA Consent:</span>
                                <span class="font-medium text-green-600 dark:text-green-400">
                                    <i class="fas fa-check-circle mr-1"></i> Granted
                                </span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-gray-500 dark:text-gray-400">Consent Date:</span>
                                <span class="font-medium text-gray-800 dark:text-white/90">
                                    {{ $driver_document->fmcsa_consent_date ? \Carbon\Carbon::parse($driver_document->fmcsa_consent_date)->format('m/d/Y') : 'N/A' }}
                                </span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-gray-500 dark:text-gray-400">Type:</span>
                                <span class="font-medium text-gray-800 dark:text-white/90">
                                    Multiple Unlimited Queries
                                </span>
                            </div>
                        @else
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-gray-500 dark:text-gray-400">FMCSA Consent:</span>
                                <span class="font-medium text-red-600 dark:text-red-400">
                                    <i class="fas fa-times-circle mr-1"></i> Not Granted
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- FMCSA Information Card -->
                <div
                    class="mt-6 rounded-lg border border-purple-200 bg-purple-50 dark:border-purple-800 dark:bg-purple-900/10 p-4">
                    <h3 class="font-medium text-purple-800 dark:text-purple-200 mb-3">
                        <i class="fas fa-shield-alt mr-2"></i>FMCSA Clearinghouse
                    </h3>
                    <ul class="space-y-2 text-sm text-purple-700 dark:text-purple-300">
                        <li class="flex items-start">
                            <i class="fas fa-user-shield mr-2 mt-0.5"></i>
                            <span>Drug & Alcohol Violations Database</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-clock mr-2 mt-0.5"></i>
                            <span>Mandatory for CDL Drivers</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-exclamation-triangle mr-2 mt-0.5"></i>
                            <span>Refusal = No Driving</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-search mr-2 mt-0.5"></i>
                            <span>Employers Must Query Annually</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const employerName = @json($authUser->name);
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingConsent =
            {{ $driver_document && $driver_document->fmcsa_consent ? 'true' : 'false' }};

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Only validate required fields in create mode
                    if (!isEditMode) {
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
                    }

                    // Confirmation dialog with different messages for create vs edit
                    let confirmationMessage = '';
                    if (isEditMode && hasExistingConsent) {
                        confirmationMessage =
                            `You are updating an existing FMCSA Clearinghouse consent. This will create a new consent record. Do you wish to proceed?`;
                    } else {
                        confirmationMessage =
                            `You are consenting to MULTIPLE UNLIMITED queries of the FMCSA Clearinghouse for the duration of your employment with ${employerName}. Do you wish to proceed?`;
                    }

                    const confirmation = confirm(confirmationMessage);
                    if (!confirmation) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Auto-fill date if not already set (only in create mode or when no existing date)
            const dateSignedInput = document.getElementById('date_signed');
            const hasExistingDate =
                {{ $driver_document && $driver_document->fmcsa_date_signed ? 'true' : 'false' }};

            if (dateSignedInput && !dateSignedInput.value && (!isEditMode || !hasExistingDate)) {
                dateSignedInput.value = new Date().toISOString().split('T')[0];
            }

            // Auto-check agreement if editing and already consented
            if (isEditMode && hasExistingConsent) {
                const agreementCheckbox = document.querySelector('input[name="consent_agreement"]');
                if (agreementCheckbox && !agreementCheckbox.checked) {
                    agreementCheckbox.checked = true;
                }
            }

            // Highlight important sections for user attention
            if (!isEditMode) {
                const importantSections = document.querySelectorAll('.bg-yellow-50, .bg-red-50');
                importantSections.forEach((section, index) => {
                    // Add slight animation to draw attention
                    setTimeout(() => {
                        section.classList.add('animate-pulse');
                        setTimeout(() => {
                            section.classList.remove('animate-pulse');
                        }, 2000);
                    }, index * 1000);
                });
            }

            // Track if user has scrolled through the entire form
            let formRead = false;
            const consentForm = document.querySelector('.rounded-2xl.border.border-gray-200');

            if (consentForm && !isEditMode) {
                let scrollPosition = 0;
                const formHeight = consentForm.offsetHeight;

                window.addEventListener('scroll', function() {
                    const rect = consentForm.getBoundingClientRect();
                    const visibleHeight = Math.min(rect.bottom, window.innerHeight) - Math.max(rect.top, 0);
                    const scrollPercentage = (visibleHeight / formHeight) * 100;

                    if (scrollPercentage > 80 && !formRead) {
                        formRead = true;
                        console.log('User has viewed the consent form');
                    }
                });
            }
        });
    </script>
@endpush
