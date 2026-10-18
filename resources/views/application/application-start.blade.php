@extends('layouts.application-form-layout')

@section('title', 'Driver\'s Application | DOT Driver Qualification')
@section('themed', '1')

@section('content')
    <x-application.entry :company="$company" heading="Start New Application" intro="Please complete the fields below to register and begin the application.">
        <form id="applicationForm" action="{{ route('public.application.send.otp', $company->slug) }}" method="POST" class="grid gap-6">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Phone" name="phone" for="phone" required>
                    <x-ui.input id="phone" name="phone" inputmode="tel" autocomplete="tel" placeholder="+1 (555) 123-4567" />
                    <p id="phone-error" role="alert" class="hidden text-sm text-destructive"></p>
                </x-ui.field>

                <x-ui.field label="Confirm Phone" name="confirm_phone" for="confirm_phone" required>
                    <x-ui.input id="confirm_phone" name="confirm_phone" inputmode="tel" autocomplete="tel" placeholder="+1 (555) 123-4567" />
                    <p id="confirm-phone-error" role="alert" class="hidden text-sm text-destructive"></p>
                </x-ui.field>
            </div>

            <x-ui.alert id="validation-summary" tone="danger" title="Please fix the following errors:" class="hidden">
                <ul id="error-list" class="list-disc space-y-1 pl-5"></ul>
            </x-ui.alert>

            <div class="grid justify-items-center gap-3">
                <x-ui.button type="submit" id="submitBtn" variant="primary" class="w-full sm:w-auto">Start Application</x-ui.button>
                <x-ui.button :href="route('application.form', $company->slug)" variant="link" icon="arrow-left">Go Back</x-ui.button>
            </div>
        </form>
    </x-application.entry>
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
                inputElement.setAttribute('aria-invalid', 'true');
            }

            // Hide error
            function hideError(inputElement, errorElement) {
                errorElement.classList.add('hidden');
                inputElement.removeAttribute('aria-invalid');
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
                    const firstErrorField = form.querySelector('[aria-invalid="true"]');
                    if (firstErrorField) {
                        firstErrorField.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }

                    showAppAlert('Please fix the errors in the form before submitting.', 'error', 'Validation Error');

                    return;
                }

                // If all validations pass, submit the form
                validationSummary.classList.add('hidden');

                // Show loading state
                submitBtn.setAttribute('aria-busy', 'true');
                submitBtn.textContent = 'Processing...';
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
