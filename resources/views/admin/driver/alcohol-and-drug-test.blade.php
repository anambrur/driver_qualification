@extends('layouts.main-layout')

@section('title', 'Alcohol & Drug Test Statement')

@section('content')
    <x-application.step admin :edit="$isEditMode" :driver="$driver" :step="$currentStep"
        :title="$isEditMode ? 'Edit Alcohol & Drug Test Statement' : 'Pre-Employment Alcohol & Drug Test Statement'"
        :description="$isEditMode ? 'Update employee certification regarding drug and alcohol testing history (Step 6 of 10)' : 'Employee certification regarding drug and alcohol testing history (Step 6 of 10)'">
        <x-slot:details>
            @if ($driver_document && $driver_document->drug_test_question_1)
                <x-ui.detail label="Drug Test Q1"><x-ui.badge :tone="$driver_document->drug_test_question_1 == 'yes' ? 'danger' : 'success'">{{ ucfirst($driver_document->drug_test_question_1) }}</x-ui.badge></x-ui.detail>
            @endif
            @if ($driver_document && $driver_document->drug_test_question_2)
                <x-ui.detail label="Drug Test Q2"><x-ui.badge :tone="$driver_document->drug_test_question_2 == 'yes' ? 'danger' : 'success'">{{ strtoupper($driver_document->drug_test_question_2) }}</x-ui.badge></x-ui.detail>
            @endif
            @if ($driver_document && $driver_document->drug_test_date_signed)
                <x-ui.detail label="Last Signed" :value="\Carbon\Carbon::parse($driver_document->drug_test_date_signed)->format('m/d/Y')" />
            @endif
        </x-slot:details>

        <form id="step-form" action="{{ route('admin.driver.alcohol.and.drug.store') }}" method="POST" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver_id }}">
            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

            <x-ui.card title="49 CFR Part 40.25(j) Compliance Statement">
                @if ($isEditMode)
                    <x-slot:action><x-ui.badge tone="info">Edit Mode - Step 6 of 10</x-ui.badge></x-slot:action>
                @endif
                <div class="grid gap-8">
                    <div class="grid gap-4 text-sm">
                        <p>49 CFR Part 40.25(j) states, as the employer, you must ask the employee whether he or she has tested positive, or refused to test, on any pre-employment drug or alcohol test administered by an employer to which the employee applied for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug and alcohol testing rules during the past two years.</p>
                        <p>If the employee admits that he or she had a positive test or a refusal to test, you must not use the employee to perform safety-sensitive functions for you, until and unless the employee documents successful completion of the return-to-duty process required in 49 CFR Subpart O.</p>
                    </div>

                    @if ($isEditMode && $driver_document && ($driver_document->drug_test_question_1 || $driver_document->drug_test_question_2))
                        <section class="grid gap-3 rounded-lg border bg-muted/40 p-4">
                            <h3 class="font-semibold">Current Alcohol & Drug Test Information</h3>
                            <dl class="grid grid-cols-2 gap-3">
                                <x-ui.detail label="Question 1" :value="ucfirst($driver_document->drug_test_question_1 ?? 'Not Answered')" />
                                <x-ui.detail label="Question 2" :value="$driver_document->drug_test_question_2 ? strtoupper($driver_document->drug_test_question_2) : 'Not Answered'" />
                                <x-ui.detail label="Signature" :value="$driver_document->drug_test_signature ?? 'Not Signed'" />
                                <x-ui.detail label="Date Signed" :value="$driver_document->drug_test_date_signed ? \Carbon\Carbon::parse($driver_document->drug_test_date_signed)->format('m/d/Y') : 'Not Dated'" />
                            </dl>
                        </section>
                    @endif

                    <fieldset class="grid gap-3">
                        <legend class="mb-3 font-medium leading-snug">1. As the prospective employee, have you tested positive, or refused to test, on any pre-employment drug or alcohol test administered by an employer to which you applied for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug and alcohol testing rules during the past two years?</legend>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_1" value="yes" class="input" {{ old('drug_test_question_1', $driver_document->drug_test_question_1 ?? '') == 'yes' ? 'checked' : '' }} @if (! $isEditMode) required @endif />
                                    Yes
                                </label>
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_1" value="no" class="input" {{ old('drug_test_question_1', $driver_document->drug_test_question_1 ?? '') == 'no' ? 'checked' : '' }} @if (! $isEditMode) required @endif />
                                    No
                                </label>
                        </div>
                    </fieldset>

                    <fieldset class="grid gap-3">
                        <legend class="mb-3 font-medium leading-snug">2. Have you tested positive, or refused to test, on any pre-employment drug or alcohol test administered by an employer to which you applied for, but did not obtain, safety-sensitive transportation work covered by DOT agency drug and alcohol testing rules during the past two years?</legend>
                        <div class="flex flex-wrap gap-x-6 gap-y-2">
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_2" value="yes" class="input" {{ old('drug_test_question_2', $driver_document->drug_test_question_2 ?? '') == 'yes' ? 'checked' : '' }} @if (! $isEditMode) required @endif />
                                    Yes
                                </label>
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_2" value="no" class="input" {{ old('drug_test_question_2', $driver_document->drug_test_question_2 ?? '') == 'no' ? 'checked' : '' }} @if (! $isEditMode) required @endif />
                                    No
                                </label>
                                <label class="label inline-flex items-center gap-2 font-normal">
                                    <input type="radio" name="drug_test_question_2" value="n/a" class="input" {{ old('drug_test_question_2', $driver_document->drug_test_question_2 ?? '') == 'n/a' ? 'checked' : '' }} @if (! $isEditMode) required @endif />
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
                        <x-ui.input id="applicant_signature" name="applicant_signature" :value="$driver_document->drug_test_signature ?? ''" placeholder="Type your full name as signature" :required="! $isEditMode" />
                    </x-ui.field>
                    <x-ui.field label="Date Signed" name="date_signed" for="date_signed" required>
                        <x-ui.input type="date" id="date_signed" name="date_signed" :required="! $isEditMode"
                            :value="$driver_document?->drug_test_date_signed ? \Carbon\Carbon::parse($driver_document->drug_test_date_signed)->format('Y-m-d') : date('Y-m-d')" />
                    </x-ui.field>
                </div>
            </x-ui.card>

            <x-application.step-actions
                :back="$isEditMode ? route('admin.driver.edit', ['id' => $driver_id]) : route('admin.driver.violation', ['driver_id' => $driver_id])"
                :back-label="$isEditMode ? 'Back to Driver Edit' : 'Back to Step 5'"
                :submit="$isEditMode ? 'Update & Continue to Step 7' : 'Save & Continue to Step 7'"
                :skip="$isEditMode ? 'Skip to Next Step' : null" :skip-href="route('admin.driver.fmcsa.consent', ['driver_id' => $driver_id, 'edit' => '1'])" />
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
            @endif
        });
    </script>
@endpush
