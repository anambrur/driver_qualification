@extends('layouts.main-layout')

@section('title', 'Alcohol & Drug Test Policy')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">Alcohol & Drug Test Policy</h1>
            <p class="text-gray-600 dark:text-gray-400">Employee certification regarding drug and alcohol testing history</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
                <form action="{{ route('admin.driver.alcohol.and.drug.test.policy.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">
                    <input type="hidden" name="consent_type" value="multiple_unlimited">

                    <!-- Consent Form Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                Alcohol & Drug Testing Policy
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-8">
                                <!-- Main Consent Statement -->
                                <div class="p-6 border border-blue-200 rounded-lg dark:border-blue-800 mb-6">
                                    <p class="text-lg text-gray-800 dark:text-white/90 mb-4">
                                        Kindly go over the Alcohol and Drug Testing policies for the company below. Once
                                        you've completed, sign
                                        electronically to acknowledge receipt, comprehension, and agreement. A copy of the
                                        policy wil be sent to you
                                        via email as soon as you have finished the application online. You may also save a
                                        copy of the policy right now
                                        by hitiing the Download icon in the viewer's top right corner.
                                    </p>
                                </div>

                                {{-- show pdf here --}}

                                <!-- Replace {{-- show pdf here --}} comment with: -->
                                <div class="mb-6">
                                    <iframe src="{{ asset('storage/'.$policyPdf->alcohol_drug_test_policy_pdf) }}"
                                        width="100%" height="600px"
                                        style="border: 1px solid #e5e7eb; border-radius: 0.5rem;"
                                        title="Alcohol & Drug Testing Policy PDF">
                                    </iframe>

                                    <div class="flex justify-between items-center mt-3">
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            Need help? Call {{ $authUser->company->phone }} or email
                                            {{ $authUser->email }}
                                        </p>

                                    </div>

                                    <p class="">I have read, fully understand and agree to al terms as set forth in
                                        the company
                                        alcohol and drug testing policy.</p>
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
                                            value="{{ old('employee_signature') }}"
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
                                            value="{{ old('date_signed', date('Y-m-d')) }}"
                                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                            required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.psp', $driver_id) }}"
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
                    const agreement = document.querySelector('input[name="consent_agreement"]');
                    const signature = document.getElementById('employee_signature');
                    const dateSigned = document.getElementById('date_signed');

                    // Validate agreement checkbox
                    if (!agreement.checked) {
                        e.preventDefault();
                        alert('You must agree to the consent terms by checking the box.');
                        agreement.focus();
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

                    // Confirmation dialog
                    const confirmation = confirm(
                        'You are consenting to MULTIPLE UNLIMITED queries of the FMCSA Clearinghouse for the duration of your employment. Do you wish to proceed?'
                    );
                    if (!confirmation) {
                        e.preventDefault();
                        return false;
                    }
                });
            }

            // Auto-fill date if not already set
            const dateSignedInput = document.getElementById('date_signed');
            if (dateSignedInput && !dateSignedInput.value) {
                dateSignedInput.value = new Date().toISOString().split('T')[0];
            }

            // Display driver name in consent statement
            const driverName = "{{ $driver_name ?? '' }}";
            console.log('Driver name loaded:', driverName);
        });
    </script>
@endpush
