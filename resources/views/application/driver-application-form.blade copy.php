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
                input: 'text',
                inputLabel: 'Enter your application ID or email',
                inputPlaceholder: 'Application ID / Email',
                showCancelButton: true,
                confirmButtonColor: '#16a34a',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Retrieve Application',
                cancelButtonText: 'Cancel',
                background: document.documentElement.classList.contains('dark') ? '#1f2937' : '#ffffff',
                color: document.documentElement.classList.contains('dark') ? '#ffffff' : '#000000',
                preConfirm: (value) => {
                    if (!value) {
                        Swal.showValidationMessage('Please enter your application ID or email');
                    }
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Handle application retrieval
                    Swal.fire({
                        title: 'Application Retrieved',
                        text: 'Please check your email for the continuation link.',
                        icon: 'success',
                        confirmButtonColor: '#16a34a',
                        background: document.documentElement.classList.contains('dark') ?
                            '#1f2937' : '#ffffff',
                        color: document.documentElement.classList.contains('dark') ? '#ffffff' :
                            '#000000'
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
