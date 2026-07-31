@extends('emails.layouts.compliance')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello {{ $driver->first_name }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        @if ($companyName)
            We have received your driver application for {{ $companyName }}.
        @else
            We have received your driver application.
        @endif
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        Our team will review your submission shortly. You can check your application status anytime using the phone number and date of birth you provided.
    </p>

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        Thank you for your interest.
    </p>
@endsection
