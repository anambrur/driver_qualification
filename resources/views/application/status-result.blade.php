@extends('layouts.application-form-layout')

@section('title', 'Application Status | DOT Driver Qualification')

@section('content')
    @php
        $tone = [
            'In Progress' => 'info',
            'Under Review' => 'warning',
            'Approved' => 'success',
            'Not Approved' => 'danger',
        ][$status] ?? 'neutral';
    @endphp

    <x-application.entry :company="$company" icon="clipboard-list" heading="Application Status" intro="Here is the current status of your driver application.">
        <dl class="grid gap-4 rounded-lg border bg-muted/40 p-5 sm:grid-cols-2">
            <x-ui.detail label="Applicant" :value="trim($driver->first_name.' '.$driver->last_name)" />
            <x-ui.detail label="Phone" :value="$driver->main_phone" />
            <x-ui.detail label="Status">
                <x-ui.badge :tone="$tone">{{ $status }}</x-ui.badge>
            </x-ui.detail>
            <x-ui.detail label="Last Updated" :value="optional($driver->updated_at)->format('M d, Y g:i A')" />
        </dl>

        <div class="mt-6 grid justify-items-center gap-3">
            <x-ui.button :href="route('public.application.status', $company->slug)" variant="primary" class="w-full sm:w-auto">Check Another Application</x-ui.button>
            <x-ui.button :href="route('application.form', $company->slug)" variant="link" icon="arrow-left">Back to Application Home</x-ui.button>
        </div>
    </x-application.entry>
@endsection
