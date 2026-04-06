@extends('layouts.application-form-layout')

@section('title', 'Driver\'s Application')

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
                <!-- Title Section -->
                <div class="text-center mb-8 md:mb-12">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Driver's Application</h2>
                    <p class="text-gray-600 text-lg md:text-xl">
                        New applicant or return to an existing application?
                    </p>
                </div>

                <!-- Buttons Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-10 md:mb-14">
                    <!-- Start New Application Button -->
                    <button id="startNewBtn"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-5 md:py-6 px-4 rounded-xl transition duration-300 transform hover:scale-[1.02] shadow-md hover:shadow-lg flex flex-col items-center justify-center gap-2 md:gap-3 group">
                        <div class="text-3xl md:text-4xl mb-1">
                            <i class="fas fa-file-circle-plus group-hover:rotate-12 transition-transform duration-300"></i>
                        </div>
                        <span class="text-xl md:text-2xl">Start New Application</span>
                        <span class="text-sm md:text-base opacity-90">Begin a new driver application</span>
                    </button>

                    <!-- Return to Application Button -->
                    <button id="returnToBtn"
                        class="bg-green-600 hover:bg-green-700 text-white font-bold py-5 md:py-6 px-4 rounded-xl transition duration-300 transform hover:scale-[1.02] shadow-md hover:shadow-lg flex flex-col items-center justify-center gap-2 md:gap-3 group">
                        <div class="text-3xl md:text-4xl mb-1">
                            <i class="fas fa-rotate-left group-hover:-rotate-12 transition-transform duration-300"></i>
                        </div>
                        <span class="text-xl md:text-2xl">Return to Application</span>
                        <span class="text-sm md:text-base opacity-90">Continue your saved application</span>
                    </button>
                </div>

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
        // Button click handlers
        var currentSlug = '{{ request()->route('slug') ?? '' }}';

        document.getElementById('startNewBtn').addEventListener('click', function() {
            // Check if slug is available
            if (!currentSlug) {
                Swal.fire({
                    title: 'Error',
                    text: 'Unable to determine company. Please contact support.',
                    icon: 'error',
                    confirmButtonColor: '#2563eb',
                });
                return;
            }

            Swal.fire({
                title: 'Start New Application',
                text: 'You are about to start a new driver application. All required documents will be needed.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#2563eb',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Continue',
                cancelButtonText: 'Cancel',
                background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Redirect to new application form with slug
                    window.location.href = "{{ route('public.application.start', ['slug' => ':slug']) }}".replace(
                        ':slug', currentSlug);
                }
            });
        });

        document.getElementById('returnToBtn').addEventListener('click', function() {
            Swal.fire({
                title: 'Return to Application',
                html: `
                    <div class="text-left mt-4 mb-2">
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">Phone Number</label>
                        <div class="relative mt-1 group">
                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                <i class="fas fa-phone text-gray-400 group-focus-within:text-brand-500 transition-colors duration-200"></i>
                            </div>
                            <input type="tel" id="swal-phone-input" 
                                class="block w-full pl-11 pr-4 py-3.5 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white dark:focus:bg-gray-900 transition-all duration-200 ease-in-out sm:text-base shadow-sm" 
                                placeholder="(555) 555-5555" maxlength="14" autocomplete="tel">
                        </div>
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 leading-relaxed">Enter the phone number associated with your uncompleted application to seamlessly pick up where you left off.</p>
                    </div>
                `,
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Retrieve Application',
                cancelButtonText: 'Cancel',
                background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                didOpen: () => {
                    const phoneInput = document.getElementById('swal-phone-input');
                    phoneInput.addEventListener('input', function (e) {
                        var x = e.target.value.replace(/\D/g, '').match(/(\d{0,3})(\d{0,3})(\d{0,4})/);
                        e.target.value = !x[2] ? x[1] : '(' + x[1] + ') ' + x[2] + (x[3] ? '-' + x[3] : '');
                    });
                },
                preConfirm: () => {
                    const phone = document.getElementById('swal-phone-input').value;
                    if (!phone || phone.length < 14) {
                        Swal.showValidationMessage('Please enter a valid complete US phone number');
                        return false;
                    }
                    
                    return fetch(`/${currentSlug}/application/check-resume`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({ phone: phone })
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error(response.statusText)
                        }
                        return response.json()
                    })
                    .then(data => {
                        if (!data.success) {
                            Swal.showValidationMessage(data.message || 'No application found with this phone number.');
                            return false;
                        }
                        return data;
                    })
                    .catch(error => {
                        Swal.showValidationMessage(`Request failed: ${error}`);
                    });
                },
                allowOutsideClick: () => !Swal.isLoading()
            }).then((result) => {
                if (result.isConfirmed && result.value.success && result.value.requires_otp) {
                    const phoneVal = result.value.phone;
                    Swal.fire({
                        title: 'Verify Your Identity',
                        html: `
                            <div class="text-left mt-4 mb-2">
                                <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-2">6-Digit SMS Code</label>
                                <input type="text" id="swal-otp-input" class="block w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl bg-gray-50 dark:bg-gray-800 text-center text-2xl tracking-[0.5em] font-medium text-gray-900 dark:text-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:border-brand-500 focus:bg-white dark:focus:bg-gray-900 transition-all duration-200 ease-in-out shadow-sm" placeholder="••••••" maxlength="6" autocomplete="one-time-code">
                                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400 text-center leading-relaxed">We've just sent a secure verification code to <span class="font-semibold text-gray-700 dark:text-gray-300">${document.getElementById('swal-phone-input').value}</span></p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#16a34a',
                        cancelButtonColor: '#6b7280',
                        confirmButtonText: 'Verify Code',
                        cancelButtonText: 'Cancel',
                        background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                        color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                        didOpen: () => {
                            // Only allow numbers
                            const otpInput = document.getElementById('swal-otp-input');
                            otpInput.addEventListener('input', function(e) {
                                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                            });
                        },
                        preConfirm: () => {
                            const otp = document.getElementById('swal-otp-input').value;
                            if (!otp || otp.length !== 6) {
                                Swal.showValidationMessage('Please enter the full 6-digit verification code sent to your phone');
                                return false;
                            }
                            
                            return fetch(`/${currentSlug}/application/check-resume-otp`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                                },
                                body: JSON.stringify({ phone: phoneVal, otp: otp })
                            })
                            .then(response => {
                                if (!response.ok) throw new Error(response.statusText);
                                return response.json();
                            })
                            .then(data => {
                                if (!data.success) {
                                    Swal.showValidationMessage(data.message || 'Invalid verification code');
                                    return false;
                                }
                                return data;
                            })
                            .catch(error => {
                                Swal.showValidationMessage(`Request failed: ${error}`);
                            });
                        },
                        allowOutsideClick: () => !Swal.isLoading()
                    }).then((otpResult) => {
                        if (otpResult.isConfirmed && otpResult.value.success) {
                            Swal.fire({
                                title: 'Identity Verified!',
                                text: 'Resuming your application...',
                                icon: 'success',
                                showConfirmButton: false,
                                timer: 1500,
                                background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                                color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                                timerProgressBar: true
                            }).then(() => {
                                window.location.href = otpResult.value.redirect;
                            });
                        }
                    });
                }
            });
        });

        // Add keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + N for new application
            if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
                e.preventDefault();
                document.getElementById('startNewBtn').click();
            }
            // Ctrl/Cmd + R for return to application
            if ((e.ctrlKey || e.metaKey) && e.key === 'r') {
                e.preventDefault();
                document.getElementById('returnToBtn').click();
            }
        });
    </script>
@endpush
