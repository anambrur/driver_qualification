@extends('layouts.main-layout')

@section('title', 'Alcohol & Drug Test Statement')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Pre-Employment Alcohol & Drug Test Statement</h1>
            <p class="text-gray-600 dark:text-gray-400">Employee certification regarding drug and alcohol testing history</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
                <form action="{{ route('admin.driver.alcohol.and.drug.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">

                    <!-- DOT Regulation Information -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                49 CFR Part 40.25(j) Compliance Statement
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-6">
                                <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                                    49 CFR Part 40.25(j) states, as the employer, you must ask the employee whether he or
                                    she has tested positive, or refused to test, on any pre-employment drug or alcohol test
                                    administered by an employer to which the employee applied for, but did not obtain,
                                    safety-sensitive transportation work covered by DOT agency drug and alcohol testing
                                    rules during the past two years.
                                </p>
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    If the employee admits that he or she had a positive test or a refusal to test, you must
                                    not use the employee to perform safety-sensitive functions for you, until and unless the
                                    employee documents successful completion of the return-to-duty process required in 49
                                    CFR Subpart O.
                                </p>
                            </div>

                            <!-- Question 1 - Pre-select existing value -->
                            <div class="mb-8">
                                <h4 class="text-md font-semibold text-gray-800 dark:text-white/90 mb-4">
                                    1. As the prospective employee, have you tested positive, or refused to test, on any
                                    pre-employment drug or alcohol test administered by an employer to which you applied
                                    for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug
                                    and alcohol testing rules during the past two years?
                                </h4>
                                <div class="flex items-center gap-6">
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_1" value="yes" required
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
                                            {{ old('drug_test_question_1', $driver_document->drug_test_question_1) == 'yes' ? 'checked' : '' }} />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_1" value="no" required
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
                                            {{ old('drug_test_question_1', $driver_document->drug_test_question_1) == 'no' ? 'checked' : '' }} />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                    </label>
                                </div>
                            </div>

                            <!-- Question 2 - Pre-select existing value -->
                            <div class="mb-8">
                                <h4 class="text-md font-semibold text-gray-800 dark:text-white/90 mb-4">
                                    2. Have you tested positive, or refused to test, on any pre-employment drug or alcohol
                                    test administered by an employer to which you applied for, but did not obtain,
                                    safety-sensitive transportation work covered by DOT agency drug and alcohol testing
                                    rules during the past two years?
                                </h4>
                                <div class="flex items-center gap-6">
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_2" value="yes" required
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
                                            {{ old('drug_test_question_2', $driver_document->drug_test_question_2) == 'yes' ? 'checked' : '' }} />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Yes</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_2" value="no" required
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
                                            {{ old('drug_test_question_2', $driver_document->drug_test_question_2) == 'no' ? 'checked' : '' }} />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">No</span>
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer select-none">
                                        <input type="radio" name="drug_test_question_2" value="na" required
                                            class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900"
                                            {{ old('drug_test_question_2', $driver_document->drug_test_question_2) == 'n/a' ? 'checked' : '' }} />
                                        <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">N/A</span>
                                    </label>
                                </div>
                            </div>


                            <!-- Certification Statement -->
                            <div
                                class="mt-6 p-4 rounded-lg bg-gray-50 border border-gray-200 dark:bg-gray-900/20 dark:border-gray-800">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    <strong>I certify that the information provided above is true and complete to the best
                                        of my knowledge. I understand that providing false information may result in
                                        termination of employment.</strong>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Signature Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Employee Certification
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="applicant_signature"
                                        class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                                        Prospective Employee Signature <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="applicant_signature" id="applicant_signature"
                                        value="{{ old('applicant_signature',$driver_document->drug_test_signature) }}"
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
                                        value="{{ $driver_document->drug_test_date_signed ?? date('Y-m-d'), old('date_signed') }}"
                                        class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                        required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.violation', $driver_id) }}"
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
                    const signature = document.getElementById('applicant_signature');
                    const dateSigned = document.getElementById('date_signed');
                    const question1 = document.querySelector('input[name="drug_test_question_1"]:checked');
                    const question2 = document.querySelector('input[name="drug_test_question_2"]:checked');

                    // Validate Question 1
                    if (!question1) {
                        e.preventDefault();
                        alert('Please answer Question 1.');
                        return false;
                    }

                    // Validate Question 2
                    if (!question2) {
                        e.preventDefault();
                        alert('Please answer Question 2.');
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

                    // Additional validation for positive test responses
                    if (question1.value === 'yes' || question2.value === 'yes') {
                        const confirmation = confirm(
                            'You have indicated a positive test result or refusal to test. According to 49 CFR regulations, you may need to provide documentation of successful completion of the return-to-duty process. Do you have this documentation ready?'
                        );
                        if (!confirmation) {
                            e.preventDefault();
                            alert('Please ensure you have the required documentation before proceeding.');
                            return false;
                        }
                    }
                });
            }

            // Auto-fill date if not already set
            const dateSignedInput = document.getElementById('date_signed');
            if (dateSignedInput && !dateSignedInput.value) {
                dateSignedInput.value = new Date().toISOString().split('T')[0];
            }
        });
    </script>
@endpush
