Hello {{ $driver->first_name }},

@if ($companyName)
Congratulations! {{ $companyName }} has hired you as a driver.
@else
Congratulations! You have been hired as a driver.
@endif

@if ($driver->hire_date)
Your hire date is {{ \Carbon\Carbon::parse($driver->hire_date)->format('F j, Y') }}.
@endif

Please keep your compliance documents up to date. Contact your fleet administrator if you have any questions.

— {{ config('app.name') }}
