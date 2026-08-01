Hello {{ $driver->first_name }},

@if ($companyName)
We have received your driver application for {{ $companyName }}.
@else
We have received your driver application.
@endif

Our team will review your submission shortly. You can check your application status anytime using the phone number and date of birth you provided.

Thank you for your interest.

— {{ config('app.name') }}
