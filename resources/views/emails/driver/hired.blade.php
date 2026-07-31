@extends('emails.layouts.compliance')

@section('content')
    <p style="margin:0 0 16px;font-size:16px;line-height:24px;">
        Hello {{ $driver->first_name }},
    </p>

    <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
        @if ($companyName)
            Congratulations! {{ $companyName }} has hired you as a driver.
        @else
            Congratulations! You have been hired as a driver.
        @endif
    </p>

    @if ($driver->hire_date)
        <p style="margin:0 0 16px;font-size:15px;line-height:24px;">
            Your hire date is <strong>{{ \Carbon\Carbon::parse($driver->hire_date)->format('F j, Y') }}</strong>.
        </p>
    @endif

    <p style="margin:0;font-size:14px;line-height:22px;color:#52525b;">
        Please keep your compliance documents up to date. Contact your fleet administrator if you have any questions.
    </p>
@endsection
