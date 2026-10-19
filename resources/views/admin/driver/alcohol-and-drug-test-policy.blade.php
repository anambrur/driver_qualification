@extends('layouts.main-layout')

@section('title', 'Alcohol & Drug Test Policy')

@section('content')
    @php
        $hasPolicy = $policyPdf && $policyPdf->alcohol_drug_test_policy_file_id;
    @endphp
    <x-application.step admin :edit="$isEditMode" :driver="$driver" :step="$currentStep"
        :title="$isEditMode ? 'Edit Alcohol & Drug Testing Policy' : 'Alcohol & Drug Testing Policy'"
        :description="$isEditMode ? 'Update acknowledgment of alcohol and drug testing policy (Step 9 of 10)' : 'Employee acknowledgment regarding alcohol and drug testing policy (Step 9 of 10)'">
        <x-slot:details>
            @if ($driver_document && $driver_document->alcohol_drug_test_policy_signature)
                <x-ui.detail label="Policy Status"><x-ui.badge tone="success">Acknowledged</x-ui.badge></x-ui.detail>
                <x-ui.detail label="Date Acknowledged" :value="$driver_document->alcohol_drug_test_policy_date ? \Carbon\Carbon::parse($driver_document->alcohol_drug_test_policy_date)->format('m/d/Y') : 'N/A'" />
            @else
                <x-ui.detail label="Policy Status"><x-ui.badge tone="danger">Not Acknowledged</x-ui.badge></x-ui.detail>
            @endif
        </x-slot:details>
        <x-slot:after>
            <x-ui.card size="sm">
                <x-slot:title><span class="inline-flex items-center gap-2"><x-ui.icon name="file-signature" class="size-4" /> Policy Details</span></x-slot:title>
                <ul class="grid gap-2 text-sm text-muted-foreground">
                    <li class="flex items-center gap-2"><x-ui.icon name="user-check" class="size-4 shrink-0" /> Mandatory for Employment</li>
                    <li class="flex items-center gap-2"><x-ui.icon name="shield" class="size-4 shrink-0" /> Safety & Compliance</li>
                    <li class="flex items-center gap-2"><x-ui.icon name="clock" class="size-4 shrink-0" /> Annual Review Required</li>
                    <li class="flex items-center gap-2"><x-ui.icon name="circle-alert" class="size-4 shrink-0" /> Zero Tolerance Policy</li>
                </ul>
            </x-ui.card>
        </x-slot:after>

        <form id="step-form" action="{{ route('admin.driver.alcohol.and.drug.test.policy.store') }}" method="POST" class="grid gap-6">
            @csrf
            <input type="hidden" name="driver_id" value="{{ $driver_id }}">
            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

            @if ($isEditMode && $driver_document && $driver_document->alcohol_drug_test_policy_signature)
                <x-ui.card title="Current Policy Acknowledgment Status">
                    <x-slot:action><x-ui.badge tone="success">Acknowledged</x-ui.badge></x-slot:action>
                    <div class="grid gap-4">
                        <dl class="grid grid-cols-2 gap-3 rounded-lg border bg-muted/40 p-4">
                            <x-ui.detail label="Status" value="Policy Acknowledged" />
                            <x-ui.detail label="Signature Date" :value="$driver_document->alcohol_drug_test_policy_date ? \Carbon\Carbon::parse($driver_document->alcohol_drug_test_policy_date)->format('m/d/Y') : 'N/A'" />
                            <x-ui.detail label="Signature" :value="$driver_document->alcohol_drug_test_policy_signature ?? 'Not Available'" />
                            <x-ui.detail label="Policy" value="Alcohol & Drug Testing Policy" />
                        </dl>
                        <p class="inline-flex items-center gap-2 text-sm text-muted-foreground"><x-ui.icon name="info" class="size-4" /> Updating this form will create a new policy acknowledgment.</p>
                    </div>
                </x-ui.card>
            @endif

            <x-ui.card id="policy-card" title="Alcohol & Drug Testing Policy Acknowledgment">
                @if ($isEditMode)
                    <x-slot:action><x-ui.badge tone="info">Edit Mode - Step 9 of 10</x-ui.badge></x-slot:action>
                @endif
                <div class="grid gap-6">
                    <div class="grid gap-3 rounded-lg border p-5">
                        <p class="text-base">Kindly go over the Alcohol and Drug Testing policies for the company below. Once you've completed, sign electronically to acknowledge receipt, comprehension, and agreement.</p>
                        <p class="text-sm text-muted-foreground">A copy of the policy will be sent to you via email as soon as you have finished the application online.</p>
                    </div>

                    <x-application.policy-pdf title="Alcohol & Drug Testing Policy"
                        :url="$hasPolicy ? route('admin.settings.policy.pdf.file', 'alcohol_drug_test') : null"
                        :updated-at="$hasPolicy ? $policyPdf->updated_at : null"
                        :contact-phone="$authUser->company->phone ?? null" :contact-email="$authUser->email ?? null" />

                    <x-ui.alert tone="warning"><p><strong>Important:</strong> I have read, fully understand and agree to all terms as set forth in the company alcohol and drug testing policy. My signature below confirms my acknowledgment and agreement.</p></x-ui.alert>

                    <div class="grid gap-5 md:grid-cols-2">
                        <x-ui.field label="Employee Signature" name="employee_signature" for="employee_signature" required>
                            <x-ui.input id="employee_signature" name="employee_signature" :value="$driver_document->alcohol_drug_test_policy_signature ?? ''" placeholder="Type your full name as signature" :required="! $isEditMode" />
                        </x-ui.field>
                        <x-ui.field label="Date Signed" name="date_signed" for="date_signed" required>
                            <x-ui.input type="date" id="date_signed" name="date_signed" :required="! $isEditMode"
                                :value="$driver_document?->alcohol_drug_test_policy_date ? \Carbon\Carbon::parse($driver_document->alcohol_drug_test_policy_date)->format('Y-m-d') : date('Y-m-d')" />
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

            <x-application.step-actions
                :back="$isEditMode ? route('admin.driver.edit', ['id' => $driver_id]) : route('admin.driver.psp', ['driver_id' => $driver_id])"
                :back-label="$isEditMode ? 'Back to Driver Edit' : 'Back to Step 8'"
                :submit="$isEditMode ? 'Update & Continue to Step 10' : 'Save & Continue to Step 10'"
                :skip="$isEditMode ? 'Skip to Next Step' : null" :skip-href="route('admin.driver.general.work.policy', ['driver_id' => $driver_id, 'edit' => '1'])" />
        </form>
    </x-application.step>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const employerName = @json($driver->company?->company_name ?? 'Employer');
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingPolicyAck =
                {{ $driver_document && $driver_document->alcohol_drug_test_policy_signature ? 'true' : 'false' }};
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
                {{ $driver_document && $driver_document->alcohol_drug_test_policy_date ? 'true' : 'false' }};

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
