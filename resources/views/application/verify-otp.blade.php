{{-- resources/views/driver-application/verify-otp.blade.php --}}
@extends('layouts.application-form-layout')

@section('title', 'Verify OTP | DOT Driver Qualification')

@section('content')
    <div class="min-h-screen bg-gray-50 flex flex-col items-center p-4 md:p-8">
        <div class="w-full max-w-4xl mx-auto">
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
                    <h3 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Verify Your Phone</h3>
                    <p class="text-gray-600 text-lg md:text-xl">
                        Enter the verification code sent to your phone to continue your application.
                    </p>
                </div>

                <!-- Phone Display -->
                <div class="text-center mb-6">
                    <div class="inline-flex items-center gap-2 bg-blue-50 px-4 py-2 rounded-full">
                        <i class="fas fa-mobile-alt text-blue-600"></i>
                        <span class="font-medium text-gray-800">{{ $phone }}</span>
                    </div>
                    <p class="text-sm text-gray-500 mt-2">
                        OTP sent successfully. Code expires in <span id="timer"
                            class="font-semibold text-blue-600">10:00</span> minutes
                    </p>
                </div>

                <form id="otpVerificationForm" action="{{ route('application.submit.otp', $company->slug) }}"
                    method="POST">
                    @csrf

                    <!-- OTP Input Fields -->
                    <div class="max-w-md mx-auto mb-10 md:mb-14">
                        <label class="mb-3 block text-sm font-medium text-gray-700 dark:text-gray-400 text-center">
                            Enter 6-digit verification code <span class="text-red-500">*</span>
                        </label>

                        <div class="flex justify-center gap-2 md:gap-3 mb-2" id="otp-container">
                            @for ($i = 1; $i <= 6; $i++)
                                <input type="text" maxlength="1" data-index="{{ $i }}"
                                    class="otp-digit h-12 w-12 md:h-12 md:w-12 text-center text-xl font-bold border-2 border-gray-300 rounded-lg focus:border-blue-500 focus:ring-2 focus:ring-blue-200 transition-all duration-200 dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                    autocomplete="off" inputmode="numeric" pattern="[0-9]*" />
                            @endfor
                        </div>

                        <!-- Hidden field to store the complete OTP -->
                        <input type="hidden" name="otp" id="otp-input">

                        <div id="otp-error" class="mt-2 text-sm text-red-500 text-center hidden"></div>

                        @error('otp')
                            <p class="mt-2 text-sm text-red-500 text-center">{{ $message }}</p>
                        @enderror

                        <!-- Validation Summary -->
                        <div id="validation-summary" class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg hidden">
                            <p class="text-red-600 font-medium mb-2">Please fix the following errors:</p>
                            <ul id="error-list" class="text-red-500 text-sm list-disc pl-5"></ul>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex flex-col items-center justify-center gap-4 mb-5">
                        <button type="submit" id="verifyBtn"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-300 w-full md:w-auto disabled:opacity-50 disabled:cursor-not-allowed">
                            Verify & Continue
                        </button>

                        <div class="flex flex-col md:flex-row gap-4 items-center">
                            <button type="button" id="resendBtn"
                                class="text-blue-600 hover:text-blue-800 transition-colors duration-300 font-medium disabled:text-gray-400 disabled:cursor-not-allowed"
                                disabled>
                                <span id="resendText">Resend OTP (<span id="countdown">60</span>s)</span>
                            </button>

                            <span class="hidden md:inline text-gray-300">|</span>

                            <a href="{{ route('application.form', $company->slug) }}"
                                class="text-gray-600 hover:text-gray-800 transition-colors duration-300 font-medium">
                                <i class="fas fa-arrow-left mr-1"></i> Change Phone Number
                            </a>
                        </div>
                    </div>
                </form>

                <!-- Help Section -->
                <div class="text-center pt-6 md:pt-8 border-t border-gray-200">
                    <p class="text-gray-600 text-base md:text-lg mb-3 md:mb-4">Need help?</p>
                    <div class="flex flex-col md:flex-row items-center justify-center gap-3 md:gap-6">
                        <!-- Phone -->
                        <a href="tel:{{ $company->phone ? $company->phone : '2092776341' }}"
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
                    Secure verification process • Your information is protected
                </p>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const otpContainer = document.getElementById('otp-container');
            const otpInputs = otpContainer.querySelectorAll('.otp-digit');
            const hiddenOtpInput = document.getElementById('otp-input');
            const otpError = document.getElementById('otp-error');
            const verifyBtn = document.getElementById('verifyBtn');
            const resendBtn = document.getElementById('resendBtn');
            const countdownElement = document.getElementById('countdown');
            const timerElement = document.getElementById('timer');
            const validationSummary = document.getElementById('validation-summary');
            const errorList = document.getElementById('error-list');
            const form = document.getElementById('otpVerificationForm');

            let countdown = 60;
            let otpTimer = 600; // 10 minutes in seconds

            // Initialize OTP input handling
            otpInputs.forEach((input, index) => {
                // Focus on first input
                if (index === 0) {
                    input.focus();
                }

                // Handle input
                input.addEventListener('input', function(e) {
                    const value = e.target.value;

                    // Only allow numbers
                    if (!/^\d*$/.test(value)) {
                        e.target.value = '';
                        return;
                    }

                    // If a number is entered, move to next input
                    if (value.length === 1 && index < otpInputs.length - 1) {
                        otpInputs[index + 1].focus();
                    }

                    // Update hidden field
                    updateHiddenOtp();
                    validateOtp();
                });

                // Handle backspace
                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !e.target.value && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });

                // Handle paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = e.clipboardData.getData('text');
                    const numbers = pasteData.replace(/\D/g, '');

                    // Fill OTP inputs with pasted numbers
                    for (let i = 0; i < Math.min(numbers.length, otpInputs.length); i++) {
                        otpInputs[i].value = numbers[i];
                    }

                    // Focus on last filled input or next empty one
                    const lastFilledIndex = Math.min(numbers.length, otpInputs.length) - 1;
                    if (lastFilledIndex < otpInputs.length - 1) {
                        otpInputs[lastFilledIndex + 1].focus();
                    } else {
                        otpInputs[lastFilledIndex].focus();
                    }

                    updateHiddenOtp();
                    validateOtp();
                });
            });

            // Update hidden OTP field
            function updateHiddenOtp() {
                let otp = '';
                otpInputs.forEach(input => {
                    otp += input.value;
                });
                hiddenOtpInput.value = otp;
            }

            // Validate OTP
            function validateOtp() {
                const otp = hiddenOtpInput.value;

                // Clear error
                otpError.classList.add('hidden');
                otpError.textContent = '';

                // Remove error styling from all inputs
                otpInputs.forEach(input => {
                    input.classList.remove('border-red-500', 'ring-1', 'ring-red-500');
                    input.classList.add('border-gray-300');
                });

                if (otp.length === 6) {
                    // All good
                    verifyBtn.disabled = false;
                    verifyBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                    return true;
                } else {
                    // Not complete
                    verifyBtn.disabled = true;
                    verifyBtn.classList.add('opacity-50', 'cursor-not-allowed');
                    return false;
                }
            }

            // Show OTP error
            function showOtpError(message) {
                otpError.textContent = message;
                otpError.classList.remove('hidden');

                // Add error styling to all inputs
                otpInputs.forEach(input => {
                    input.classList.add('border-red-500', 'ring-1', 'ring-red-500');
                    input.classList.remove('border-gray-300');
                });
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

            // Start countdown timer for resend button
            function startResendCountdown() {
                countdown = 60;
                resendBtn.disabled = true;
                resendBtn.classList.add('disabled:text-gray-400', 'disabled:cursor-not-allowed');

                const countdownInterval = setInterval(() => {
                    countdown--;
                    countdownElement.textContent = countdown;

                    if (countdown <= 0) {
                        clearInterval(countdownInterval);
                        resendBtn.disabled = false;
                        resendBtn.classList.remove('disabled:text-gray-400', 'disabled:cursor-not-allowed');
                        document.getElementById('resendText').innerHTML = 'Resend OTP';
                    }
                }, 1000);
            }

            // Start OTP expiration timer
            function startOtpTimer() {
                otpTimer = 600;

                const timerInterval = setInterval(() => {
                    otpTimer--;
                    const minutes = Math.floor(otpTimer / 60);
                    const seconds = otpTimer % 60;
                    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                    if (otpTimer <= 0) {
                        clearInterval(timerInterval);
                        timerElement.textContent = 'Expired!';
                        timerElement.classList.remove('text-blue-600');
                        timerElement.classList.add('text-red-600');

                        // Disable verify button
                        verifyBtn.disabled = true;
                        verifyBtn.classList.add('opacity-50', 'cursor-not-allowed');
                        showOtpError('OTP has expired. Please request a new one.');
                    }
                }, 1000);
            }

            // Handle resend OTP
            resendBtn.addEventListener('click', function() {
                if (resendBtn.disabled) return;

                // Show loading state
                const originalText = resendBtn.innerHTML;
                resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Sending...';
                resendBtn.disabled = true;

                // Send AJAX request to resend OTP
                fetch('{{ route('application.resend.otp', $company->slug) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            phone: '{{ $phone }}'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Reset OTP inputs
                            otpInputs.forEach(input => {
                                input.value = '';
                            });
                            updateHiddenOtp();
                            validateOtp();

                            // Reset timers
                            startResendCountdown();
                            startOtpTimer();

                            // Show success message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'OTP Resent',
                                    text: 'A new verification code has been sent to your phone.',
                                    icon: 'success',
                                    confirmButtonColor: '#2563eb',
                                    timer: 3000
                                });
                            }
                        } else {
                            // Show error message
                            if (typeof Swal !== 'undefined') {
                                Swal.fire({
                                    title: 'Failed to Resend',
                                    text: data.message ||
                                        'Failed to resend OTP. Please try again.',
                                    icon: 'error',
                                    confirmButtonColor: '#2563eb'
                                });
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        if (typeof Swal !== 'undefined') {
                            Swal.fire({
                                title: 'Error',
                                text: 'An error occurred. Please try again.',
                                icon: 'error',
                                confirmButtonColor: '#2563eb'
                            });
                        }
                    })
                    .finally(() => {
                        // Restore button state
                        resendBtn.innerHTML = originalText;
                        if (countdown <= 0) {
                            resendBtn.disabled = false;
                        }
                    });
            });

            // Form submission handler
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const otp = hiddenOtpInput.value;
                const errors = [];

                // Validate OTP
                if (otp.length !== 6) {
                    showOtpError('Please enter the complete 6-digit verification code.');
                    errors.push('OTP: Please enter the complete 6-digit verification code.');
                } else if (!/^\d{6}$/.test(otp)) {
                    showOtpError('OTP must contain only numbers.');
                    errors.push('OTP: OTP must contain only numbers.');
                }

                if (errors.length > 0) {
                    updateValidationSummary(errors);

                    // Scroll to error
                    otpInputs[0].scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });

                    return;
                }

                // Show loading state
                const originalText = verifyBtn.innerHTML;
                verifyBtn.innerHTML = `
                    <div class="flex items-center justify-center">
                        <svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        Verifying...
                    </div>
                `;
                verifyBtn.disabled = true;

                // Submit the form
                form.submit();
            });

            // Initialize
            validateOtp();
            startResendCountdown();
            startOtpTimer();

            // Auto-focus on OTP inputs when page loads
            setTimeout(() => {
                otpInputs[0].focus();
            }, 100);
        });
    </script>

    <style>
        .otp-digit {
            transition: all 0.2s ease;
        }

        .otp-digit:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.1);
            transform: scale(1.05);
        }

        .otp-digit:not(:placeholder-shown) {
            border-color: #3b82f6;
            background-color: #f8fafc;
        }

        /* Hide number input arrows */
        .otp-digit::-webkit-outer-spin-button,
        .otp-digit::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .otp-digit[type=number] {
            -moz-appearance: textfield;
        }
    </style>
@endpush
