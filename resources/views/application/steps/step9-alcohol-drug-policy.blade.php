@extends('layouts.application-form-layout')

@section('title', 'Alcohol & Drug Testing Policy | DOT Driver Qualification')

@section('content')
    @php
        $hasPolicy = $policyPdf && $policyPdf->alcohol_drug_test_policy_file_id;
    @endphp
    <x-application.step :company="$company" :driver="$driver" :step="9" title="Alcohol & Drug Testing Policy"
        description="Read the company alcohol and drug testing policy and sign to acknowledge it.">
        <form id="step-form" action="{{ route('public.application.store.step9', ['slug' => $company->slug]) }}" method="POST" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver->id }}">

            <x-ui.card id="policy-card" title="Alcohol & Drug Testing Policy Acknowledgment">
                <div class="grid gap-6">
                    <div class="grid gap-3 rounded-lg border p-5">
                        <p class="text-base">Kindly go over the Alcohol and Drug Testing policies for the company below. Once you've completed, sign electronically to acknowledge receipt, comprehension, and agreement.</p>
                        <p class="text-sm text-muted-foreground">A copy of the policy will be sent to you via email as soon as you have finished the application online.</p>
                    </div>

                    <x-application.policy-pdf title="Alcohol & Drug Testing Policy"
                        :url="$hasPolicy ? route('public.application.policy', [$company->slug, 'alcohol_drug_test']) : null"
                        :updated-at="$hasPolicy ? $policyPdf->updated_at : null"
                        :contact-phone="$company->phone" :contact-email="$company->email" />

                    <x-ui.alert tone="warning"><p><strong>Important:</strong> I have read, fully understand and agree to all terms as set forth in the company alcohol and drug testing policy. My signature below confirms my acknowledgment and agreement.</p></x-ui.alert>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.field label="Employee Signature" name="employee_signature" for="employee_signature" required>
                            <x-ui.input id="employee_signature" name="employee_signature" :value="$driverDocument->alcohol_drug_test_policy_signature ?? ''" placeholder="Type your full name as signature" required />
                        </x-ui.field>
                        <x-ui.field label="Date Signed" name="date_signed" for="date_signed" required>
                            <x-ui.input type="date" id="date_signed" name="date_signed" required
                                :value="$driverDocument?->alcohol_drug_test_policy_date ? \Carbon\Carbon::parse($driverDocument->alcohol_drug_test_policy_date)->format('Y-m-d') : date('Y-m-d')" />
                        </x-ui.field>
                    </div>
                    <p class="inline-flex items-center gap-2 text-sm text-muted-foreground"><x-ui.icon name="info" class="size-4 shrink-0" /> By signing, you acknowledge that you have read and understood the Alcohol & Drug Testing Policy.</p>
                </div>
            </x-ui.card>

            <x-ui.card title="About the Alcohol & Drug Testing Policy">
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid content-start gap-2 rounded-lg border bg-muted/40 p-4">
                        <h3 class="inline-flex items-center gap-2 text-sm font-semibold"><x-ui.icon name="flask-conical" class="size-4" /> Testing Requirements</h3>
                        <ul class="grid gap-1 text-sm text-muted-foreground">
                            <li>• Pre-employment testing</li>
                            <li>• Random testing</li>
                            <li>• Reasonable suspicion testing</li>
                            <li>• Post-accident testing</li>
                            <li>• Return-to-duty testing</li>
                        </ul>
                    </div>
                    <div class="grid content-start gap-2 rounded-lg border bg-muted/40 p-4">
                        <h3 class="inline-flex items-center gap-2 text-sm font-semibold"><x-ui.icon name="scale" class="size-4" /> Policy Coverage</h3>
                        <ul class="grid gap-1 text-sm text-muted-foreground">
                            <li>• DOT and non-DOT testing</li>
                            <li>• Alcohol testing procedures</li>
                            <li>• Drug testing procedures</li>
                            <li>• Consequences of violations</li>
                            <li>• Return-to-duty process</li>
                        </ul>
                    </div>
                </div>
            </x-ui.card>

            <x-application.step-actions :back="route('public.application.step8', ['driver_id' => $driver->id, 'slug' => $company->slug])" back-label="Back to Step 8" submit="Save & Continue to Step 10" />
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const employerName = @json($company->company_name);
            const isEditMode = false;
            const hasExistingPolicyAck =
                {{ $driverDocument && $driverDocument->alcohol_drug_test_policy_signature ? 'true' : 'false' }};
            const hasPolicyPDF = {{ $policyPdf && $policyPdf->alcohol_drug_test_policy_file_id ? 'true' : 'false' }};

            // Form validation
            const form = document.getElementById('step-form');
            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();

                    // Only validate required fields in create mode
                    if (!isEditMode) {
                        const signature = document.getElementById('employee_signature');
                        const dateSigned = document.getElementById('date_signed');

                        // Validate signature
                        if (!signature.value.trim()) {
                            showAppAlert('Please provide your signature to acknowledge the policy.');
                            signature.focus();
                            return;
                        }

                        // Validate date
                        if (!dateSigned.value) {
                            showAppAlert('Please select the date signed.');
                            dateSigned.focus();
                            return;
                        }

                        // Check if PDF is available (only warn in create mode)
                        if (!hasPolicyPDF) {
                            const continueWithoutPDF = await showAppConfirm(
                                'The Alcohol & Drug Testing Policy PDF is not available. You may still proceed, but you should request a copy from your administrator. Do you wish to continue?',
                                { icon: 'warning', title: 'Policy PDF Unavailable' }
                            );
                            if (!continueWithoutPDF.isConfirmed) {
                                return;
                            }
                        }
                    }

                    // Confirmation dialog with different messages for create vs edit
                    let confirmationMessage = '';
                    if (isEditMode && hasExistingPolicyAck) {
                        confirmationMessage =
                            `You are updating an existing Alcohol & Drug Testing Policy acknowledgment. This will create a new acknowledgment record. Do you wish to proceed?`;
                    } else {
                        confirmationMessage =
                            `You are acknowledging that you have read and understood the Alcohol & Drug Testing Policy for ${employerName}. Do you wish to proceed?`;
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
                {{ $driverDocument && $driverDocument->alcohol_drug_test_policy_date ? 'true' : 'false' }};

            if (dateSignedInput && !dateSignedInput.value && (!isEditMode || !hasExistingDate)) {
                dateSignedInput.value = new Date().toISOString().split('T')[0];
            }

            // Track PDF viewing time
            let pdfViewTime = 0;
            let pdfViewTimer;
            const pdfIframe = document.querySelector('#step-form iframe');

            if (pdfIframe && !isEditMode) {
                // Start timer when iframe is in viewport
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            // Start timer
                            pdfViewTimer = setInterval(() => {
                                pdfViewTime += 1;
                                console.log('PDF viewed for:', pdfViewTime, 'seconds');

                                // Auto-check requirement after 30 seconds of viewing
                                if (pdfViewTime >= 30) {
                                    console.log('PDF viewed for minimum recommended time');
                                }
                            }, 1000);
                        } else {
                            // Stop timer when out of view
                            if (pdfViewTimer) {
                                clearInterval(pdfViewTimer);
                            }
                        }
                    });
                }, {
                    threshold: 0.5
                });

                observer.observe(pdfIframe);
            }

            // Show reminder if user tries to submit without PDF (create mode only)
            const submitButton = document.querySelector('#step-form button[type="submit"]');
            if (submitButton && !hasPolicyPDF && !isEditMode) {
                submitButton.addEventListener('mouseover', function() {
                    if (!hasPolicyPDF) {
                        this.title =
                            "Warning: Policy PDF not available. Please request from administrator.";
                    }
                });
            }

            // PDF download tracking
            const downloadButton = document.querySelector('a[href*="storage"]');
            if (downloadButton) {
                downloadButton.addEventListener('click', function() {
                    console.log('Policy PDF downloaded by user');
                    // You could send an analytics event here
                });
            }

            // Scroll tracking for policy content
            let policyRead = false;
            const policyContent = document.getElementById('policy-card');

            if (policyContent && !isEditMode) {
                let lastScrollPosition = window.pageYOffset;
                let totalScrollDistance = 0;

                window.addEventListener('scroll', function() {
                    const currentScroll = window.pageYOffset;
                    const scrollDiff = Math.abs(currentScroll - lastScrollPosition);
                    totalScrollDistance += scrollDiff;
                    lastScrollPosition = currentScroll;

                    // Consider policy read if user has scrolled significant amount
                    if (totalScrollDistance > 500 && !policyRead) {
                        policyRead = true;
                        console.log('User has likely read the policy content');
                    }
                });
            }
        });
    </script>
@endpush
