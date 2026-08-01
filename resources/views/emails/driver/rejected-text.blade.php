Hello {{ $driver->first_name }},

@if ($companyName)
Thank you for applying with {{ $companyName }}. After careful review, we will not be moving forward with your application at this time.
@else
Thank you for your application. After careful review, we will not be moving forward at this time.
@endif

@if ($reasonLabel)
Reason: {{ $reasonLabel }}
@endif

We appreciate your interest and wish you the best in your future opportunities.

— {{ config('app.name') }}
