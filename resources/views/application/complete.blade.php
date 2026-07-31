@extends('layouts.application-form-layout')

@section('title', 'Application Submitted | DOT Driver Qualification')

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
            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10 border border-gray-200 text-center">
                <div
                    class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100 text-green-600">
                    <i class="fas fa-check text-3xl"></i>
                </div>

                <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">
                    Application Submitted Successfully
                </h2>
                <p class="text-gray-600 text-lg md:text-xl mb-8 max-w-2xl mx-auto">
                    Thank you for completing your driver application for
                    <span class="font-semibold text-gray-800">{{ $company->company_name }}</span>.
                    Our team will review your submission and contact you if anything else is needed.
                </p>

                @if ($driver)
                    <div class="mb-8 rounded-xl border border-gray-200 bg-gray-50 p-5 text-left max-w-xl mx-auto">
                        <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500 mb-3">
                            Application Summary
                        </h3>
                        <dl class="space-y-3 text-sm md:text-base">
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Applicant</dt>
                                <dd class="font-medium text-gray-800">
                                    {{ $driver->first_name }} {{ $driver->last_name }}
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Phone</dt>
                                <dd class="font-medium text-gray-800">{{ $driver->main_phone }}</dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Status</dt>
                                <dd>
                                    <span
                                        class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                                        {{ ucfirst($driver->status ?? 'pending') }}
                                    </span>
                                </dd>
                            </div>
                            <div class="flex justify-between gap-4">
                                <dt class="text-gray-500">Submitted</dt>
                                <dd class="font-medium text-gray-800">
                                    {{ optional($driver->updated_at)->format('M d, Y g:i A') ?? now()->format('M d, Y g:i A') }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                @endif

                <div class="flex flex-col md:flex-row items-center justify-center gap-4 mb-8">
                    <a href="{{ route('public.application.status', $company->slug) }}"
                        class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-300 w-full md:w-auto">
                        Check Application Status
                    </a>
                    <a href="{{ route('application.form', $company->slug) }}"
                        class="text-blue-600 hover:text-blue-800 transition-colors duration-300 font-medium">
                        Back to Application Home
                    </a>
                </div>

                <div class="pt-6 border-t border-gray-200">
                    <p class="text-gray-600 text-base mb-4">Need help?</p>
                    <div class="flex flex-col md:flex-row items-center justify-center gap-3 md:gap-6">
                        <a href="tel:{{ $company->phone ?? '2092776341' }}"
                            class="flex items-center gap-2 text-blue-600 hover:text-blue-800 transition-colors duration-300">
                            <div class="bg-blue-100 p-2 md:p-3 rounded-full">
                                <i class="fas fa-phone text-blue-600"></i>
                            </div>
                            <span class="font-medium">{{ $company->phone ?: '123456789' }}</span>
                        </a>
                        @if ($company->email)
                            <div class="hidden md:block h-6 w-px bg-gray-300"></div>
                            <a href="mailto:{{ $company->email }}"
                                class="flex items-center gap-2 text-blue-600 hover:text-blue-800 transition-colors duration-300">
                                <div class="bg-blue-100 p-2 md:p-3 rounded-full">
                                    <i class="fas fa-envelope text-blue-600"></i>
                                </div>
                                <span class="font-medium">{{ $company->email }}</span>
                            </a>
                        @endif
                    </div>
                </div>
            </div>

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
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: 'Application Submitted!',
                    text: 'Your driver application has been submitted successfully. We will review it shortly.',
                    icon: 'success',
                    confirmButtonColor: '#2563eb',
                    confirmButtonText: 'OK'
                });
            }
        });
    </script>
@endpush
