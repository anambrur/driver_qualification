@extends('layouts.application-form-layout')

@section('title', 'FMCSA Clearinghouse Consent | DOT Driver Qualification')

@section('content')
    <x-application.step :company="$company" :driver="$driver" :step="7" title="FMCSA Clearinghouse Consent"
        description="Consent to queries of the FMCSA Drug and Alcohol Clearinghouse.">
        <form id="step-form" action="{{ route('public.application.store.step7', ['slug' => $company->slug]) }}" method="POST" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver->id }}">
            <input type="hidden" name="consent_type" value="multiple_unlimited">

            <x-ui.card id="consent-card" title="General Consent for Limited Queries">
                <div class="grid gap-6">
                    <div class="grid gap-4 rounded-lg border bg-muted/40 p-5">
                        <p class="text-base">I, <strong>{{ $driver->first_name }} {{ $driver->last_name }}</strong>, hereby provide consent to <strong>{{ $company->company_name }}</strong> to conduct a limited query of the FMCSA Commercial Driver's License Drug and Alcohol Clearinghouse (Clearinghouse) to determine whether drug or alcohol violation information about me exists in the Clearinghouse.</p>
                        <x-ui.alert tone="success" icon="circle-check">I am consenting to multiple unlimited queries and for the duration of employment with {{ $company->company_name }}.</x-ui.alert>
                    </div>

                    <x-ui.alert tone="warning" title="Important Information About Limited Queries:" data-attention>
                        I understand that if the limited query conducted by {{ $company->company_name }} indicates that drug or alcohol violation information about me exists in the Clearinghouse, FMCSA will not disclose that information to {{ $company->company_name }} without first obtaining additional specific consent from me.
                    </x-ui.alert>
                    <x-ui.alert tone="danger" title="Consequences of Refusing Consent:" data-attention>
                        I further understand that if I refuse to provide consent for {{ $company->company_name }} to conduct a limited query of the Clearinghouse, {{ $company->company_name }} must prohibit me from performing safety-sensitive functions, including driving a commercial motor vehicle, as required by FMCSA's drug and alcohol program regulations.
                    </x-ui.alert>

                    <label class="flex items-start gap-3 text-sm leading-relaxed">
                        <input type="checkbox" name="consent_agreement" value="1" class="input mt-0.5" required />
                        <span>I have read and understand the above consent statements. I voluntarily provide my consent for {{ $company->company_name }} to conduct multiple unlimited queries of the FMCSA Drug and Alcohol Clearinghouse for the duration of my employment.</span>
                    </label>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.field label="Employee Signature" name="employee_signature" for="employee_signature" required>
                            <x-ui.input id="employee_signature" name="employee_signature" :value="$driverDocument->fmcsa_consent_signature ?? ''" placeholder="Type your full name as signature" required />
                        </x-ui.field>
                        <x-ui.field label="Date Signed" name="date_signed" for="date_signed" required>
                            <x-ui.input type="date" id="date_signed" name="date_signed" required
                                :value="$driverDocument?->fmcsa_date_signed ? \Carbon\Carbon::parse($driverDocument->fmcsa_date_signed)->format('Y-m-d') : date('Y-m-d')" />
                        </x-ui.field>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="About FMCSA Clearinghouse">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid content-start gap-2 rounded-lg border bg-muted/40 p-4">
                        <h3 class="inline-flex items-center gap-2 text-sm font-semibold"><x-ui.icon name="database" class="size-4" /> What is the Clearinghouse?</h3>
                        <p class="text-sm text-muted-foreground">A secure online database that gives employers, FMCSA, State Driver Licensing Agencies, and State law enforcement personnel real-time information about commercial driver's license (CDL) and commercial learner's permit (CLP) holders' drug and alcohol program violations.</p>
                    </div>
                    <div class="grid content-start gap-2 rounded-lg border bg-muted/40 p-4">
                        <h3 class="inline-flex items-center gap-2 text-sm font-semibold"><x-ui.icon name="search" class="size-4" /> Limited Queries</h3>
                        <p class="text-sm text-muted-foreground">A check to see if there is any information in the Clearinghouse about a current or prospective driver. If a limited query returns a result of "Driver record not found," no further action is required. If it returns a result of "Requires further investigation," a full query must be conducted within 24 hours.</p>
                    </div>
                </div>
            </x-ui.card>

            <x-application.step-actions :back="route('public.application.step6', ['driver_id' => $driver->id, 'slug' => $company->slug])" back-label="Back to Step 6" submit="Save & Continue to Step 8" />
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const employerName = @json($company->company_name);
            const isEditMode = false;
            const hasExistingConsent =
                {{ $driverDocument && $driverDocument->fmcsa_consent ? 'true' : 'false' }};

            // Form validation
            const form = document.getElementById('step-form');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    // Only validate required fields in create mode
                    if (!isEditMode) {
                        const agreement = document.querySelector('input[name="consent_agreement"]');
                        const signature = document.getElementById('employee_signature');
                        const dateSigned = document.getElementById('date_signed');

                        // Validate agreement checkbox
                        if (!agreement.checked) {
                            showAppAlert('You must agree to the consent terms by checking the box.');
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

                    // Confirmation dialog with different messages for create vs edit
                    let confirmationMessage = '';
                    if (isEditMode && hasExistingConsent) {
                        confirmationMessage =
                            `You are updating an existing FMCSA Clearinghouse consent. This will create a new consent record. Do you wish to proceed?`;
                    } else {
                        confirmationMessage =
                            `You are consenting to MULTIPLE UNLIMITED queries of the FMCSA Clearinghouse for the duration of your employment with ${employerName}. Do you wish to proceed?`;
                    }

                    const confirmation = await showAppConfirm(confirmationMessage);
                    if (confirmation.isConfirmed) {
                        form.submit();
                    }
                });
            }

            // Auto-fill date if not already set (only in create mode or when no existing date)
            const dateSignedInput = document.getElementById('date_signed');
            const hasExistingDate =
                {{ $driverDocument && $driverDocument->fmcsa_date_signed ? 'true' : 'false' }};

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
                const importantSections = document.querySelectorAll('[data-attention]');
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
            const consentForm = document.getElementById('consent-card');

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
