@extends('layouts.main-layout')

@section('title', 'PSP Driver Disclosure & Authorization')

@section('content')
    <div class="p-4 mx-auto max-w-7xl">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-800 dark:text-white/90">PSP Driver Disclosure & Authorization</h1>
            <p class="text-gray-600 dark:text-gray-400">Authorization for background reports from the PSP Online Service</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-12 gap-4">
            <div class="md:col-span-9">
                <form action="{{ route('admin.driver.psp.store') }}" method="POST">
                    @csrf
                    <input type="hidden" name="driver_id" value="{{ $driver_id }}">

                    <!-- Important Disclosure Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                IMPORTANT DISCLOSURE REGARDING BACKGROUND REPORTS FROM THE PSP ONLINE SERVICE
                            </h3>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-6 space-y-4">
                                <p class="text-sm text-gray-700 dark:text-gray-300">
                                    In connection with your application for employment with <strong>SKYROS LOGISTICS INC
                                        ("Prospective Employer")</strong>, Prospective Employer, its employees, agents or
                                    contractors may obtain one or more reports regarding your driving, and safety inspection
                                    history from the Federal Motor Carrier Safety Administration (FMCSA).
                                </p>

                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <h4 class="font-semibold text-gray-800 dark:text-white/90 mb-2">In-Person Applications:
                                    </h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        When the application for employment is submitted in person, if the Prospective
                                        Employer uses any information it obtains from FMCSA in a decision to not hire you or
                                        to make any other adverse employment decision regarding you, the Prospective
                                        Employer will provide you with a copy of the report upon which its decision was
                                        based and a written summary of your rights under the Fair Credit Reporting Act
                                        before taking any final adverse action. If any final adverse action is taken against
                                        you based upon your driving history or safety report, the Prospective Employer will
                                        notify you that the action has been taken and that the action was based in part or
                                        in whole on this report.
                                    </p>
                                </div>

                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <h4 class="font-semibold text-gray-800 dark:text-white/90 mb-2">Remote Applications:
                                    </h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        When the application for employment is submitted by mail, telephone, computer, or
                                        other similar means, if the Prospective Employer uses any information it obtains
                                        from FMCSA in a decision to not hire you or to make any other adverse employment
                                        decision regarding you, the Prospective Employer must provide you within three
                                        business days of taking adverse action oral, written or electronic notification;
                                        that adverse action has been taken based in whole or in part on information obtained
                                        from FMCSA; the name, address, and the toll free telephone number of FMCSA; that the
                                        FMCSA did not make the decision to take the adverse action and is unable to provide
                                        you the specific reasons why the adverse action was taken; and that you may, upon
                                        providing proper identification, request a free copy of the report and may dispute
                                        with the FMCSA the accuracy or completeness of any information or report. If you
                                        request a copy of a driver record from the Prospective Employer who procured the
                                        report, then, within 3 business days of receiving your request, together with proper
                                        identification, the Prospective Employer must send or provide to you a copy of your
                                        report and a summary of your rights under the Fair Credit Reporting Act.
                                    </p>
                                </div>

                                <div
                                    class="p-4 bg-yellow-50 border border-yellow-200 rounded-lg dark:bg-yellow-900/10 dark:border-yellow-800">
                                    <h4 class="font-semibold text-gray-800 dark:text-white/90 mb-2">Important Information
                                        About Data Accuracy:</h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300 mb-3">
                                        <strong>Neither the Prospective Employer nor the FMCSA contractor supplying the
                                            crash and safety information has the capability to correct any safety data that
                                            appears to be incorrect.</strong> You may challenge the accuracy of the data by
                                        submitting a request to https://dataqs.fmcsa.dot.gov/. If you challenge crash or
                                        inspection information reported by a State, FMCSA cannot change or correct this
                                        data. Your request will be forwarded by the DataQs system to the appropriate State
                                        for adjudication.
                                    </p>
                                </div>

                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <h4 class="font-semibold text-gray-800 dark:text-white/90 mb-2">About Your PSP Report:
                                    </h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Any crash or inspection in which you were involved will display on your PSP report.
                                        Since the PSP report does not report or assign, or imply fault, it will include all
                                        Commercial Motor Vehicle (CMV) crashes where you were a driver or co-driver and
                                        where those crashes were reported to FMCSA, regardless of fault. Similarly, all
                                        inspections, with or without violations, appear on the PSP report. State citations
                                        associated with Federal Motor Carrier Safety Regulations (FMCSR) violations that
                                        have been adjudicated by a court of law will also appear, and remain, on a PSP
                                        report.
                                    </p>
                                </div>

                                <div
                                    class="p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900/10 dark:border-blue-800">
                                    <h4 class="font-semibold text-gray-800 dark:text-white/90 mb-2">Your Authorization
                                        Required:</h4>
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        <strong>The Prospective Employer cannot obtain background reports from FMCSA without
                                            your authorization.</strong>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Authorization Section -->
                    <div class="rounded-2xl border border-gray-200 bg-white dark:border-gray-800 dark:bg-white/[0.03] mb-6">
                        <div class="px-5 py-4 sm:px-6 sm:py-5 border-b border-gray-100 dark:border-gray-800">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                                AUTHORIZATION
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                If you agree that the Prospective Employer may obtain such background reports, please read
                                the following and sign below:
                            </p>
                        </div>
                        <div class="p-5 sm:p-6">
                            <div class="mb-6 space-y-4">
                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        I authorize <strong>SKYROS LOGISTICS INC ("Prospective Employer")</strong> to access
                                        the FMCSA Pre-Employment Screening Program (PSP) system to seek information
                                        regarding my commercial driving safety record and information regarding my safety
                                        inspection history. I understand that I am authorizing the release of safety
                                        performance information including crash data from the previous five (5) years and
                                        inspection history from the previous three (3) years. I understand and acknowledge
                                        that this release of information may assist the Prospective Employer to make a
                                        determination regarding my suitability as an employee.
                                    </p>
                                </div>

                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        I further understand that neither the Prospective Employer nor the FMCSA contractor
                                        supplying the crash and safety information has the capability to correct any safety
                                        data that appears to be incorrect. I understand I may challenge the accuracy of the
                                        data by submitting a request to https://dataqs.fmcsa.dot.gov/. If I challenge crash
                                        or inspection information reported by a State, FMCSA cannot change or correct this
                                        data. I understand my request will be forwarded by the DataQs system to the
                                        appropriate State for adjudication.
                                    </p>
                                </div>

                                <div class="p-4 bg-gray-50 rounded-lg dark:bg-gray-900/20">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        I understand that any crash or inspection in which I was involved will display on my
                                        PSP report. Since the PSP report does not report, or assign, or imply fault, I
                                        acknowledge it will include all CMV crashes where I was a driver or co-driver and
                                        where those crashes were reported to FMCSA, regardless of fault. Similarly, I
                                        understand all inspections, with or without violations, will appear on my PSP
                                        report, and State citations associated with FMCSR violations that have been
                                        adjudicated by a court of law will also appear, and remain, on our PSP report. I
                                        have read the above Disclosure Regarding Background Reports provided to me by
                                        Prospective Employer and I understand that if I sign this Disclosure and
                                        Authorization, Prospective Employer may obtain a report of my crash and inspection
                                        history, I hereby authorize Prospective Employer and its employees, authorized
                                        agents, and/or affiliates to obtain the information authorized above.
                                    </p>
                                </div>
                            </div>

                            <!-- Agreement Checkbox -->
                            <div class="mb-6">
                                <label class="inline-flex items-center cursor-pointer select-none">
                                    <input type="checkbox" name="authorization_agreement" value="1" required
                                        class="text-brand-500 focus:ring-brand-500/20 dark:focus:ring-brand-800/50 h-4 w-4 border-gray-300 rounded focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900" />
                                    <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                        I have read and understand the above disclosure and authorization statements.
                                    </span>
                                </label>
                            </div>

                            <!-- Signature Section -->
                            <div class="mt-6">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="applicant_signature"
                                            class="block text-sm font-medium text-gray-700 dark:text-gray-400 mb-2">
                                            Applicant Signature <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="applicant_signature" id="applicant_signature"
                                            value="{{ old('applicant_signature') }}"
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

                    <!-- Important Notice -->
                    <div class="rounded-2xl border border-red-200 bg-red-50 dark:border-red-800 dark:bg-red-900/10 mb-6">
                        <div class="p-5 sm:p-6">
                            <div class="flex items-start">
                                <svg class="w-5 h-5 text-red-600 dark:text-red-400 mt-0.5 mr-3 flex-shrink-0"
                                    fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z"
                                        clip-rule="evenodd" />
                                </svg>
                                <div>
                                    <h4 class="text-sm font-semibold text-red-800 dark:text-red-300">IMPORTANT NOTICE:</h4>
                                    <p class="text-sm text-red-700 dark:text-red-400 mt-1">
                                        This form is made available to monthly account holders by NIC on behalf of the U.S.
                                        Department of Transportation, Federal Motor Carrier Safety Administration (FMCSA).
                                        Account holders are required by federal law to obtain an Applicant's written or
                                        electronic consent prior to accessing the Applicant's PSP report. Further, account
                                        holders are required by FMCSA to use the language contained in this Disclosure and
                                        Authorization form to obtain an Applicant's consent. The language must be used in
                                        whole, exactly as provided. Further, the language on this form must exist as one
                                        stand-alone document. The language may NOT be included with other consent forms or
                                        any other language.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div
                        class="flex flex-col sm:flex-row gap-4 justify-between pt-6 border-t border-gray-200 dark:border-gray-800">
                        <a href="{{ route('admin.driver.alcohol.and.drug.test', $driver_id) }}"
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
                    const agreement = document.querySelector('input[name="authorization_agreement"]');
                    const signature = document.getElementById('applicant_signature');
                    const dateSigned = document.getElementById('date_signed');

                    // Validate agreement checkbox
                    if (!agreement.checked) {
                        e.preventDefault();
                        alert('You must agree to the authorization terms by checking the box.');
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

                    // Confirm authorization
                    const confirmation = confirm(
                        'By submitting this form, you authorize SKYROS LOGISTICS INC to access your PSP report from FMCSA. Do you wish to proceed?'
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

            // Track reading of disclosure
            const disclosureSection = document.querySelector('.rounded-2xl.border');
            if (disclosureSection) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            // User has seen the disclosure section
                            console.log('User has viewed the disclosure section');
                        }
                    });
                }, {
                    threshold: 0.5
                });

                observer.observe(disclosureSection);
            }
        });
    </script>
@endpush
