@extends('layouts.application-form-layout')

@section('title', 'Application Submitted | DOT Driver Qualification')

@section('content')
    <x-application.entry :company="$company" icon="circle-check" tone="success" heading="Application Submitted Successfully"
        :intro="'Thank you for completing your driver application for '.$company->company_name.'. Our team will review your submission and contact you if anything else is needed.'">
        @if ($driver)
            <section aria-labelledby="summary-heading" class="rounded-lg border bg-muted/40 p-5">
                <h2 id="summary-heading" class="mb-4 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Application Summary</h2>
                <dl class="grid gap-4 sm:grid-cols-2">
                    <x-ui.detail label="Applicant" :value="trim($driver->first_name.' '.$driver->last_name)" />
                    <x-ui.detail label="Phone" :value="$driver->main_phone" />
                    <x-ui.detail label="Status">
                        <x-ui.badge tone="warning">{{ ucfirst($driver->status ?? 'pending') }}</x-ui.badge>
                    </x-ui.detail>
                    <x-ui.detail label="Submitted" :value="optional($driver->updated_at)->format('M d, Y g:i A') ?? now()->format('M d, Y g:i A')" />
                </dl>
            </section>
        @endif

        <div class="mt-6 grid justify-items-center gap-3">
            <x-ui.button :href="route('public.application.status', $company->slug)" variant="primary" class="w-full sm:w-auto">Check Application Status</x-ui.button>
            <x-ui.button :href="route('application.form', $company->slug)" variant="link" icon="arrow-left">Back to Application Home</x-ui.button>
        </div>
    </x-application.entry>
@endsection
