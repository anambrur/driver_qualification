@extends('layouts.main-layout')

@section('title', 'Alcohol & Drug Test Policy')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-foreground dark:text-white/90">
                @if ($isEditMode)
                    Edit Alcohol & Drug Testing Policy
                @else
                    Alcohol & Drug Testing Policy
                @endif
            </h1>
            <p class="text-muted-foreground">
                @if ($isEditMode)
                    Update acknowledgment of alcohol and drug testing policy (Step 9 of 10)
                @else
                    Employee acknowledgment regarding alcohol and drug testing policy (Step 9 of 10)
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

                <form action="{{ route('admin.driver.alcohol.and.drug.test.policy.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">
                    <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

                    <!-- Current Policy Status -->
                    @if ($isEditMode && $driver_document && $driver_document->alcohol_drug_test_policy_signature)
                        <div
                            class="rounded-2xl border border-blue-200 bg-blue-50 dark:border-blue-800 dark:bg-blue-900/10 mb-6">
                            <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-blue-100 dark:border-blue-800">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-semibold text-blue-800 dark:text-blue-200">
                                        Current Policy Acknowledgment Status
                                    </h3>
                                    <span
                                        class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Acknowledged
                                    </span>
                                </div>
                            </div>
                            <div class="p-5 sm:p-6">
                                <div class="grid grid-cols-2 gap-4 text-sm">
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Status:</span>
                                        <span class="ml-2 font-medium text-green-600 dark:text-green-400">
                                            Policy Acknowledged
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Signature Date:</span>
                                        <span class="ml-2 text-blue-800 dark:text-blue-200">
                                            {{ $driver_document->alcohol_drug_test_policy_date ? \Carbon\Carbon::parse($driver_document->alcohol_drug_test_policy_date)->format('m/d/Y') : 'N/A' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Signature:</span>
                                        <span class="ml-2 text-blue-800 dark:text-blue-200">
                                            {{ $driver_document->alcohol_drug_test_policy_signature ?? 'Not Available' }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="font-medium text-blue-600 dark:text-blue-400">Policy:</span>
                                        <span class="ml-2 text-blue-800 dark:text-blue-200">
                                            Alcohol & Drug Testing Policy
                                        </span>
                                    </div>
                                </div>
                                <p class="mt-4 text-sm text-blue-700 dark:text-blue-300">
                                    <x-ui.icon name="info" class="inline-block size-4 align-[-0.125em] mr-2" />
                                    Updating this form will create a new policy acknowledgment.
                                </p>
                            </div>
                        </div>
                    @endif

                    <!-- Consent Form Section -->
                    <div class="rounded-2xl border border-border bg-card dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-border">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                    Alcohol & Drug Testing Policy Acknowledgment
                                </h3>
                                @if ($isEditMode)
                                    <span
                                        class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                        Edit Mode - Step 9 of 10
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-8">
                                <!-- Main Instructions -->
                                <div class="p-6 border border-blue-200 rounded-lg dark:border-blue-800 mb-6">
                                    <p class="text-lg text-foreground dark:text-white/90 mb-4">
                                        Kindly go over the Alcohol and Drug Testing policies for the company below. Once
                                        you've completed, sign electronically to acknowledge receipt, comprehension, and
                                        agreement.
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        A copy of the policy will be sent to you via email as soon as you have finished the
                                        application online.
                                    </p>
                                </div>

                                <!-- PDF Viewer Section -->
                                <div class="mb-6">
                                    <div class="flex justify-between items-center mb-4">
                                        <h4 class="text-lg font-semibold text-foreground dark:text-white/90">
                                            Alcohol & Drug Testing Policy Document
                                        </h4>
                                        @if ($policyPdf && $policyPdf->alcohol_drug_test_policy_file_id)
                                            <div class="flex gap-2">
                                                <a href="{{ route('admin.settings.policy.pdf.file', 'alcohol_drug_test') }}"
                                                    target="_blank"
                                                    class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-primary border border-transparent rounded-lg shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                                    <x-ui.icon name="download" class="inline-block size-4 align-[-0.125em] mr-2" />
                                                    Download PDF
                                                </a>
                                            </div>
                                        @endif
                                    </div>

                                    @if ($policyPdf && $policyPdf->alcohol_drug_test_policy_file_id)
                                        <div class="border border-input rounded-lg overflow-hidden">
                                            <iframe
                                                src="{{ route('admin.settings.policy.pdf.file', 'alcohol_drug_test') }}"
                                                width="100%" height="600px" style="border: none;"
                                                title="Alcohol & Drug Testing Policy PDF">
                                            </iframe>
                                        </div>

                                        <div class="flex justify-between items-center mt-3">
                                            <p class="text-sm text-muted-foreground">
                                                Need help? Call {{ $authUser->company->phone ?? 'N/A' }} or email
                                                {{ $authUser->email ?? 'N/A' }}
                                            </p>
                                            <p class="text-xs text-muted-foreground">
                                                Last updated:
                                                {{ $policyPdf->updated_at ? \Carbon\Carbon::parse($policyPdf->updated_at)->format('m/d/Y') : 'N/A' }}
                                            </p>
                                        </div>
                                    @else
                                        <div
                                            class="p-8 text-center border-2 border-dashed border-input rounded-lg">
                                            <x-ui.icon name="file-text" class="inline-block size-10 align-[-0.125em] text-muted-foreground mb-4" />
                                            <h4 class="text-lg font-medium text-foreground mb-2">
                                                Policy Document Not Available
                                            </h4>
                                            <p class="text-sm text-muted-foreground">
                                                The Alcohol & Drug Testing Policy PDF has not been uploaded yet.
                                            </p>
                                            <p class="text-xs text-muted-foreground mt-2">
                                                Please contact your administrator at {{ $authUser->email ?? 'N/A' }}
                                            </p>
                                        </div>
                                    @endif

                                    <div
                                        class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/10 dark:border-yellow-800">
                                        <div class="flex items-start">
                                            <x-ui.icon name="triangle-alert" class="inline-block size-4 align-[-0.125em] text-yellow-600 dark:text-yellow-400 mt-0.5 mr-3" />
                                            <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                                <strong>Important:</strong> I have read, fully understand and agree to all
                                                terms as set forth in the company alcohol and drug testing policy. My
                                                signature below confirms my acknowledgment and agreement.
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Signature Section -->
                            <div class="mt-8">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="employee_signature"
                                            class="block text-sm font-medium text-foreground mb-2">
                                            Employee Signature <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="employee_signature" id="employee_signature"
                                            value="{{ old('employee_signature', $driver_document->alcohol_drug_test_policy_signature ?? '') }}"
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
                                            value="{{ old('date_signed', $driver_document->alcohol_drug_test_policy_date ? \Carbon\Carbon::parse($driver_document->alcohol_drug_test_policy_date)->format('Y-m-d') : date('Y-m-d')) }}"
                                            class="shadow-theme-xs focus:border-primary/50 focus:ring-primary/10 h-11 w-full rounded-lg border border-input bg-transparent px-4 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:ring-3 focus:outline-hidden dark:text-white/90 dark:placeholder:text-white/30"
                                            @if (!$isEditMode) required @endif>
                                    </div>
                                </div>
                                <p class="mt-4 text-sm text-muted-foreground">
                                    <x-ui.icon name="info" class="inline-block size-4 align-[-0.125em] mr-2" />
                                    By signing, you acknowledge that you have read and understood the Alcohol & Drug Testing
                                    Policy.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Policy Information -->
                    <div
                        class="rounded-2xl border border-border bg-card dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-border">
                            <h3 class="text-lg font-semibold text-foreground dark:text-white/90">
                                About the Alcohol & Drug Testing Policy
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="p-4 bg-muted/50 rounded-lg">
                                    <h4 class="text-sm font-semibold text-foreground dark:text-white/90 mb-2">
                                        <x-ui.icon name="flask-conical" class="inline-block size-4 align-[-0.125em] mr-2" />Testing Requirements
                                    </h4>
                                    <ul class="text-sm text-muted-foreground space-y-1">
                                        <li>• Pre-employment testing</li>
                                        <li>• Random testing</li>
                                        <li>• Reasonable suspicion testing</li>
                                        <li>• Post-accident testing</li>
                                        <li>• Return-to-duty testing</li>
                                    </ul>
                                </div>
                                <div class="p-4 bg-muted/50 rounded-lg">
                                    <h4 class="text-sm font-semibold text-foreground dark:text-white/90 mb-2">
                                        <x-ui.icon name="scale" class="inline-block size-4 align-[-0.125em] mr-2" />Policy Coverage
                                    </h4>
                                    <ul class="text-sm text-muted-foreground space-y-1">
                                        <li>• DOT and non-DOT testing</li>
                                        <li>• Alcohol testing procedures</li>
                                        <li>• Drug testing procedures</li>
                                        <li>• Consequences of violations</li>
                                        <li>• Return-to-duty process</li>
                                    </ul>
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
                            <a href="{{ route('admin.driver.psp', ['driver_id' => $driver_id]) }}"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-foreground bg-card border border-input rounded-lg shadow-theme-xs hover:bg-muted/50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="arrow-left" class="inline-block size-4 align-[-0.125em] mr-2" />
                                Back to Step 8
                            </a>
                        @endif

                        <div class="flex gap-4">
                            <button type="submit" name="action" value="save"
                                class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-primary border border-transparent rounded-lg shadow-theme-xs hover:bg-primary/90 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-primary">
                                <x-ui.icon name="save" class="inline-block size-4 align-[-0.125em] mr-2" />
                                {{ $isEditMode ? 'Update & Continue to Step 10' : 'Save & Continue to Step 10' }}
                            </button>

                            @if ($isEditMode)
                                <a href="{{ route('admin.driver.general.work.policy', ['driver_id' => $driver_id, 'edit' => '1']) }}"
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
                        @if ($driver_document && $driver_document->alcohol_drug_test_policy_signature)
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Policy Status:</span>
                                <span class="font-medium text-green-600 dark:text-green-400">
                                    <x-ui.icon name="circle-check" class="inline-block size-4 align-[-0.125em] mr-1" /> Acknowledged
                                </span>
                            </div>
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Date Acknowledged:</span>
                                <span class="font-medium text-foreground dark:text-white/90">
                                    {{ $driver_document->alcohol_drug_test_policy_date ? \Carbon\Carbon::parse($driver_document->alcohol_drug_test_policy_date)->format('m/d/Y') : 'N/A' }}
                                </span>
                            </div>
                        @else
                            <div class="flex items-center text-sm">
                                <span class="w-24 text-muted-foreground">Policy Status:</span>
                                <span class="font-medium text-red-600 dark:text-red-400">
                                    <x-ui.icon name="circle-x" class="inline-block size-4 align-[-0.125em] mr-1" /> Not Acknowledged
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- Policy Information Card -->
                <div
                    class="mt-6 rounded-lg border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/10 p-4">
                    <h3 class="font-medium text-green-800 dark:text-green-200 mb-3">
                        <x-ui.icon name="file-signature" class="inline-block size-4 align-[-0.125em] mr-2" />Policy Details
                    </h3>
                    <ul class="space-y-2 text-sm text-green-700 dark:text-green-300">
                        <li class="flex items-start">
                            <x-ui.icon name="user-check" class="inline-block size-4 align-[-0.125em] mr-2 mt-0.5" />
                            <span>Mandatory for Employment</span>
                        </li>
                        <li class="flex items-start">
                            <x-ui.icon name="shield" class="inline-block size-4 align-[-0.125em] mr-2 mt-0.5" />
                            <span>Safety & Compliance</span>
                        </li>
                        <li class="flex items-start">
                            <x-ui.icon name="clock" class="inline-block size-4 align-[-0.125em] mr-2 mt-0.5" />
                            <span>Annual Review Required</span>
                        </li>
                        <li class="flex items-start">
                            <x-ui.icon name="circle-alert" class="inline-block size-4 align-[-0.125em] mr-2 mt-0.5" />
                            <span>Zero Tolerance Policy</span>
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
            const employerName = @json($authUser->name ?? 'Employer');
            const isEditMode = {{ $isEditMode ? 'true' : 'false' }};
            const hasExistingPolicyAck =
                {{ $driver_document && $driver_document->alcohol_drug_test_policy_signature ? 'true' : 'false' }};
            const hasPolicyPDF = {{ $policyPdf && $policyPdf->alcohol_drug_test_policy_file_id ? 'true' : 'false' }};

            // Form validation
            const form = document.querySelector('form');
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
            const pdfIframe = document.querySelector('iframe');

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
            const submitButton = document.querySelector('button[type="submit"]');
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
            const policyContent = document.querySelector('.rounded-2xl.border.border-border');

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
