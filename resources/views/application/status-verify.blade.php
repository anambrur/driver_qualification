@extends('layouts.application-form-layout')

@section('title', 'Verify Your Phone | DOT Driver Qualification')

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
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-800 mb-3">Enter Your Verification Code</h2>
                    <p class="text-gray-600 text-lg md:text-xl">
                        If an application matches the details you entered, we sent a 6-digit code to that phone.
                    </p>
                </div>

                <form id="statusVerifyForm" action="{{ route('public.application.status.verify.submit', $company->slug) }}"
                    method="POST" class="max-w-sm mx-auto">
                    @csrf
                    <div class="mb-10">
                        <label for="otp" class="mb-1.5 block text-sm font-medium text-gray-700">
                            Verification Code <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6"
                            autocomplete="one-time-code"
                            class="shadow-theme-xs focus:border-brand-300 focus:ring-brand-500/10 h-11 w-full rounded-lg border border-gray-300 bg-transparent px-4 py-2.5 text-center text-lg tracking-[0.5em] text-gray-800 placeholder:text-gray-400 focus:ring-3 focus:outline-hidden"
                            placeholder="••••••" required />
                        @error('otp')
                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    <div class="flex flex-col items-center justify-center gap-4">
                        <button type="submit"
                            class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-8 rounded-lg transition duration-300 w-full md:w-auto">
                            View Status
                        </button>
                        <a href="{{ route('public.application.status', $company->slug) }}"
                            class="text-blue-600 hover:text-blue-800 transition-colors duration-300 font-medium">
                            Start Over
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
            const otp = document.getElementById('otp');
            if (!otp) return;

            otp.addEventListener('input', function(e) {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
            });
        });
    </script>
@endpush
