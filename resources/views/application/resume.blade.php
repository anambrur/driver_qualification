@extends('layouts.application-form-layout')

@section('title', 'Resume Application | DOT Driver Qualification')

@section('content')
    <div class="min-h-screen bg-gray-50 flex flex-col items-center p-4 md:p-8">
        <div class="w-full max-w-4xl lg:max-w-5xl xl:max-w-6xl mx-auto">
            <div class="mb-8 md:mb-12 bg-blue-950 p-4 rounded-lg flex items-center justify-between">
                <h3 class="text-2xl font-bold text-white mb-1">
                    {{ $company->company_name }}
                </h3>
                <p class="text-gray-200 text-sm">
                    © {{ now()->year }} {{ url('/') }}
                </p>
            </div>

            <div class="bg-white rounded-2xl shadow-xl p-6 md:p-10 border border-gray-200">
                <div class="text-center mb-8 md:mb-12">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Resume Application</h2>
                    <p class="text-gray-600 text-lg md:text-xl">
                        Enter your phone number and date of birth to continue where you left off.
                    </p>
                </div>

                <form id="resumeForm" action="{{ route('public.application.verify.resume', $company->slug) }}"
                    method="POST">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 md:gap-8 mb-10">
                        <div>
                            <label for="phone" class="mb-1.5 block text-sm font-medium text-gray-700">
                                Phone <span class="text-red-500">*</span>
                            </label>
                            <input type="text" id="phone" name="phone" value="{{ old('phone') }}"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden"
                                placeholder="+1 (555) 123-4567" required />
                            @error('phone')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="date_of_birth" class="mb-1.5 block text-sm font-medium text-gray-700">
                                Date of Birth <span class="text-red-500">*</span>
                            </label>
                            <input type="date" id="date_of_birth" name="date_of_birth"
                                value="{{ old('date_of_birth') }}"
                                class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-sm text-gray-800 focus:ring-3 focus:outline-hidden"
                                required />
                            @error('date_of_birth')
                                <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="flex flex-col items-center justify-center gap-4">
                        <button type="submit"
                            class="bg-green-600 hover:bg-green-700 text-white font-medium py-3 px-8 rounded-lg transition duration-300 w-full md:w-auto">
                            Continue Application
                        </button>
                    <a href="{{ route('application.form', $company->slug) }}"
                        class="text-blue-600 hover:text-blue-800 transition-colors duration-300 font-medium">
                        Back to Application Home
                    </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('resumeForm');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                const phone = document.getElementById('phone');
                const dob = document.getElementById('date_of_birth');

                if (!phone.value.trim() || !dob.value) {
                    e.preventDefault();
                    showAppAlert('Please enter both phone number and date of birth.', 'warning');
                }
            });
        });
    </script>
@endpush
