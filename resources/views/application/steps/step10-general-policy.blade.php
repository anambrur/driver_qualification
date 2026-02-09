@extends('layouts.application-form-layout')

@section('title', 'General Work Policy | DOT Driver Qualification')

@section('content')
    <div class="min-h-screen bg-gray-50 flex flex-col items-center p-4 md:p-8">
        <div class="w-full max-w-4xl lg:max-w-5xl xl:max-w-6xl mx-auto">
            <!-- Header Section -->
            <div class="mb-8 md:mb-12 bg-blue-950 p-4 rounded-lg flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white mb-1">
                    {{ $company->company_name }}
                </h3>
                <p class="text-gray-200 text-sm">
                    © {{ now()->year }} {{ url('/') }}
                </p>
            </div>

            <!-- Main Card -->
            <div class="p-4 mx-auto max-w-7xl">
                <div class="mb-6">
                    <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                        @if ($isEditMode)
                            Edit Company's General Work Policy
                        @else
                            Company's General Work Policy
                        @endif
                    </h1>
                    <p class="text-gray-600 dark:text-gray-400">
                        @if ($isEditMode)
                            Update acknowledgment of company's general work policy (Step 10 of 10)
                        @else
                            Employee acknowledgment regarding company's general work policy (Step 10 of 10)
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
                            <div
                                class="mb-6 p-4 rounded-lg bg-red-50 border border-red-200 dark:bg-red-900/20 dark:border-red-800">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z"
                                            clip-rule="evenodd" />
                                    </svg>
                                    <h3 class="text-red-800 dark:text-red-200 font-medium">Please fix the following errors:
                                    </h3>
                                </div>
                                <ul class="mt-2 list-disc list-inside text-sm text-red-700 dark:text-red-300">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <form action="{{ route('public.application.store.step10', ['slug' => $company->slug]) }}"
                            method="POST">
                            @csrf
                            <input type="hidden" name="driver_id" value="{{ $driver->id }}">
                            <input type="hidden" name="from_edit" value="{{ $isEditMode ? '1' : '0' }}">

                            <!-- Current Policy Status -->
                            @if ($isEditMode && $driverDocument && $driverDocument->general_work_policy_signature)
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
                                                <span class="font-medium text-blue-600 dark:text-blue-400">Signature
                                                    Date:</span>
                                                <span class="ml-2 text-blue-800 dark:text-blue-200">
                                                    {{ $driverDocument->general_work_policy_date ? \Carbon\Carbon::parse($driverDocument->general_work_policy_date)->format('m/d/Y') : 'N/A' }}
                                                </span>
                                            </div>
                                            <div>
                                                <span class="font-medium text-blue-600 dark:text-blue-400">Signature:</span>
                                                <span class="ml-2 text-blue-800 dark:text-blue-200">
                                                    {{ $driverDocument->general_work_policy_signature ?? 'Not Available' }}
                                                </span>
                                            </div>
                                            <div>
                                                <span class="font-medium text-blue-600 dark:text-blue-400">Policy:</span>
                                                <span class="ml-2 text-blue-800 dark:text-blue-200">
                                                    Company's General Work Policy
                                                </span>
                                            </div>
                                        </div>
                                        <p class="mt-4 text-sm text-blue-700 dark:text-blue-300">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            Updating this form will create a new policy acknowledgment.
                                        </p>
                                    </div>
                                </div>
                            @endif

                            <!-- Consent Form Section -->
                            <div
                                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                    <div class="flex justify-between items-center">
                                        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                            Company's General Work Policy Acknowledgment
                                        </h3>
                                        @if ($isEditMode)
                                            <span
                                                class="px-3 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                Edit Mode - Step 10 of 10
                                            </span>
                                        @endif
                                    </div>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <div class="mb-8">
                                        <!-- Main Instructions -->
                                        <div class="p-6 border border-blue-200 rounded-lg dark:border-blue-800 mb-6">
                                            <p class="text-lg text-gray-800 dark:text-white/90 mb-4">
                                                Kindly explore the key company's general work policy. After completing it,
                                                sign
                                                electronically to acknowledge the receipt, comprehension, and agreement.
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                A copy of the policy will be sent to you via email as soon as you have
                                                finished the
                                                application online. You can also save a copy of the policy right now by
                                                hitting the
                                                Download icon in the viewer's top right corner.
                                            </p>
                                        </div>

                                        <!-- PDF Viewer Section -->
                                        <div class="mb-6">
                                            <div class="flex justify-between items-center mb-4">
                                                <h4 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                                    Company's General Work Policy Document
                                                </h4>
                                                @if ($policyPdf && $policyPdf->general_work_policy_pdf)
                                                    <div class="flex gap-2">
                                                        <a href="{{ asset('storage/' . $policyPdf->general_work_policy_pdf) }}"
                                                            target="_blank"
                                                            class="inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                                            <i class="fas fa-download mr-2"></i>
                                                            Download PDF
                                                        </a>
                                                    </div>
                                                @endif
                                            </div>

                                            @if ($policyPdf && $policyPdf->general_work_policy_pdf)
                                                <div
                                                    class="border border-gray-300 rounded-lg dark:border-gray-700 overflow-hidden">
                                                    <iframe
                                                        src="{{ asset('storage/' . $policyPdf->general_work_policy_pdf) }}"
                                                        width="100%" height="600px" style="border: none;"
                                                        title="Company's General Work Policy PDF">
                                                    </iframe>
                                                </div>

                                                <div class="flex justify-between items-center mt-3">
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        Need help? Call {{ $authUser->company->phone ?? 'N/A' }} or email
                                                        {{ $authUser->email ?? 'N/A' }}
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                                        Last updated:
                                                        {{ $policyPdf->updated_at ? \Carbon\Carbon::parse($policyPdf->updated_at)->format('m/d/Y') : 'N/A' }}
                                                    </p>
                                                </div>
                                            @else
                                                <div
                                                    class="p-8 text-center border-2 border-dashed border-gray-300 rounded-lg dark:border-gray-700">
                                                    <i
                                                        class="fas fa-file-pdf text-4xl text-gray-400 dark:text-gray-500 mb-4"></i>
                                                    <h4 class="text-lg font-medium text-gray-700 dark:text-gray-300 mb-2">
                                                        Policy Document Not Available
                                                    </h4>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        The Company's General Work Policy PDF has not been uploaded yet.
                                                    </p>
                                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                                                        Please contact your administrator at
                                                        {{ $authUser->email ?? 'N/A' }}
                                                    </p>
                                                </div>
                                            @endif

                                            <div
                                                class="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/10 dark:border-yellow-800">
                                                <div class="flex items-start">
                                                    <i
                                                        class="fas fa-exclamation-triangle text-yellow-600 dark:text-yellow-400 mt-0.5 mr-3"></i>
                                                    <p class="text-sm text-yellow-800 dark:text-yellow-300">
                                                        <strong>Important:</strong> I have read, fully understand and agree
                                                        to all
                                                        terms as set forth in the company general work policy. My signature
                                                        below
                                                        confirms my acknowledgment and agreement.
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
                                                    class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                                                    Employee Signature <span class="text-red-500">*</span>
                                                </label>
                                                <input type="text" name="employee_signature" id="employee_signature"
                                                    value="{{ old('employee_signature', $driverDocument->general_work_policy_signature ?? '') }}"
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
                                                    value="{{ old('date_signed', $driverDocument->general_work_policy_date ? \Carbon\Carbon::parse($driverDocument->general_work_policy_date)->format('Y-m-d') : date('Y-m-d')) }}"
                                                    class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                                    @if (!$isEditMode) required @endif>
                                            </div>
                                        </div>
                                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            By signing, you acknowledge that you have read and understood the Company's
                                            General
                                            Work Policy.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Policy Information -->
                            <div
                                class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                                <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                                    <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                        About the Company's General Work Policy
                                    </h3>
                                </div>
                                <div class="p-5 sm:p-6">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-2">
                                                <i class="fas fa-briefcase mr-2"></i>Work Requirements
                                            </h4>
                                            <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                                <li>• Work hours and schedules</li>
                                                <li>• Attendance and punctuality</li>
                                                <li>• Dress code and appearance</li>
                                                <li>• Workplace conduct</li>
                                                <li>• Performance expectations</li>
                                            </ul>
                                        </div>
                                        <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                            <h4 class="text-sm font-semibold text-gray-800 dark:text-white/90 mb-2">
                                                <i class="fas fa-users mr-2"></i>Employee Relations
                                            </h4>
                                            <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
                                                <li>• Communication guidelines</li>
                                                <li>• Teamwork and collaboration</li>
                                                <li>• Conflict resolution</li>
                                                <li>• Professional development</li>
                                                <li>• Code of ethics</li>
                                            </ul>
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
                                    <a href="{{ route('public.application.step9', ['driver_id' => $driver->id, 'slug' => $company->slug]) }}"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Step 9
                                    </a>
                                @endif

                                <div class="flex gap-4">
                                    <button type="submit" name="action" value="save"
                                        class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-white bg-brand-500 border border-transparent rounded-lg shadow-theme-xs hover:bg-brand-600 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500">
                                        <i class="fas fa-save mr-2"></i>
                                        {{ $isEditMode ? 'Update & Complete Application' : 'Save & Complete Application' }}
                                    </button>

                                    @if ($isEditMode)
                                        <a href="{{ route('admin.driver.edit', ['id' => $driver_id]) }}"
                                            class="inline-flex items-center justify-center px-4 py-3 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg shadow-theme-xs hover:bg-gray-50 focus:outline-hidden focus:ring-2 focus:ring-offset-2 focus:ring-brand-500 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                                            Skip to Completion
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
                            'driver_id' => $driver->id,
                        ])

                        <!-- Driver Info Card -->
                        <div
                            class="mt-6 rounded-lg border border-gray-200 dark:border-gray-800 bg-white dark:bg-white/[0.03] p-4">
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
                                    <span
                                        class="font-medium text-gray-800 dark:text-white/90">{{ $driver->main_phone }}</span>
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
                                @if ($driverDocument && $driverDocument->general_work_policy_signature)
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Policy Status:</span>
                                        <span class="font-medium text-green-600 dark:text-green-400">
                                            <i class="fas fa-check-circle mr-1"></i> Acknowledged
                                        </span>
                                    </div>
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Date Acknowledged:</span>
                                        <span class="font-medium text-gray-800 dark:text-white/90">
                                            {{ $driverDocument->general_work_policy_date ? \Carbon\Carbon::parse($driverDocument->general_work_policy_date)->format('m/d/Y') : 'N/A' }}
                                        </span>
                                    </div>
                                @else
                                    <div class="flex items-center text-sm">
                                        <span class="w-24 text-gray-500 dark:text-gray-400">Policy Status:</span>
                                        <span class="font-medium text-red-600 dark:text-red-400">
                                            <i class="fas fa-times-circle mr-1"></i> Not Acknowledged
                                        </span>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <!-- Policy Information Card -->
                        <div
                            class="mt-6 rounded-lg border border-green-200 bg-green-50 dark:border-green-800 dark:bg-green-900/10 p-4">
                            <h3 class="font-medium text-green-800 dark:text-green-200 mb-3">
                                <i class="fas fa-file-contract mr-2"></i>Policy Details
                            </h3>
                            <ul class="space-y-2 text-sm text-green-700 dark:text-green-300">
                                <li class="flex items-start">
                                    <i class="fas fa-user-check mr-2 mt-0.5"></i>
                                    <span>Mandatory for Employment</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-briefcase mr-2 mt-0.5"></i>
                                    <span>Workplace Guidelines</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-clock mr-2 mt-0.5"></i>
                                    <span>Annual Review Required</span>
                                </li>
                                <li class="flex items-start">
                                    <i class="fas fa-balance-scale mr-2 mt-0.5"></i>
                                    <span>Professional Conduct</span>
                                </li>
                            </ul>
                        </div>
                    </div>
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
                {{ $driverDocument && $driverDocument->general_work_policy_signature ? 'true' : 'false' }};
            const hasPolicyPDF = {{ $policyPdf && $policyPdf->general_work_policy_pdf ? 'true' : 'false' }};

            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    // Only validate required fields in create mode
                    if (!isEditMode) {
                        const signature = document.getElementById('employee_signature');
                        const dateSigned = document.getElementById('date_signed');

                        // Validate signature
                        if (!signature.value.trim()) {
                            e.preventDefault();
                            alert('Please provide your signature to acknowledge the policy.');
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

                        // Check if PDF is available (only warn in create mode)
                        if (!hasPolicyPDF) {
                            const continueWithoutPDF = confirm(
                                'The Company\'s General Work Policy PDF is not available. You may still proceed, but you should request a copy from your administrator. Do you wish to continue?'
                            );
                            if (!continueWithoutPDF) {
                                e.preventDefault();
                                return false;
                            }
                        }
                    }

                    // Confirmation dialog with different messages for create vs edit
                    let confirmationMessage = '';
                    if (isEditMode && hasExistingPolicyAck) {
                        confirmationMessage =
                            `You are updating an existing Company's General Work Policy acknowledgment. This will create a new acknowledgment record. Do you wish to proceed?`;
                    } else {
                        confirmationMessage =
                            `You are acknowledging that you have read and understood the Company's General Work Policy for ${employerName}. Do you wish to proceed?`;
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
                {{ $driverDocument && $driverDocument->general_work_policy_date ? 'true' : 'false' }};

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
            const policyContent = document.querySelector('.rounded-2xl.border.border-gray-200');

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

            // Display driver name in console (for debugging)
            const driverName = "{{ $driver->first_name }} {{ $driver->last_name }}";
            console.log('Driver name loaded:', driverName);
        });
    </script>
@endpush
