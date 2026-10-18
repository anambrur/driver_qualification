@extends('layouts.main-layout')

@section('title', 'Alcohol & Drug Test Statement')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground dark:text-white/90">
                @if ($isEditMode)
                    Edit Alcohol & Drug Test Statement
                @else
                    Pre-Employment Alcohol & Drug Test Statement
                @endif
            </h1>
            <p class="text-muted-foreground">
                @if ($isEditMode)
                    Update employee certification regarding drug and alcohol testing history (Step 6 of 10)
                @else
                    Employee certification regarding drug and alcohol testing history (Step 6 of 10)
                @endif
            </p>
            <div class="mt-2 flex items-center text-sm text-muted-foreground">
                <x-ui.icon name="user" class="inline-block size-4 align-[-0.125em] mr-2" />
                <span>Driver: {{ $driver->first_name }} {{ $driver->last_name }}</span>
                @if ($driver->company)
                    <span class="mx-2">•</span>
                    <x-ui.icon name="building-2" class="inline-block size-4 align-[-0.125em] mr-2" />
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

                <form action="{{ route('admin.driver.alcohol.and.drug.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">
                    <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

                    <!-- DOT Regulation Information -->
                    <div class="rounded-2xl border border-border bg-card dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-border">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                    49 CFR Part 40.25(j) Compliance Statement
                                </h3>
                                @if ($isEditMode)
                                    <span
                                        class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Edit Mode - Step 6 of 10
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-6">
                                <p class="text-sm text-foreground mb-4">
                                    49 CFR Part 40.25(j) states, as the employer, you must ask the employee whether he or
                                    she has tested positive, or refused to test, on any pre-employment drug or alcohol test
                                    administered by an employer to which the employee applied for, but did not obtain,
                                    safety-sensitive transportation work covered by DOT agency drug and alcohol testing
                                    rules during the past two years.
                                </p>
                                <p class="text-sm text-foreground">
                                    If the employee admits that he or she had a positive test or a refusal to test, you must
                                    not use the employee to perform safety-sensitive functions for you, until and unless the
                                    employee documents successful completion of the return-to-duty process required in 49
                                    CFR Subpart O.
                                </p>
                            </div>

                            <!-- Current Information Display -->
                            @if (
                                $isEditMode &&
                                    $driver_document &&
                                    ($driver_document->drug_test_question_1 || $driver_document->drug_test_question_2))
                                <div
                                    class="mb-6 p-4 rounded-lg bg-muted/50 border border-border">
                                    <h4 class="text-md font-semibold text-foreground dark:text-white/90 mb-3">
                                        Current Alcohol & Drug Test Information
                                    </h4>
                                    <div class="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <span class="font-medium text-muted-foreground">Question 1:</span>
                                            <span
                                                class="ml-2 {{ $driver_document->drug_test_question_1 == 'yes' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                                {{ ucfirst($driver_document->drug_test_question_1 ?? 'Not Answered') }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-muted-foreground">Question 2:</span>
                                            <span
                                                class="ml-2 {{ $driver_document->drug_test_question_2 == 'yes' ? 'text-red-600 dark:text-red-400' : ($driver_document->drug_test_question_2 == 'no' ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground') }}">
                                                {{ $driver_document->drug_test_question_2 ? strtoupper($driver_document->drug_test_question_2) : 'Not Answered' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-muted-foreground">Signature:</span>
                                            <span class="ml-2 text-foreground dark:text-white/90">
                                                {{ $driver_document->drug_test_signature ?? 'Not Signed' }}
                                            </span>
                                        </div>
                                        <div>
                                            <span class="font-medium text-muted-foreground">Date Signed:</span>
                                            <span class="ml-2 text-foreground dark:text-white/90">
                                                {{ $driver_document->drug_test_date_signed ? \Carbon\Carbon::parse($driver_document->drug_test_date_signed)->format('m/d/Y') : 'Not Dated' }}
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <!-- Question 1 -->
                            <div class="mb-8">
                                <h4 class="text-md font-semibold text-foreground dark:text-white/90 mb-4">
                                    1. As the prospective employee, have you tested positive, or refused to test, on any
                                    pre-employment drug or alcohol test administered by an employer to which you applied
                                    for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug
                                    and alcohol testing rules during the past two years?
                                </h4>
                                <div class="flex items-center gap-6">
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_1" value="yes"
                                            class="text-primary focus:ring-primary/20 h-4 w-4 border-input focus:ring-3 focus:outline-hidden"
                                            {{ old('drug_test_question_1', $driver_document->drug_test_question_1 ?? '') == 'yes' ? 'checked' : '' }}
                                            @if (!$isEditMode) required @endif />
                                        <span class="ml-2 text-sm text-foreground">Yes</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_1" value="no"
                                            class="text-primary focus:ring-primary/20 h-4 w-4 border-input focus:ring-3 focus:outline-hidden"
                                            {{ old('drug_test_question_1', $driver_document->drug_test_question_1 ?? '') == 'no' ? 'checked' : '' }}
                                            @if (!$isEditMode) required @endif />
                                        <span class="ml-2 text-sm text-foreground">No</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Question 2 -->
                            <div class="mb-8">
                                <h4 class="text-md font-semibold text-foreground dark:text-white/90 mb-4">
                                    2. Have you tested positive, or refused to test, on any pre-employment drug or alcohol
                                    test administered by an employer to which you applied for, but did not obtain,
                                    safety-sensitive transportation work covered by DOT agency drug and alcohol testing
                                    rules during the past two years?
                                </h4>
                                <div class="flex items-center gap-6">
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_2" value="yes"
                                            class="text-primary focus:ring-primary/20 h-4 w-4 border-input focus:ring-3 focus:outline-hidden"
                                            {{ old('drug_test_question_2', $driver_document->drug_test_question_2 ?? '') == 'yes' ? 'checked' : '' }}
                                            @if (!$isEditMode) required @endif />
                                        <span class="ml-2 text-sm text-foreground">Yes</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_2" value="no"
                                            class="text-primary focus:ring-primary/20 h-4 w-4 border-input focus:ring-3 focus:outline-hidden"
                                            {{ old('drug_test_question_2', $driver_document->drug_test_question_2 ?? '') == 'no' ? 'checked' : '' }}
                                            @if (!$isEditMode) required @endif />
                                        <span class="ml-2 text-sm text-foreground">No</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_2" value="n/a"
                                            class="text-primary focus:ring-primary/20 h-4 w-4 border-input focus:ring-3 focus:outline-hidden"
                                            {{ old('drug_test_question_2', $driver_document->drug_test_question_2 ?? '') == 'n/a' ? 'checked' : '' }}
                                            @if (!$isEditMode) required @endif />
                                        <span class="ml-2 text-sm text-foreground">N/A</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Certification Statement -->
                            <div
                                class="mt-6 p-4 rounded-lg bg-muted/50 border border-border">
                                <p class="text-sm text-foreground">
                                    <strong>I certify that the information provided above is true and complete to the best
                                        of my knowledge. I understand that providing false information may result in
                                        termination of employment.</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Signature Section -->
                    <div class="rounded-2xl border border-border bg-card dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-border">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                Employee Certification
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="applicant_signature"
                                        class="block text-sm font-medium text-foreground mb-2">
                                        Prospective Employee Signature <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="applicant_signature" id="applicant_signature"
                                        value="{{ old('applicant_signature', $driver_document->drug_test_signature ?? '') }}"
                                        placeholder="Type your full name as signature"
                                        class="shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                        @if (!$isEditMode) required @endif>
                                </div>
                                <div>
                                    <label for="date_signed"
                                        class="block text-sm font-medium text-foreground mb-2">
                                        Date Signed <span class="text-red-500">*</span>
                                    </label>
                                    <input type="date" name="date_signed" id="date_signed"
                                        value="{{ old('date_signed', $driver_document->drug_test_date_signed ? \Carbon\Carbon::parse($driver_document->drug_test_date_signed)->format('Y-m-d') : date('Y-m-d')) }}"
                                        class="shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                        @if (!$isEditMode) required @endif>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-border">
                        @if ($isEditMode)
                            <a href="{{ route('admin.driver.edit', ['id' => $driver_id]) }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-foreground bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="arrow-left" class="inline-block size-4 align-[-0.125em] mr-2" />
                                Back to Driver Edit
                            </a>
                        @else
                            <a href="{{ route('admin.driver.violation', ['driver_id' => $driver_id]) }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-foreground bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="arrow-left" class="inline-block size-4 align-[-0.125em] mr-2" />
                                Back to Step 5
                            </a>
                        @endif

                        <div class="flex gap-4">
                            <button type="submit" name="action" value="save"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-primary border border-transparent rounded-lg shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="save" class="inline-block size-4 align-[-0.125em] mr-2" />
                                {{ $isEditMode ? 'Update & Continue to Step 7' : 'Save & Continue to Step 7' }}
                            </button>

                            @if ($isEditMode)
                                <a href="{{ route('admin.driver.fmcsa.consent', ['driver_id' => $driver_id, 'edit' => '1']) }}"
                                    class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-foreground bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
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
                <div class="mt-6 rounded-lg border border-border bg-card dark:bg-white/[0.03] p-4">
                    <h3 class="font-medium text-foreground dark:text-white/90 mb-3">Driver Information</h3>
                    <div class="space-y-2">
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Name:</span>
                            <span class="font-medium text-foreground dark:text-white/90">{{ $driver->first_name }}
                                {{ $driver->last_name }}</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Email:</span>
                            <span class="font-medium text-foreground dark:text-white/90">{{ $driver->email }}</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Phone:</span>
                            <span class="font-medium text-foreground dark:text-white/90">{{ $driver->main_phone }}</span>
                        </div>
                        @if ($driver->company)
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Company:</span>
                                <span
                                    class="font-medium text-foreground dark:text-white/90">{{ $driver->company->company_name }}</span>
                            </div>
                        @endif
                        <div class="flex items-center text-sm">
                            <span class="w-24 text-muted-foreground">Status:</span>
                            <span
                                class="font-medium {{ $driver->status == 'active' ? 'text-green-600 dark:text-green-400' : 'text-yellow-600 dark:text-yellow-400' }}">
                                {{ ucfirst($driver->status) }}
                            </span>
                        </div>
                        @if ($driver_document && $driver_document->drug_test_question_1)
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Drug Test Q1:</span>
                                <span
                                    class="font-medium {{ $driver_document->drug_test_question_1 == 'yes' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400' }}">
                                    {{ ucfirst($driver_document->drug_test_question_1) }}
                                </span>
                            </div>
                        @endif
                        @if ($driver_document && $driver_document->drug_test_question_2)
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Drug Test Q2:</span>
                                <span
                                    class="font-medium {{ $driver_document->drug_test_question_2 == 'yes' ? 'text-red-600 dark:text-red-400' : ($driver_document->drug_test_question_2 == 'no' ? 'text-green-600 dark:text-green-400' : 'text-muted-foreground') }}">
                                    {{ strtoupper($driver_document->drug_test_question_2) }}
                                </span>
                            </div>
                        @endif
                        @if ($driver_document && $driver_document->drug_test_date_signed)
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Last Signed:</span>
                                <span class="font-medium text-foreground dark:text-white/90">
                                    {{ \Carbon\Carbon::parse($driver_document->drug_test_date_signed)->format('m/d/Y') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </div>
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
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
                    const hasExistingData =
                        {{ $driver_document && ($driver_document->drug_test_question_1 || $driver_document->drug_test_question_2) ? 'true' : 'false' }};

                    // Only validate required fields in create mode
                    if (!isEditMode) {
                        const signature = document.getElementById('applicant_signature');
                        const dateSigned = document.getElementById('date_signed');
                        const question1 = document.querySelector(
                            'input[name="drug_test_question_1"]:checked');
                        const question2 = document.querySelector(
                            'input[name="drug_test_question_2"]:checked');

                        // Validate Question 1
                        if (!question1) {
                            showAppAlert('Please answer Question 1.');
                            return;
                        }

                        // Validate Question 2
                        if (!question2) {
                            showAppAlert('Please answer Question 2.');
                            return;
                        }

                        // Validate signature
                        if (!signature.value.trim()) {
                            showAppAlert('Please provide your signature.');
                            signature.focus();
                            return;
                        }

                        // Validate date
                        if (!dateSigned.value) {
                            showAppAlert('Please select the date signed.');
                            dateSigned.focus();
                            return;
                        }
                    }

                    // Additional validation for positive test responses (applies to both modes)
                    const question1 = document.querySelector('input[name="drug_test_question_1"]:checked');
                    const question2 = document.querySelector('input[name="drug_test_question_2"]:checked');

                    if ((question1 && question1.value === 'yes') || (question2 && question2.value === 'yes')) {
                        const confirmation = await showAppConfirm(
                            'You have indicated a positive test result or refusal to test. According to 49 CFR regulations, you may need to provide documentation of successful completion of the return-to-duty process. Do you have this documentation ready?',
                            { icon: 'warning', title: 'Documentation Required' }
                        );
                        if (!confirmation.isConfirmed) {
                            showAppAlert('Please ensure you have the required documentation before proceeding.');
                            return;
                        }
                    }

                    form.submit();
                });
            }

            // Auto-fill date if not already set (only in create mode)
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingDate =
                {{ $driver_document && $driver_document->drug_test_date_signed ? 'true' : 'false' }};

            if (!isEditMode && !hasExistingDate) {
                const dateSignedInput = document.getElementById('date_signed');
                if (dateSignedInput && !dateSignedInput.value) {
                    dateSignedInput.value = new Date().toISOString().split('T')[0];
                }
            }

            // Show warning if editing existing positive responses
            @if (
                $isEditMode &&
                    $driver_document &&
                    ($driver_document->drug_test_question_1 == 'yes' || $driver_document->drug_test_question_2 == 'yes'))
                document.addEventListener('DOMContentLoaded', function() {
                    const currentQ1 = '{{ $driver_document->drug_test_question_1 }}';
                    const currentQ2 = '{{ $driver_document->drug_test_question_2 }}';

                    // Monitor changes to radio buttons
                    const radioButtons = document.querySelectorAll(
                        'input[type="radio"][name^="drug_test_question"]');
                    radioButtons.forEach(radio => {
                        radio.addEventListener('change', async function() {
                            const questionNumber = this.name === 'drug_test_question_1' ?
                                1 : 2;
                            const currentValue = questionNumber === 1 ? currentQ1 :
                                currentQ2;

                            // If changing from 'yes' to something else, show warning
                            if (currentValue === 'yes' && this.value !== 'yes') {
                                const warning = await showAppConfirm(
                                    'Warning: You are changing a previously recorded positive test response. Make sure you have proper documentation for this change.',
                                    { icon: 'warning', title: 'Warning' }
                                );
                                if (!warning.isConfirmed) {
                                    // Revert to original value
                                    const originalRadio = document.querySelector(
                                        `input[name="${this.name}"][value="${currentValue}"]`
                                        );
                                    if (originalRadio) {
                                        originalRadio.checked = true;
                                    }
                                }
                            }
                        });
                    });
                });
            @endif
        });
    </script>
@endpush
