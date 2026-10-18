@extends('layouts.application-form-layout')

@section('title', 'Verify OTP | DOT Driver Qualification')
@section('themed', '1')

@section('content')
    <x-application.entry :company="$company" icon="smartphone" heading="Verify Your Phone" intro="Enter the verification code sent to your phone to continue your application.">
        <div class="mb-6 grid justify-items-center gap-2 text-center">
            <x-ui.badge>{{ $phone }}</x-ui.badge>
            <p class="text-sm text-muted-foreground">
                OTP sent successfully. Code expires in <span id="timer" class="font-semibold text-primary">5:00</span> minutes
            </p>
        </div>

        <form id="otpVerificationForm" action="{{ route('public.application.submit.otp', $company->slug) }}" method="POST" class="grid gap-6">
            @csrf

            <fieldset class="grid gap-3">
                <legend class="label mb-3 w-full justify-center text-center">Enter 6-digit verification code <span class="text-destructive" aria-hidden="true">*</span></legend>

                <div class="flex justify-center gap-2" id="otp-container">
                    @for ($i = 1; $i <= 6; $i++)
                        <input type="text" maxlength="1" data-index="{{ $i }}" aria-label="Digit {{ $i }}"
                            class="otp-digit input size-11 p-0 text-center text-xl font-semibold sm:size-12"
                            autocomplete="{{ $i === 1 ? 'one-time-code' : 'off' }}" inputmode="numeric" pattern="[0-9]*" />
                    @endfor
                </div>

                <input type="hidden" name="otp" id="otp-input">

                <p id="otp-error" role="alert" class="hidden text-center text-sm text-destructive"></p>

                @error('otp')
                    <p role="alert" class="text-center text-sm text-destructive">{{ $message }}</p>
                @enderror

                <x-ui.alert id="validation-summary" tone="danger" title="Please fix the following errors:" class="hidden">
                    <ul id="error-list" class="list-disc space-y-1 pl-5"></ul>
                </x-ui.alert>
            </fieldset>

            <div class="grid justify-items-center gap-3">
                <x-ui.button type="submit" id="verifyBtn" variant="primary" class="w-full sm:w-auto">Verify &amp; Continue</x-ui.button>

                <div class="flex flex-col items-center gap-2 sm:flex-row sm:gap-4">
                    <x-ui.button id="resendBtn" variant="link" disabled>
                        <span id="resendText">Resend OTP (<span id="countdown">60</span>s)</span>
                    </x-ui.button>
                    <x-ui.button :href="route('application.form', $company->slug)" variant="link" icon="arrow-left">Change Phone Number</x-ui.button>
                </div>
            </div>
        </form>
    </x-application.entry>
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
            const timerElement = document.getElementById('timer');
            const validationSummary = document.getElementById('validation-summary');
            const errorList = document.getElementById('error-list');
            const form = document.getElementById('otpVerificationForm');

            const defaultOtpSeconds = 300;
            const initialOtpSeconds = @json(max(0, min(300, (int) ($expiryInfo['seconds_left'] ?? 0))));
            let countdown = 60;
            let otpTimer = initialOtpSeconds;
            let resendCountdownInterval = null;
            let otpTimerInterval = null;
            let otpExpired = otpTimer <= 0;

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
                if (!otpExpired) {
                    otpError.classList.add('hidden');
                    otpError.textContent = '';
                }

                // Remove error styling from all inputs
                if (!otpExpired) {
                    otpInputs.forEach(input => input.removeAttribute('aria-invalid'));
                }

                if (otp.length === 6 && !otpExpired) {
                    // All good
                    verifyBtn.disabled = false;
                    return true;
                } else {
                    // Not complete
                    verifyBtn.disabled = true;
                    return false;
                }
            }

            // Show OTP error
            function showOtpError(message) {
                otpError.textContent = message;
                otpError.classList.remove('hidden');

                // Add error styling to all inputs
                otpInputs.forEach(input => input.setAttribute('aria-invalid', 'true'));
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
                if (resendCountdownInterval) {
                    clearInterval(resendCountdownInterval);
                }

                countdown = 60;
                resendBtn.disabled = true;
                document.getElementById('resendText').innerHTML = 'Resend OTP (<span id="countdown">60</span>s)';

                resendCountdownInterval = setInterval(() => {
                    countdown--;
                    const currentCountdown = document.getElementById('countdown');
                    if (currentCountdown) {
                        currentCountdown.textContent = countdown;
                    }

                    if (countdown <= 0) {
                        clearInterval(resendCountdownInterval);
                        resendCountdownInterval = null;
                        resendBtn.disabled = false;
                        document.getElementById('resendText').innerHTML = 'Resend OTP';
                    }
                }, 1000);
            }

            // Start OTP expiration timer
            function startOtpTimer(seconds = defaultOtpSeconds) {
                if (otpTimerInterval) {
                    clearInterval(otpTimerInterval);
                }

                const parsedSeconds = parseInt(seconds, 10);
                otpTimer = Number.isFinite(parsedSeconds) ? Math.max(0, parsedSeconds) : defaultOtpSeconds;
                otpExpired = otpTimer <= 0;

                timerElement.classList.remove('text-destructive');
                timerElement.classList.add('text-primary');

                function renderTimer() {
                    const minutes = Math.floor(otpTimer / 60);
                    const seconds = otpTimer % 60;
                    timerElement.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;

                    if (otpTimer <= 0) {
                        if (otpTimerInterval) {
                            clearInterval(otpTimerInterval);
                            otpTimerInterval = null;
                        }

                        otpExpired = true;
                        timerElement.textContent = 'Expired';
                        timerElement.classList.remove('text-primary');
                        timerElement.classList.add('text-destructive');

                        // Disable verify button
                        verifyBtn.disabled = true;
                        showOtpError('OTP expired. Please request a new verification code.');
                    }
                }

                renderTimer();

                otpTimerInterval = setInterval(() => {
                    otpTimer--;
                    renderTimer();
                }, 1000);
            }

            // Handle resend OTP
            resendBtn.addEventListener('click', function() {
                if (resendBtn.disabled) return;

                // Show loading state
                const originalText = resendBtn.innerHTML;
                resendBtn.textContent = 'Sending...';
                resendBtn.disabled = true;

                // Send AJAX request to resend OTP
                fetch('{{ route('public.application.resend.otp', $company->slug) }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            phone: @json($phone)
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            otpExpired = false;

                            // Reset OTP inputs
                            otpInputs.forEach(input => {
                                input.value = '';
                            });
                            updateHiddenOtp();
                            otpInputs[0].focus();
                            validateOtp();

                            // Reset timers
                            startResendCountdown();
                            startOtpTimer(data.expires_in || defaultOtpSeconds);

                            showAppAlert('A new verification code has been sent to your phone.', 'success', 'OTP Resent');
                        } else {
                            resendBtn.disabled = false;

                            showAppAlert(data.message || 'Failed to resend OTP. Please try again.', 'error', 'Failed to Resend');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        resendBtn.disabled = false;

                        showAppAlert('An error occurred. Please try again.', 'error', 'Error');
                    })
                    .finally(() => {
                        if (countdown > 0 && !resendCountdownInterval) {
                            resendBtn.innerHTML = originalText;
                        }

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
                if (otpExpired) {
                    showOtpError('OTP expired. Please request a new verification code.');
                    errors.push('OTP: OTP expired. Please request a new verification code.');
                } else if (otp.length !== 6) {
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
                verifyBtn.setAttribute('aria-busy', 'true');
                verifyBtn.textContent = 'Verifying...';
                verifyBtn.disabled = true;

                // Submit the form
                form.submit();
            });

            // Initialize
            validateOtp();
            startResendCountdown();
            startOtpTimer(otpTimer);

            // Auto-focus on OTP inputs when page loads
            setTimeout(() => {
                otpInputs[0].focus();
            }, 100);
        });
    </script>

@endpush
