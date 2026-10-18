@extends('layouts.application-form-layout')

@section('title', 'Alcohol & Drug Test Statement | DOT Driver Qualification')

@section('content')
    <x-application.step :company="$company" :driver="$driver" :step="6" title="Alcohol & Drug Test Statement"
        description="Answer the 49 CFR Part 40.25(j) questions and sign the certification.">
        <form id="step-form" action="{{ route('public.application.store.step6', ['slug' => $company->slug]) }}" method="POST" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver->id }}">

            <x-ui.card title="49 CFR Part 40.25(j) Compliance Statement">
                <div class="grid gap-8">
                    <div class="grid gap-4 text-sm">
                        <p>49 CFR Part 40.25(j) states, as the employer, you must ask the employee whether he or she has tested positive, or refused to test, on any pre-employment drug or alcohol test administered by an employer to which the employee applied for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug and alcohol testing rules during the past two years.</p>
                        <p>If the employee admits that he or she had a positive test or a refusal to test, you must not use the employee to perform safety-sensitive functions for you, until and unless the employee documents successful completion of the return-to-duty process required in 49 CFR Subpart O.</p>
                    </div>

                    <fieldset class="grid gap-3">
                        <legend class="mb-3 font-medium leading-snug">1. As the prospective employee, have you tested positive, or refused to test, on any pre-employment drug or alcohol test administered by an employer to which you applied for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug and alcohol testing rules during the past two years?</legend>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_1" value="yes" class="input" {{ old('drug_test_question_1', $driverDocument->drug_test_question_1 ?? '') == 'yes' ? 'checked' : '' }} required />
                                    Yes
                                </label>
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_1" value="no" class="input" {{ old('drug_test_question_1', $driverDocument->drug_test_question_1 ?? '') == 'no' ? 'checked' : '' }} required />
                                    No
                                </label>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-3">
                        <legend class="mb-3 font-medium leading-snug">2. Have you tested positive, or refused to test, on any pre-employment drug or alcohol test administered by an employer to which you applied for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug and alcohol testing rules during the past two years?</legend>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_2" value="yes" class="input" {{ old('drug_test_question_2', $driverDocument->drug_test_question_2 ?? '') == 'yes' ? 'checked' : '' }} required />
                                    Yes
                                </label>
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_2" value="no" class="input" {{ old('drug_test_question_2', $driverDocument->drug_test_question_2 ?? '') == 'no' ? 'checked' : '' }} required />
                                    No
                                </label>
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_2" value="n/a" class="input" {{ old('drug_test_question_2', $driverDocument->drug_test_question_2 ?? '') == 'n/a' ? 'checked' : '' }} required />
                                    N/A
                                </label>
                        </div>
                    </fieldset>

                    <x-ui.alert>I certify that the information provided above is true and complete to the best of my knowledge. I understand that providing false information may result in termination of employment.</x-ui.alert>
                </div>
            </x-ui.card>

            <x-ui.card title="Employee Certification">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.field label="Prospective Employee Signature" name="applicant_signature" for="applicant_signature" required>
                        <x-ui.input id="applicant_signature" name="applicant_signature" :value="$driverDocument->drug_test_signature ?? ''" placeholder="Type your full name as signature" required />
                    </x-ui.field>
                    <x-ui.field label="Date Signed" name="date_signed" for="date_signed" required>
                        <x-ui.input type="date" id="date_signed" name="date_signed" required
                            :value="$driverDocument?->drug_test_date_signed ? \Carbon\Carbon::parse($driverDocument->drug_test_date_signed)->format('Y-m-d') : date('Y-m-d')" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <x-application.step-actions :back="route('public.application.step5', ['driver_id' => $driver->id, 'slug' => $company->slug])" back-label="Back to Step 5" submit="Save & Continue to Step 7" />
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Form validation
            const form = document.getElementById('step-form');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

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

                    // Additional validation for positive test responses
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

            // Auto-fill date if not already set
            const hasExistingDate =
                {{ $driverDocument && $driverDocument->drug_test_date_signed ? 'true' : 'false' }};

            if (!hasExistingDate) {
                const dateSignedInput = document.getElementById('date_signed');
                if (dateSignedInput && !dateSignedInput.value) {
                    dateSignedInput.value = new Date().toISOString().split('T')[0];
                }
            }

        });
    </script>
@endpush
