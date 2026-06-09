@extends('layouts.application-form-layout')

@section('title', 'Driver\'s Application | DOT Driver Qualification')

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
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10 border border-gray-200">
                <!-- Driver's Application Form -->
                <div class="mb-8 md:mb-12 border-b border-gray-200 pb-4 flex justify-end">
                    <h3 class="text-4xl md:text-4xl font-bold text-gray-800">
                        Driver's Application
                    </h3>
                </div>

                <!-- Title Section -->
                <div class="text-center mb-8 md:mb-12">
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Start New Application</h3>
                    <p class="text-gray-600 text-lg md:text-xl">
                        Please complete the fields below to register and begin the application.
                    </p>
                </div>

                <form id="applicationForm" action="{{ route('public.application.send.otp', $company->slug) }}"
                    method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-10 md:mb-14">
                        <div>
                            <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Phone <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="phone" name="phone"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                placeholder="+1 (555) 123-4567" value="{{ old('phone') }}" />
                            <div id="phone-error" class="mt-1 text-sm text-red-500 hidden"></div>
                            @error('phone')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="confirm_phone"
                                class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-400">
                                Confirm Phone <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="confirm_phone" name="confirm_phone"
                                class="dark:bg-dark-900 shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 dark:focus:border-brand-800 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden dark:border-gray-700 dark:bg-gray-900 dark:text-white/90 dark:placeholder:text-white/30"
                                placeholder="+1 (555) 123-4567" value="{{ old('confirm_phone') }}" />
                            <div id="confirm-phone-error" class="mt-1 text-sm text-red-500 hidden"></div>
                            @error('confirm_phone')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Validation Summary -->
                    <div id="validation-summary" class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg hidden">
                        <p class="text-red-600 font-medium mb-2">Please fix the following errors:</p>
                        <ul id="error-list" class="text-red-500 text-sm list-disc pl-5"></ul>
                    </div>

                    <!-- Stacked vertical layout -->
                    <div class="flex flex-col items-center justify-center gap-4 mb-5">
                        <button type="submit" id="submitBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-300 w-full md:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                            Start Application
                        </button>

                        <a href="{{ url('/') }}"
                            class="text-blue-600 hover:text-blue-800 transition-colors duration-300 font-medium">
                            Go Back
                        </a>
                    </div>
                </form>

                <!-- Help Section -->
                <div class="text-center pt-6 md:pt-8 border-t border-gray-200">
                    <p class="text-gray-600 text-base md:text-lg mb-3 md:mb-4">Need help?</p>
                    <div class="flex flex-col md:flex-row items-center justify-center gap-3 md:gap-6">
                        <!-- Phone -->
                        <a href="tel:2092776341"
                            class="flex items-center gap-2 text-blue-600 hover:text-blue-800 transition-colors duration-300 group">
                            <div
                                class="bg-blue-100 p-2 md:p-3 rounded-full group-hover:bg-blue-200 transition-colors duration-300">
                                <i class="fas fa-phone text-blue-600 text-sm md:text-base"></i>
                            </div>
                            <span
                                class="font-medium text-base md:text-lg">{{ $company->phone ? $company->phone : '123456789' }}</span>
                        </a>

                        <!-- Divider (hidden on mobile) -->
                        <div class="hidden md:block h-6 w-px bg-gray-300"></div>

                        <!-- Email -->
                        <a href="mailto:{{ $company->email }}"
                            class="flex items-center gap-2 text-blue-600 hover:text-blue-800 transition-colors duration-300 group">
                            <div
                                class="bg-blue-100 p-2 md:p-3 rounded-full group-hover:bg-blue-200 transition-colors duration-300">
                                <i class="fas fa-envelope text-blue-600 text-sm md:text-base"></i>
                            </div>
                            <span class="font-medium text-base md:text-lg">{{ $company->email }}</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Footer Note -->
            <div class="text-center mt-6 md:mt-8">
                <p class="text-gray-500 text-sm md:text-base">
                    Secure application process • Your information is protected
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('applicationForm');
            const phoneInput = document.getElementById('phone');
            const confirmPhoneInput = document.getElementById('confirm_phone');
            const phoneError = document.getElementById('phone-error');
            const confirmPhoneError = document.getElementById('confirm-phone-error');
            const validationSummary = document.getElementById('validation-summary');
            const errorList = document.getElementById('error-list');
            const submitBtn = document.getElementById('submitBtn');

            function digitsOnly(phone) {
                return (phone || '').replace(/\D/g, '');
            }

            function isValidNanpNationalNumber(digits) {
                return /^[2-9]\d{2}[2-9]\d{6}$/.test(digits);
            }

            function normalizePhone(phone) {
                let digits = digitsOnly(phone);

                if (!digits) {
                    return '';
                }

                if (digits.length === 12 && digits.startsWith('11') && isValidNanpNationalNumber(digits.slice(2))) {
                    digits = digits.slice(1);
                }

                if (digits.length === 10 && isValidNanpNationalNumber(digits)) {
                    return '+1' + digits;
                }

                if (digits.length === 11 && digits.startsWith('1')) {
                    return '+' + digits;
                }

                if (digits.length === 11 && digits.startsWith('01')) {
                    return '+880' + digits.slice(1);
                }

                if (digits.length === 13 && digits.startsWith('880')) {
                    return '+' + digits;
                }

                if (digits.length === 12 && digits.startsWith('91')) {
                    return '+' + digits;
                }

                return '+' + digits;
            }

            function isValidPhone(normalized) {
                if (!/^\+[1-9]\d{1,14}$/.test(normalized)) {
                    return false;
                }

                const digits = normalized.slice(1);

                if (digits.startsWith('1')) {
                    return digits.length === 11 && isValidNanpNationalNumber(digits.slice(1));
                }

                if (digits.startsWith('880')) {
                    return /^8801[3-9]\d{8}$/.test(digits);
                }

                if (digits.startsWith('91')) {
                    return /^91[6-9]\d{9}$/.test(digits);
                }

                return normalized.length >= 10 && normalized.length <= 16;
            }

            function formatPhoneNumber(normalized) {
                const digits = normalized.slice(1);

                if (digits.startsWith('1') && digits.length === 11) {
                    const national = digits.slice(1);
                    return `+1 (${national.slice(0, 3)}) ${national.slice(3, 6)}-${national.slice(6)}`;
                }

                if (digits.startsWith('880') && digits.length === 13) {
                    return `+880 ${digits.slice(3, 5)} ${digits.slice(5, 8)}-${digits.slice(8, 11)}-${digits.slice(11)}`;
                }

                if (digits.startsWith('91') && digits.length === 12) {
                    return `+91 ${digits.slice(2, 7)}-${digits.slice(7)}`;
                }

                return normalized;
            }

            function validatePhone(phone) {
                if (!phone.trim()) {
                    return {
                        isValid: false,
                        message: 'Phone number is required'
                    };
                }

                const digits = digitsOnly(phone);
                const hasExplicitCountryPrefix = phone.trim().startsWith('+');

                if (!hasExplicitCountryPrefix) {
                    const isUsNational = digits.length === 10 && isValidNanpNationalNumber(digits);
                    const isUsWithCountryCode = digits.length === 11 && digits.startsWith('1') &&
                        isValidNanpNationalNumber(digits.slice(1));
                    const isBangladeshLocal = digits.length === 11 && digits.startsWith('01');
                    const isBangladeshWithCountryCode = digits.length === 13 && digits.startsWith('880');
                    const isIndiaWithCountryCode = digits.length === 12 && digits.startsWith('91');

                    if (
                        !isUsNational &&
                        !isUsWithCountryCode &&
                        !isBangladeshLocal &&
                        !isBangladeshWithCountryCode &&
                        !isIndiaWithCountryCode
                    ) {
                        return {
                            isValid: false,
                            message: 'Please enter a valid phone number.'
                        };
                    }
                }

                const normalized = normalizePhone(phone);

                if (!isValidPhone(normalized)) {
                    return {
                        isValid: false,
                        message: 'Please enter a valid phone number.'
                    };
                }

                return {
                    isValid: true,
                    normalized: normalized,
                    formatted: formatPhoneNumber(normalized)
                };
            }

            function validatePhoneMatch(phone1, phone2) {
                const phone1Result = validatePhone(phone1);
                const phone2Result = validatePhone(phone2);

                if (!phone1Result.isValid || !phone2Result.isValid) {
                    return {
                        isValid: false,
                        message: 'Enter both phone numbers before confirming.'
                    };
                }

                if (phone1Result.normalized !== phone2Result.normalized) {
                    return {
                        isValid: false,
                        message: 'Phone numbers do not match.'
                    };
                }

                return {
                    isValid: true
                };
            }

            // Show error
            function showError(inputElement, errorElement, message) {
                errorElement.textContent = message;
                errorElement.classList.remove('hidden');
                inputElement.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                inputElement.classList.remove('border-gray-300');
            }

            // Hide error
            function hideError(inputElement, errorElement) {
                errorElement.classList.add('hidden');
                inputElement.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                inputElement.classList.add('border-gray-300');
            }

            // Update validation summary
            function updateValidationSummary(errors) {
                if (errors.length > 0) {
                    validationSummary.classList.remove('hidden');
                    errorList.innerHTML = '';
                    errors.forEach(error => {
                        const li = document.createElement('li');
                        li.textContent = error;
                        errorList.appendChild(li);
                    });
                } else {
                    validationSummary.classList.add('hidden');
                }
            }

            // Real-time validation on input
            phoneInput.addEventListener('input', function() {
                const result = validatePhone(this.value);

                if (!result.isValid) {
                    showError(phoneInput, phoneError, result.message);
                } else {
                    hideError(phoneInput, phoneError);
                }

                // Also validate match if confirm phone is filled
                if (confirmPhoneInput.value.trim()) {
                    const matchResult = validatePhoneMatch(phoneInput.value, confirmPhoneInput.value);
                    if (!matchResult.isValid) {
                        showError(confirmPhoneInput, confirmPhoneError, matchResult.message);
                    } else {
                        hideError(confirmPhoneInput, confirmPhoneError);
                    }
                }

                updateSubmitButton();
            });

            confirmPhoneInput.addEventListener('input', function() {
                const phone1 = phoneInput.value;
                const phone2 = this.value;

                const phone2Result = validatePhone(phone2);
                if (!phone2Result.isValid) {
                    showError(confirmPhoneInput, confirmPhoneError, phone2Result.message);
                } else {
                    // Validate match only if both fields are valid
                    const phone1Result = validatePhone(phone1);
                    if (phone1Result.isValid) {
                        const matchResult = validatePhoneMatch(phone1, phone2);
                        if (!matchResult.isValid) {
                            showError(confirmPhoneInput, confirmPhoneError, matchResult.message);
                        } else {
                            hideError(confirmPhoneInput, confirmPhoneError);
                        }
                    } else {
                        hideError(confirmPhoneInput, confirmPhoneError);
                    }
                }

                updateSubmitButton();
            });


            // Validate form on blur as well
            phoneInput.addEventListener('blur', function() {
                const result = validatePhone(this.value);
                if (!result.isValid) {
                    showError(phoneInput, phoneError, result.message);
                } else {
                    this.value = result.formatted;
                    hideError(phoneInput, phoneError);
                }
                updateSubmitButton();
            });

            confirmPhoneInput.addEventListener('blur', function() {
                const result = validatePhone(this.value);
                if (!result.isValid) {
                    showError(confirmPhoneInput, confirmPhoneError, result.message);
                } else {
                    this.value = result.formatted;
                    const matchResult = validatePhoneMatch(phoneInput.value, this.value);
                    if (!matchResult.isValid) {
                        showError(confirmPhoneInput, confirmPhoneError, matchResult.message);
                    } else {
                        hideError(confirmPhoneInput, confirmPhoneError);
                    }
                }
                updateSubmitButton();
            });

            // Update submit button state
            function updateSubmitButton() {
                const phone1Valid = validatePhone(phoneInput.value).isValid;
                const phone2Valid = validatePhone(confirmPhoneInput.value).isValid;
                const phonesMatch = validatePhoneMatch(phoneInput.value, confirmPhoneInput.value).isValid;

                const isValid = phone1Valid && phone2Valid && phonesMatch;

                submitBtn.disabled = !isValid;
                submitBtn.classList.toggle('opacity-50', !isValid);
                submitBtn.classList.toggle('cursor-not-allowed', !isValid);
            }

            // Form submission handler
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                // Collect all validation errors
                const errors = [];
                const phone1Result = validatePhone(phoneInput.value);
                const phone2Result = validatePhone(confirmPhoneInput.value);
                const matchResult = validatePhoneMatch(phoneInput.value, confirmPhoneInput.value);

                if (!phone1Result.isValid) {
                    showError(phoneInput, phoneError, phone1Result.message);
                    errors.push('Phone: ' + phone1Result.message);
                }

                if (!phone2Result.isValid) {
                    showError(confirmPhoneInput, confirmPhoneError, phone2Result.message);
                    errors.push('Confirm Phone: ' + phone2Result.message);
                } else if (!matchResult.isValid) {
                    showError(confirmPhoneInput, confirmPhoneError, matchResult.message);
                    errors.push('Confirm Phone: ' + matchResult.message);
                }

                if (errors.length > 0) {
                    updateValidationSummary(errors);

                    // Scroll to first error
                    const firstErrorField = document.querySelector('.border-red-500');
                    if (firstErrorField) {
                        firstErrorField.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }

                    // Show error notification
                    if (typeof Swal !== 'undefined') {
                        Swal.fire({
                            title: 'Validation Error',
                            html: 'Please fix the errors in the form before submitting.',
                            icon: 'error',
                            confirmButtonColor: '#2563eb',
                            background: document.documentElement.classList.contains('dark') ?
                                '#1f2937' : '#ffffff',
                            color: document.documentElement.classList.contains('dark') ? '#ffffff' :
                                '#000000'
                        });
                    }

                    return;
                }

                // If all validations pass, submit the form
                validationSummary.classList.add('hidden');

                // Show loading state
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = `
            <div class="flex items-center justify-center">
                <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                Processing...
            </div>
        `;
                submitBtn.disabled = true;

                const cleanedPhone = phone1Result.normalized;
                const cleanedConfirmPhone = phone2Result.normalized;

                // Update form values with cleaned versions
                phoneInput.value = cleanedPhone;
                confirmPhoneInput.value = cleanedConfirmPhone;

                // Submit the form
                form.submit();
            });

            // Initialize submit button state and show examples
            updateSubmitButton();
            // showPhoneExamples();
        });
    </script>
@endpush
