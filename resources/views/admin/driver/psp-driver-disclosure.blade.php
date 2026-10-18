@extends('layouts.main-layout')

@section('title', 'PSP Driver Disclosure & Authorization')

@section('content')
    <x-application.step admin :edit="$isEditMode" :driver="$driver" :step="$currentStep"
        :title="$isEditMode ? 'Edit PSP Driver Disclosure & Authorization' : 'PSP Driver Disclosure & Authorization'"
        :description="$isEditMode ? 'Update authorization for background reports from the PSP Online Service (Step 8 of 10)' : 'Authorization for background reports from the PSP Online Service (Step 8 of 10)'">
        <x-slot:details>
            @if ($driver_document && $driver_document->psp_authorization)
                <x-ui.detail label="PSP Authorization"><x-ui.badge tone="success">Authorized</x-ui.badge></x-ui.detail>
                <x-ui.detail label="Authorized Date" :value="$driver_document->psp_authorization_date ? \Carbon\Carbon::parse($driver_document->psp_authorization_date)->format('m/d/Y') : 'N/A'" />
            @else
                <x-ui.detail label="PSP Authorization"><x-ui.badge tone="danger">Not Authorized</x-ui.badge></x-ui.detail>
            @endif
        </x-slot:details>
        <x-slot:after>
            <x-ui.card size="sm">
                <x-slot:title><span class="inline-flex items-center gap-2"><x-ui.icon name="info" class="size-4" /> About PSP Reports</span></x-slot:title>
                <ul class="grid gap-2 text-sm text-muted-foreground">
                    <li class="flex items-center gap-2"><x-ui.icon name="shield" class="size-4 shrink-0" /> FMCSA Pre-Employment Screening Program</li>
                    <li class="flex items-center gap-2"><x-ui.icon name="car-front" class="size-4 shrink-0" /> 5 years of crash data</li>
                    <li class="flex items-center gap-2"><x-ui.icon name="search" class="size-4 shrink-0" /> 3 years of inspection history</li>
                    <li class="flex items-center gap-2"><x-ui.icon name="scale" class="size-4 shrink-0" /> FMCSR violation records</li>
                </ul>
            </x-ui.card>
        </x-slot:after>

        <form id="step-form" action="{{ route('admin.driver.psp.store') }}" method="POST" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver_id }}">
            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

            @if ($isEditMode && $driver_document && $driver_document->psp_authorization)
                <x-ui.card title="Current PSP Authorization Status">
                    <x-slot:action><x-ui.badge tone="success">Authorized</x-ui.badge></x-slot:action>
                    <div class="grid gap-4">
                        <dl class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/40 p-4">
                            <x-ui.detail label="Status" value="Authorized" />
                            <x-ui.detail label="Authorization Date" :value="$driver_document->psp_authorization_date ? \Carbon\Carbon::parse($driver_document->psp_authorization_date)->format('m/d/Y H:i') : 'N/A'" />
                            <x-ui.detail label="Signature" :value="$driver_document->psp_authorization_signature ?? 'Not Available'" />
                            <x-ui.detail label="Date Signed" :value="$driver_document->psp_authorization_agreement_date ?? 'N/A'" />
                        </dl>
                        <p class="inline-flex items-center gap-2 text-sm text-muted-foreground"><x-ui.icon name="info" class="size-4" /> Updating this form will create a new authorization record.</p>
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card id="disclosure-card" title="IMPORTANT DISCLOSURE REGARDING BACKGROUND REPORTS FROM THE PSP ONLINE SERVICE">
                @if ($isEditMode)
                    <x-slot:action><x-ui.badge tone="info">Edit Mode - Step 8 of 10</x-ui.badge></x-slot:action>
                @endif
                <div class="grid gap-4 text-sm">
                    <p>In connection with your application for employment with <strong>{{ $authUser->name }} ("Prospective Employer")</strong>, Prospective Employer, its employees, agents or contractors may obtain one or more reports regarding your driving, and safety inspection history from the Federal Motor Carrier Safety Administration (FMCSA).</p>
                    <section class="grid gap-2 rounded-lg border bg-muted/40 p-4">
                        <h3 class="font-semibold">In-Person Applications:</h3>
                        <p>When the application for employment is submitted in person, if the Prospective Employer uses any information it obtains from FMCSA in a decision to not hire you or to make any other adverse employment decision regarding you, the Prospective Employer will provide you with a copy of the report upon which its decision was based and a written summary of your rights under the Fair Credit Reporting Act before taking any final adverse action. If any final adverse action is taken against you based upon your driving history or safety report, the Prospective Employer will notify you that the action has been taken and that the action was based in part or in whole on this report.</p>
                    </section>
                    <section class="grid gap-2 rounded-lg border bg-muted/40 p-4">
                        <h3 class="font-semibold">Remote Applications:</h3>
                        <p>When the application for employment is submitted by mail, telephone, computer, or other similar means, if the Prospective Employer uses any information it obtains from FMCSA in a decision to not hire you or to make any other adverse employment decision regarding you, the Prospective Employer must provide you within three business days of taking adverse action oral, written or electronic notification; that adverse action has been taken based in whole or in part on information obtained from FMCSA; the name, address, and the toll free telephone number of FMCSA; that the FMCSA did not make the decision to take the adverse action and is unable to provide you the specific reasons why the adverse action was taken; and that you may, upon providing proper identification, request a free copy of the report and may dispute with the FMCSA the accuracy or completeness of any information or report. If you request a copy of a driver record from the Prospective Employer who procured the report, then, within 3 business days of receiving your request, together with proper identification, the Prospective Employer must send or provide to you a copy of your report and a summary of your rights under the Fair Credit Reporting Act.</p>
                    </section>
                    <x-ui.alert tone="warning" title="Important Information About Data Accuracy:">
                        <p><strong>Neither the Prospective Employer nor the FMCSA contractor supplying the crash and safety information has the capability to correct any safety data that appears to be incorrect.</strong> You may challenge the accuracy of the data by submitting a request to https://dataqs.fmcsa.dot.gov/. If you challenge crash or inspection information reported by a State, FMCSA cannot change or correct this data. Your request will be forwarded by the DataQs system to the appropriate State for adjudication.</p>
                    </x-ui.alert>
                    <section class="grid gap-2 rounded-lg border bg-muted/40 p-4">
                        <h3 class="font-semibold">About Your PSP Report:</h3>
                        <p>Any crash or inspection in which you were involved will display on your PSP report. Since the PSP report does not report or assign, or imply fault, it will include all Commercial Motor Vehicle (CMV) crashes where you were a driver or co-driver and where those crashes were reported to FMCSA, regardless of fault. Similarly, all inspections, with or without violations, appear on the PSP report. State citations associated with Federal Motor Carrier Safety Regulations (FMCSR) violations that have been adjudicated by a court of law will also appear, and remain, on a PSP report.</p>
                    </section>
                    <x-ui.alert tone="info" title="Your Authorization Required:">
                        <strong>The Prospective Employer cannot obtain background reports from FMCSA without your authorization.</strong>
                    </x-ui.alert>
                </div>
            </x-ui.card>

            <x-ui.card title="AUTHORIZATION" description="If you agree that the Prospective Employer may obtain such background reports, please read the following and sign below:">
                <div class="grid gap-6">
                    <div class="grid gap-4 text-sm">
                        <p class="rounded-lg border bg-muted/40 p-4">I authorize <strong>{{ $authUser->name }} ("Prospective Employer")</strong> to access the FMCSA Pre-Employment Screening Program (PSP) system to seek information regarding my commercial driving safety record and information regarding my safety inspection history. I understand that I am authorizing the release of safety performance information including crash data from the previous five (5) years and inspection history from the previous three (3) years. I understand and acknowledge that this release of information may assist the Prospective Employer to make a determination regarding my suitability as an employee.</p>
                        <p class="rounded-lg border bg-muted/40 p-4">I further understand that neither the Prospective Employer nor the FMCSA contractor supplying the crash and safety information has the capability to correct any safety data that appears to be incorrect. I understand I may challenge the accuracy of the data by submitting a request to https://dataqs.fmcsa.dot.gov/. If I challenge crash or inspection information reported by a State, FMCSA cannot change or correct this data. I understand my request will be forwarded by the DataQs system to the appropriate State for adjudication.</p>
                        <p class="rounded-lg border bg-muted/40 p-4">I understand that any crash or inspection in which I was involved will display on my PSP report. Since the PSP report does not report, or assign, or imply fault, I acknowledge it will include all CMV crashes where I was a driver or co-driver and where those crashes were reported to FMCSA, regardless of fault. Similarly, I understand all inspections, with or without violations, will appear on my PSP report, and State citations associated with FMCSR violations that have been adjudicated by a court of law will also appear, and remain, on our PSP report. I have read the above Disclosure Regarding Background Reports provided to me by Prospective Employer and I understand that if I sign this Disclosure and Authorization, Prospective Employer may obtain a report of my crash and inspection history, I hereby authorize Prospective Employer and its employees, authorized agents, and/or affiliates to obtain the information authorized above.</p>
                    </div>

                    <label class="flex items-start gap-3 text-sm leading-relaxed">
                        <input type="checkbox" name="authorization_agreement" value="1" class="input mt-0.5" @if (! $isEditMode) required @endif />
                        <span>I have read and understand the above disclosure and authorization statements.</span>
                    </label>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.field label="Applicant Signature" name="applicant_signature" for="applicant_signature" required>
                            <x-ui.input id="applicant_signature" name="applicant_signature" :value="$driver_document->psp_authorization_signature ?? ''" placeholder="Type your full name as signature" :required="! $isEditMode" />
                        </x-ui.field>
                        <x-ui.field label="Date Signed" name="date_signed" for="date_signed" required>
                            <x-ui.input type="date" id="date_signed" name="date_signed" :value="date('Y-m-d')" :required="! $isEditMode" />
                        </x-ui.field>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.alert tone="danger" title="IMPORTANT NOTICE:">
                This form is made available to monthly account holders by NIC on behalf of the U.S. Department of Transportation, Federal Motor Carrier Safety Administration (FMCSA). Account holders are required by federal law to obtain an Applicant's written or electronic consent prior to accessing the Applicant's PSP report. Further, account holders are required by FMCSA to use the language contained in this Disclosure and Authorization form to obtain an Applicant's consent. The language must be used in whole, exactly as provided. Further, the language on this form must exist as one stand-alone document. The language may NOT be included with other consent forms or any other language.
            </x-ui.alert>

            <x-application.step-actions
                :back="$isEditMode ? route('admin.driver.edit', ['id' => $driver_id]) : route('admin.driver.fmcsa.consent', ['driver_id' => $driver_id])"
                :back-label="$isEditMode ? 'Back to Driver Edit' : 'Back to Step 7'"
                :submit="$isEditMode ? 'Update & Continue to Step 9' : 'Save & Continue to Step 9'"
                :skip="$isEditMode ? 'Skip to Next Step' : null" :skip-href="route('admin.driver.alcohol.and.drug.test.policy', ['driver_id' => $driver_id, 'edit' => '1'])" />
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const employerName = @json($authUser->name);
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingAuthorization =
                {{ $driver_document && $driver_document->psp_authorization ? 'true' : 'false' }};

            // Form validation
            const form = document.getElementById('step-form');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    // Only validate required fields in create mode
                    if (!isEditMode) {
                        const agreement = document.querySelector('input[name="authorization_agreement"]');
                        const signature = document.getElementById('applicant_signature');
                        const dateSigned = document.getElementById('date_signed');

                        // Validate agreement checkbox
                        if (!agreement.checked) {
                            showAppAlert('You must agree to the authorization terms by checking the box.');
                            agreement.focus();
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

                    // Confirm authorization for both modes
                    let confirmationMessage = '';
                    if (isEditMode && hasExistingAuthorization) {
                        confirmationMessage =
                            `You are updating an existing PSP authorization. This will create a new authorization record. Do you wish to proceed?`;
                    } else {
                        confirmationMessage =
                            `By submitting this form, you authorize ${employerName} to access your PSP report from FMCSA. Do you wish to proceed?`;
                    }

                    const confirmation = await showAppConfirm(confirmationMessage);
                    if (confirmation.isConfirmed) {
                        form.submit();
                    }
                });
            }

            // Auto-fill date if not already set (only in create mode or when no existing date)
            const dateSignedInput = document.getElementById('date_signed');
            if (dateSignedInput && !dateSignedInput.value) {
                dateSignedInput.value = new Date().toISOString().split('T')[0];
            }

            // Auto-check agreement if editing and already authorized
            if (isEditMode && hasExistingAuthorization) {
                const agreementCheckbox = document.querySelector('input[name="authorization_agreement"]');
                if (agreementCheckbox && !agreementCheckbox.checked) {
                    agreementCheckbox.checked = true;
                }
            }

            // Track reading of disclosure with timeout to ensure user reads it
            let disclosureRead = false;
            const disclosureSection = document.getElementById('disclosure-card');

            if (disclosureSection && !isEditMode) {
                let scrollTimer;
                window.addEventListener('scroll', function() {
                    const rect = disclosureSection.getBoundingClientRect();
                    const isInViewport = (
                        rect.top >= 0 &&
                        rect.left >= 0 &&
                        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                    );

                    if (isInViewport && !disclosureRead) {
                        clearTimeout(scrollTimer);
                        scrollTimer = setTimeout(function() {
                            disclosureRead = true;
                            console.log('User has had time to read the disclosure section');
                        }, 3000); // 3 seconds to read
                    }
                });

                // Also check on initial load
                setTimeout(function() {
                    const rect = disclosureSection.getBoundingClientRect();
                    const isInViewport = (
                        rect.top >= 0 &&
                        rect.left >= 0 &&
                        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
                        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
                    );

                    if (isInViewport) {
                        disclosureRead = true;
                    }
                }, 1000);
            }
        });
    </script>
@endpush
