@extends('layouts.application-form-layout')

@section('title', 'Applications Closed | DOT Driver Qualification')

@section('content')
    <x-application.entry :company="$company" icon="clipboard-x" heading="Online applications are closed"
        :intro="$company->company_name.' is not taking driver applications online right now. Please contact the company directly.'">
        <div class="grid justify-items-center gap-3">
            <x-ui.button :href="route('public.application.status', $company->slug)" variant="link" icon="search">Already applied? Check your status</x-ui.button>
        </div>
    </x-application.entry>
@endsection
