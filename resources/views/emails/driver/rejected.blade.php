@extends('emails.layouts.compliance')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello {{ $driver->first_name }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        @if ($companyName)
            Thank you for applying with {{ $companyName }}. After careful review, we will not be moving forward with your application at this time.
        @else
            Thank you for your application. After careful review, we will not be moving forward at this time.
        @endif
    </p>

    @if ($reasonLabel)
        <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
            Reason: <strong>{{ $reasonLabel }}</strong>
        </p>
    @endif

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        We appreciate your interest and wish you the best in your future opportunities.
    </p>
@endsection
