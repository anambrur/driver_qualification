@extends('layouts.application-form-layout')

@section('title', 'Verify Your Phone | DOT Driver Qualification')

@section('content')
    <x-application.entry :company="$company" icon="smartphone" heading="Enter Your Verification Code" intro="If an application matches the details you entered, we sent a 6-digit code to that phone.">
        <form id="statusVerifyForm" action="{{ route('public.application.status.verify.submit', $company->slug) }}" method="POST" class="mx-auto grid max-w-xs gap-6">
            @csrf
            <x-ui.field label="Verification Code" name="otp" for="otp" required>
                <x-ui.input id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" placeholder="••••••"
                    class="text-center text-lg tracking-[0.5em]" required />
            </x-ui.field>

            <div class="grid justify-items-center gap-3">
                <x-ui.button type="submit" variant="primary" class="w-full">View Status</x-ui.button>
                <x-ui.button :href="route('public.application.status', $company->slug)" variant="link">Start Over</x-ui.button>
            </div>
        </form>
    </x-application.entry>
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
