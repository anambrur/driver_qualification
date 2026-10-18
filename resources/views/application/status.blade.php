@extends('layouts.application-form-layout')

@section('title', 'Check Application Status | DOT Driver Qualification')
@section('themed', '1')

@section('content')
    <x-application.entry :company="$company" icon="clipboard-list" heading="Check Application Status" intro="Enter the phone number and date of birth used on your application.">
        <form id="statusForm" action="{{ route('public.application.check.status', $company->slug) }}" method="POST" class="grid gap-6">
            @csrf
            <div class="grid gap-5 sm:grid-cols-2">
                <x-ui.field label="Phone" name="phone" for="phone" required>
                    <x-ui.input id="phone" name="phone" inputmode="tel" autocomplete="tel" placeholder="+1 (555) 123-4567" required />
                </x-ui.field>

                <x-ui.field label="Date of Birth" name="date_of_birth" for="date_of_birth" required>
                    <x-ui.input type="date" id="date_of_birth" name="date_of_birth" autocomplete="bday" required />
                </x-ui.field>
            </div>

            <div class="grid justify-items-center gap-3">
                <x-ui.button type="submit" variant="primary" class="w-full sm:w-auto">Check Status</x-ui.button>
                <x-ui.button :href="route('application.form', $company->slug)" variant="link" icon="arrow-left">Back to Application Home</x-ui.button>
            </div>
        </form>
    </x-application.entry>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('statusForm');
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
